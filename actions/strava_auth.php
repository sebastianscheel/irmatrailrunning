<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol('alumno');

// IMPORTANTE: REEMPLAZAR ESTAS CREDENCIALES
$client_id = 'AQUI_TU_CLIENT_ID';
$client_secret = 'AQUI_TU_CLIENT_SECRET';
// En producción, debe ser la URL final (ej: https://irmatrail.com/actions/strava_auth.php)
$redirect_uri = 'http://localhost:8000/actions/strava_auth.php';

// 1. Redirección inicial hacia Strava
if (!isset($_GET['code']) && !isset($_GET['error'])) {
    $url = "https://www.strava.com/oauth/authorize?client_id={$client_id}&response_type=code&redirect_uri={$redirect_uri}&approval_prompt=force&scope=activity:read_all";
    header("Location: " . $url);
    exit;
}

// 2. Strava devolvió un error (ej: usuario canceló)
if (isset($_GET['error'])) {
    header("Location: /alumno/perfil.php?error=strava_denied");
    exit;
}

// 3. Callback exitoso con código de autorización
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Solicitar Tokens
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'grant_type' => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        // Obtener el ID del alumno
        $stmtPerfil = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
        $stmtPerfil->execute([$_SESSION['user_id']]);
        $alumno_id = $stmtPerfil->fetchColumn();

        if ($alumno_id) {
            $athlete_id = isset($data['athlete']['id']) ? $data['athlete']['id'] : null;
            // Guardar tokens
            $stmt = $pdo->prepare("
                INSERT INTO strava_tokens (alumno_id, access_token, refresh_token, expires_at, athlete_id)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                athlete_id = VALUES(athlete_id),
                fecha_conexion = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $alumno_id,
                $data['access_token'],
                $data['refresh_token'],
                $data['expires_at'],
                $athlete_id
            ]);
            header("Location: /alumno/perfil.php?msg=strava_ok");
        } else {
            header("Location: /logout.php");
        }
    } else {
        error_log("Strava Auth Error: " . json_encode($data));
        header("Location: /alumno/perfil.php?error=strava_fail");
    }
    exit;
}
