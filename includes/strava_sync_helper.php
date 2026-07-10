<?php
// includes/strava_sync_helper.php

function sincronizarActividadesStrava($alumno_id, $pdo) {
    // IMPORTANTE: REEMPLAZAR ESTAS CREDENCIALES
    $client_id = 'AQUI_TU_CLIENT_ID';
    $client_secret = 'AQUI_TU_CLIENT_SECRET';

    // 1. Obtener Token del Alumno
    $stmtToken = $pdo->prepare("SELECT * FROM strava_tokens WHERE alumno_id = ?");
    $stmtToken->execute([$alumno_id]);
    $tokenData = $stmtToken->fetch();

    if (!$tokenData) {
        return ['success' => false, 'error' => 'no_tokens'];
    }

    $access_token = $tokenData['access_token'];

    // 2. Renovar token si expiró
    if (time() >= $tokenData['expires_at']) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $tokenData['refresh_token'],
            'grant_type' => 'refresh_token'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            $access_token = $data['access_token'];
            $stmtUpdate = $pdo->prepare("UPDATE strava_tokens SET access_token = ?, refresh_token = ?, expires_at = ? WHERE alumno_id = ?");
            $stmtUpdate->execute([$access_token, $data['refresh_token'], $data['expires_at'], $alumno_id]);
        } else {
            return ['success' => false, 'error' => 'token_refresh_failed'];
        }
    }

    // 3. Consultar Actividades de Strava (Últimos 14 días)
    $after = strtotime("-14 days");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/activities?after={$after}&per_page=30");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$access_token}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        return ['success' => false, 'error' => 'api_error', 'response' => $response];
    }

    $actividades = json_decode($response, true);

    // 4. Volcar datos sobre `rutina_asignada`
    $actividades_por_dia = [];

    foreach ($actividades as $act) {
        if (in_array($act['type'], ['Run', 'TrailRun', 'Hike', 'Walk'])) {
            $fecha = substr($act['start_date_local'], 0, 10); // YYYY-MM-DD
            
            if (!isset($actividades_por_dia[$fecha])) {
                $actividades_por_dia[$fecha] = [
                    'distancia_m' => 0,
                    'tiempo_s' => 0,
                    'desnivel_m' => 0,
                    'max_dist' => 0,
                    'activity_id' => null
                ];
            }
            
            $actividades_por_dia[$fecha]['distancia_m'] += $act['distance'];
            $actividades_por_dia[$fecha]['tiempo_s'] += $act['moving_time'];
            $actividades_por_dia[$fecha]['desnivel_m'] += $act['total_elevation_gain'];
            
            if ($act['distance'] >= $actividades_por_dia[$fecha]['max_dist']) {
                $actividades_por_dia[$fecha]['max_dist'] = $act['distance'];
                $actividades_por_dia[$fecha]['activity_id'] = $act['id'];
            }
        }
    }

    $sincronizados = 0;

    foreach ($actividades_por_dia as $fecha => $datos) {
        $stmtCheck = $pdo->prepare("SELECT id, completada FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
        $stmtCheck->execute([$alumno_id, $fecha]);
        $rutina = $stmtCheck->fetch();

        if ($rutina) {
            $dist_km = round($datos['distancia_m'] / 1000, 2);
            $tiem_min = round($datos['tiempo_s'] / 60);
            $desn_m = round($datos['desnivel_m']);
            $act_id = $datos['activity_id'];
            
            $ritmo_real = null;
            if ($datos['distancia_m'] > 0 && $datos['tiempo_s'] > 0) {
                $segundos_por_km = $datos['tiempo_s'] / ($datos['distancia_m'] / 1000);
                $minutos = floor($segundos_por_km / 60);
                $segundos = round($segundos_por_km % 60);
                if ($segundos < 10) {
                    $segundos = '0' . $segundos;
                }
                $ritmo_real = $minutos . ":" . $segundos . " min/km";
            }

            $stmtU = $pdo->prepare("
                UPDATE rutina_asignada 
                SET completada = 1, 
                    distancia_real = ?, 
                    feedback_tiempo_minutos = ?, 
                    desnivel_real = ?,
                    strava_activity_id = ?,
                    ritmo_real = ?,
                    fecha_registro_feedback = NOW()
                WHERE id = ?
            ");
            $stmtU->execute([$dist_km, $tiem_min, $desn_m, $act_id, $ritmo_real, $rutina['id']]);
            $sincronizados++;
        }
    }

    if ($sincronizados > 0) {
        // Obtener nombre del alumno, usuario_id y su entrenador asignado
        $stmtUsr = $pdo->prepare("
            SELECT u.nombre, u.apellido, p.usuario_id, p.entrenador_id 
            FROM usuarios u 
            JOIN alumno_perfil p ON p.usuario_id = u.id 
            WHERE p.id = ?
        ");
        $stmtUsr->execute([$alumno_id]);
        $studentInfo = $stmtUsr->fetch();
        
        if ($studentInfo) {
            $alumno_nombre = trim($studentInfo['nombre'] . ' ' . $studentInfo['apellido']);
            $student_user_id = $studentInfo['usuario_id'];
            $entrenador_id = $studentInfo['entrenador_id'];

            // Cargar helper de auditoría
            require_once __DIR__ . '/audit_helper.php';

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'usuario_id' => $student_user_id,
                'usuario_nombre' => $alumno_nombre,
                'usuario_rol' => 'alumno',
                'accion' => 'sync_strava',
                'entidad' => 'rutina',
                'alumno_id' => $alumno_id,
                'detalle' => "Sincronizó automáticamente $sincronizados entrenamientos desde Strava.",
                'datos_nuevos' => ['sincronizados' => $sincronizados]
            ]);

            // Notificar al entrenador
            if ($entrenador_id) {
                crearNotificacion(
                    $pdo, 
                    $entrenador_id, 
                    "Sincronización de Strava", 
                    "$alumno_nombre sincronizó $sincronizados entrenamientos desde Strava.", 
                    "/admin/planificador.php?alumno_id=$alumno_id"
                );
            }
        }
    }

    return ['success' => true, 'sincronizados' => $sincronizados];
}
