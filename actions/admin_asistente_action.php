<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/asistente_planificacion_engine.php';
require_once __DIR__ . '/../includes/asistente_gemini.php';

// Aumentar el tiempo límite de ejecución para peticiones a la API de IA (hasta 3 mins)
set_time_limit(180);

// Validar que sea admin o entrenador total
require_rol(['admin', 'entrenador_total']);

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'generar') {
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $nivel = isset($_POST['nivel']) ? trim($_POST['nivel']) : 'Principiante';
    $objetivo = isset($_POST['objetivo']) ? trim($_POST['objetivo']) : 'Adaptación';
    $semanas = isset($_POST['semanas']) ? (int)$_POST['semanas'] : 8;
    
    $dias_seleccionados = isset($_POST['dias_seleccionados']) ? $_POST['dias_seleccionados'] : [];
    if (empty($dias_seleccionados)) {
        echo json_encode(['success' => false, 'message' => 'Debes seleccionar al menos un día de entrenamiento.']);
        exit;
    }
    $dias_seleccionados = array_map('intval', $dias_seleccionados);
    sort($dias_seleccionados);
    $dias_semana = count($dias_seleccionados);
    
    $modo = isset($_POST['modo']) ? trim($_POST['modo']) : 'plantillas';
    $fecha_inicio = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : date('Y-m-d');
    
    // Datos opcionales de carrera
    $carrera_distancia = isset($_POST['carrera_distancia']) ? trim($_POST['carrera_distancia']) : '';
    $carrera_desnivel = isset($_POST['carrera_desnivel']) ? trim($_POST['carrera_desnivel']) : '';
    $carrera_info = "";
    if (!empty($carrera_distancia)) {
        $carrera_info = $carrera_distancia . "K";
        if (!empty($carrera_desnivel)) {
            $carrera_info .= " con " . $carrera_desnivel . "m D+";
        }
    }

    try {
        $plan = [];

        // Obtener rutinas de la biblioteca para Modo A (Plantillas/Sesiones)
        $biblioteca_rutinas = [];
        if ($modo === 'plantillas') {
            $stmtBib = $pdo->prepare("SELECT titulo, descripcion, tipo_sesion FROM entrenamientos_individuales");
            $stmtBib->execute();
            $biblioteca_rutinas = $stmtBib->fetchAll();
        }

        $estructura_ia = isset($_POST['estructura_ia']) ? trim($_POST['estructura_ia']) : '';

        try {
            $stmtIA = $pdo->query("SELECT * FROM configuracion_ia LIMIT 1");
            $config_ia = $stmtIA->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $config_ia = null;
        }
        if (!$config_ia) {
            $config_ia = [
                'disciplina' => 'Trail Running',
                'rol_entrenador' => 'preparador físico experto',
                'tipos_sesion' => 'Fondo|Fuerza|Pasadas|Regenerativo|Cuestas',
                'estructura_descripcion' => "Entrada en calor:\nBloque principal:\nVuelta a la calma:",
                'tono_respuesta' => 'Profesional y motivador'
            ];
        }

        $asistenteGemini = null;
        if ($modo === 'ia') {
            try {
                $asistenteGemini = new AsistenteGemini($config_ia);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }

        // Generar semana a semana
        for ($w = 1; $w <= $semanas; $w++) {
            $fase = get_fase_semana($w, $semanas);
            $volumen = get_volumen_semanal($w, $semanas, $nivel);
            
            $rutinas_semana = [];
            
            if ($modo === 'ia') {
                // Modo IA (Gemini) - Genera toda la semana en una sola llamada, adaptándose a las directivas
                try {
                    $resIA = $asistenteGemini->generarSemana(
                        $w,
                        $semanas,
                        $nivel,
                        $fase,
                        $volumen,
                        $dias_seleccionados,
                        $carrera_info,
                        $estructura_ia,
                        $config_ia
                    );
                    
                    if (isset($resIA['rutinas']) && is_array($resIA['rutinas'])) {
                        $idx = 0;
                        foreach ($resIA['rutinas'] as $s) {
                            $dia_offset = isset($dias_seleccionados[$idx]) ? $dias_seleccionados[$idx] : (isset($s['dia']) ? (int)$s['dia'] : 1);
                            $dias_totales_offset = (($w - 1) * 7) + ($dia_offset - 1);
                            $fecha_sesion = date('Y-m-d', strtotime("$fecha_inicio +$dias_totales_offset days"));
                            
                            $rutinas_semana[] = [
                                'fecha' => $fecha_sesion,
                                'dia_semana' => obtener_dia_semana_nombre($dia_offset),
                                'titulo' => $s['titulo'] ?? 'Entrenamiento',
                                'descripcion' => $s['descripcion'] ?? '',
                                'tipo_sesion' => $s['tipo_sesion'] ?? 'Fondo',
                                'distancia_km' => isset($s['distancia_km']) ? (float)$s['distancia_km'] : 0.0,
                                'ritmo_sugerido' => $s['ritmo_sugerido'] ?? 'Ritmo controlado',
                                'terreno' => $s['terreno'] ?? 'Plano'
                            ];
                            $idx++;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error en Gemini al generar semana $w: " . $e->getMessage());
                    // Fallback usando estructura PHP clásica
                    $estructura = generar_estructura_semana($w, $semanas, $dias_semana, $nivel, $fase, $volumen);
                    $idx = 0;
                    foreach ($estructura as $s) {
                        $dia_offset = isset($dias_seleccionados[$idx]) ? $dias_seleccionados[$idx] : $s['dia'];
                        $dias_totales_offset = (($w - 1) * 7) + ($dia_offset - 1);
                        $fecha_sesion = date('Y-m-d', strtotime("$fecha_inicio +$dias_totales_offset days"));
                        
                        $rutinas_semana[] = [
                            'fecha' => $fecha_sesion,
                            'dia_semana' => obtener_dia_semana_nombre($dia_offset),
                            'titulo' => $s['titulo'],
                            'descripcion' => "Entrenamiento de " . $s['tipo_sesion'] . " programado de forma adaptativa. Foco en fase de " . $fase . ". Ritmo sugerido: " . $s['ritmo_sugerido'] . ".",
                            'tipo_sesion' => $s['tipo_sesion'],
                            'distancia_km' => $s['distancia_km'],
                            'ritmo_sugerido' => $s['ritmo_sugerido'],
                            'terreno' => $s['terreno']
                        ];
                        $idx++;
                    }
                }
            } else {
                // Modo Plantillas/Biblioteca
                $estructura = generar_estructura_semana($w, $semanas, $dias_semana, $nivel, $fase, $volumen);
                $idx = 0;
                foreach ($estructura as $s) {
                    // Buscar en la biblioteca por tipo de sesión
                    $candidatos = [];
                    foreach ($biblioteca_rutinas as $br) {
                        if (strcasecmp($br['tipo_sesion'], $s['tipo_sesion']) === 0) {
                            $candidatos[] = $br;
                        }
                    }
                    
                    $descripcion = '';
                    $titulo = $s['titulo'];
                    if (!empty($candidatos)) {
                        $elegido = $candidatos[array_rand($candidatos)];
                        $titulo = $elegido['titulo'];
                        $descripcion = $elegido['descripcion'];
                    } else {
                        $descripcion = "Entrenamiento de " . $s['tipo_sesion'] . " en terreno " . $s['terreno'] . ". Realizar a ritmo " . $s['ritmo_sugerido'] . ".";
                    }
                    
                    $dia_offset = isset($dias_seleccionados[$idx]) ? $dias_seleccionados[$idx] : $s['dia'];
                    $dias_totales_offset = (($w - 1) * 7) + ($dia_offset - 1);
                    $fecha_sesion = date('Y-m-d', strtotime("$fecha_inicio +$dias_totales_offset days"));
                    
                    $rutinas_semana[] = [
                        'fecha' => $fecha_sesion,
                        'dia_semana' => obtener_dia_semana_nombre($dia_offset),
                        'titulo' => $titulo,
                        'descripcion' => $descripcion,
                        'tipo_sesion' => $s['tipo_sesion'],
                        'distancia_km' => $s['distancia_km'],
                        'ritmo_sugerido' => $s['ritmo_sugerido'],
                        'terreno' => $s['terreno']
                    ];
                    $idx++;
                }
            }
            
            $plan[] = [
                'semana' => $w,
                'fase' => $fase,
                'volumen_total_km' => $volumen,
                'rutinas' => $rutinas_semana
            ];
        }

        echo json_encode([
            'success' => true,
            'plan' => $plan
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

if ($action === 'aplicar') {
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $rutinas_json = isset($_POST['rutinas']) ? $_POST['rutinas'] : '';
    
    if ($alumno_id <= 0 || empty($rutinas_json)) {
        echo json_encode(['success' => false, 'error' => 'Datos insuficientes para aplicar el plan.']);
        exit;
    }

    $rutinas = json_decode($rutinas_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Formato de rutinas inválido.']);
        exit;
    }

    $confirmar_sobrescritura = isset($_POST['confirmar_sobrescritura']) ? (int)$_POST['confirmar_sobrescritura'] : 0;

    try {
        // Paso 1: Verificación de conflictos
        if ($confirmar_sobrescritura === 0) {
            $fechas_conflictivas = [];
            foreach ($rutinas as $r) {
                $fecha = $r['fecha'];
                $stmtCheck = $pdo->prepare("SELECT id FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
                $stmtCheck->execute([$alumno_id, $fecha]);
                if ($stmtCheck->fetchColumn()) {
                    $fechas_conflictivas[] = $fecha;
                }
            }
            
            if (count($fechas_conflictivas) > 0) {
                echo json_encode([
                    'conflict' => true,
                    'message' => 'Existen ' . count($fechas_conflictivas) . ' entrenamiento(s) previamente agendado(s) en las fechas seleccionadas. ¿Deseas sobrescribirlos con la nueva planificación?'
                ]);
                exit;
            }
        }

        $pdo->beginTransaction();

        // Obtener usuario_id del alumno para notificaciones
        $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
        $stmtUsr->execute([$alumno_id]);
        $student_user_id = $stmtUsr->fetchColumn();

        foreach ($rutinas as $r) {
            $fecha = $r['fecha'];
            
            // Check si ya existe rutina en esa fecha para ese alumno
            $stmtCheck = $pdo->prepare("SELECT id FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtCheck->execute([$alumno_id, $fecha]);
            $existente_id = $stmtCheck->fetchColumn();

            if ($existente_id) {
                // Update
                $stmtU = $pdo->prepare("
                    UPDATE rutina_asignada 
                    SET titulo = ?, descripcion = ?, tipo_sesion = ?, distancia_km = ?, ritmo_sugerido = ?, terreno = ?, completada = 0
                    WHERE id = ?
                ");
                $stmtU->execute([$r['titulo'], $r['descripcion'], $r['tipo_sesion'], $r['distancia_km'], $r['ritmo_sugerido'], $r['terreno'], $existente_id]);
            } else {
                // Insert
                $stmtI = $pdo->prepare("
                    INSERT INTO rutina_asignada (alumno_id, fecha, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno, completada) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmtI->execute([$alumno_id, $fecha, $r['titulo'], $r['descripcion'], $r['tipo_sesion'], $r['distancia_km'], $r['ritmo_sugerido'], $r['terreno']]);
            }
        }

        // Registrar auditoría
        require_once __DIR__ . '/../includes/audit_helper.php';
        registrarAuditoria($pdo, [
            'accion' => 'aplicar_plan_asistido',
            'entidad' => 'alumno_perfil',
            'entidad_id' => $alumno_id,
            'alumno_id' => $alumno_id,
            'detalle' => "Aplicó un plan generado asistido de " . count($rutinas) . " rutinas."
        ]);

        // Notificar al alumno
        if ($student_user_id) {
            crearNotificacion($pdo, $student_user_id, "Calendario de Entrenamientos Actualizado", "Tu entrenador ha cargado nuevos entrenamientos asistidos en tu calendario.", "/alumno/dashboard.php");
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
        exit;
    }
}

function obtener_dia_semana_nombre($dia) {
    $nombres = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo'
    ];
    return isset($nombres[$dia]) ? $nombres[$dia] : '';
}
