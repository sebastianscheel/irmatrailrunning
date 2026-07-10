<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que sea admin o entrenador
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;

    if ($alumno_id <= 0 && $action !== 'restore_rutina') {
        header("Location: /admin/alumnos.php?error=invalid_alumno");
        exit;
    }

    try {
        if ($action === 'create_rutina') {
            $fecha = trim($_POST['fecha']);
            $titulo = trim($_POST['titulo']);
            $descripcion = trim($_POST['descripcion']);
            $tipo_sesion = trim($_POST['tipo_sesion']);
            $distancia_km = isset($_POST['distancia_km']) ? (float)$_POST['distancia_km'] : 0.0;
            $ritmo_sugerido = trim($_POST['ritmo_sugerido']);
            $terreno = trim($_POST['terreno']);

            if (empty($fecha) || empty($titulo) || empty($descripcion) || empty($tipo_sesion)) {
                header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=empty_fields");
                exit;
            }

            // Obtener el usuario_id del alumno para notificaciones
            $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
            $stmtUsr->execute([$alumno_id]);
            $student_user_id = $stmtUsr->fetchColumn();

            $pdo->beginTransaction();

            // Capturar datos anteriores de la rutina si ya existe una para este día
            $stmtPrev = $pdo->prepare("SELECT * FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtPrev->execute([$alumno_id, $fecha]);
            $rutina_anterior = $stmtPrev->fetch();

            // Eliminar si ya existe rutina en esa fecha para ese alumno
            $stmtDel = $pdo->prepare("DELETE FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtDel->execute([$alumno_id, $fecha]);

            // Insertar nueva rutina
            $stmtInsert = $pdo->prepare("
                INSERT INTO rutina_asignada (alumno_id, fecha, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$alumno_id, $fecha, $titulo, $descripcion, $tipo_sesion, $distancia_km, $ritmo_sugerido, $terreno]);
            $nueva_rutina_id = $pdo->lastInsertId();

            $datos_nuevos = [
                'alumno_id' => $alumno_id,
                'fecha' => $fecha,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'tipo_sesion' => $tipo_sesion,
                'distancia_km' => $distancia_km,
                'ritmo_sugerido' => $ritmo_sugerido,
                'terreno' => $terreno
            ];

            // Registrar auditoría y notificar
            if ($rutina_anterior) {
                registrarAuditoria($pdo, [
                    'accion' => 'editar_rutina',
                    'entidad' => 'rutina',
                    'entidad_id' => $nueva_rutina_id,
                    'alumno_id' => $alumno_id,
                    'detalle' => "Modificó el entrenamiento programado para el día $fecha.",
                    'datos_anteriores' => $rutina_anterior,
                    'datos_nuevos' => $datos_nuevos
                ]);
                
                if ($student_user_id) {
                    crearNotificacion($pdo, $student_user_id, "Planificación Modificada", "Tu entrenador modificó el entrenamiento del " . date('d/m', strtotime($fecha)) . ": $titulo.", "/alumno/dashboard.php");
                }
            } else {
                registrarAuditoria($pdo, [
                    'accion' => 'crear_rutina',
                    'entidad' => 'rutina',
                    'entidad_id' => $nueva_rutina_id,
                    'alumno_id' => $alumno_id,
                    'detalle' => "Cargó un nuevo entrenamiento para el día $fecha: '$titulo'.",
                    'datos_nuevos' => $datos_nuevos
                ]);

                if ($student_user_id) {
                    crearNotificacion($pdo, $student_user_id, "Nuevo Entrenamiento Asignado", "Tu entrenador programó una nueva rutina para el " . date('d/m', strtotime($fecha)) . ": $titulo.", "/alumno/dashboard.php");
                }
            }

            $pdo->commit();
            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=rutina_ok");
            exit;

        } elseif ($action === 'delete_rutina') {
            $rutina_id = isset($_POST['rutina_id']) ? (int)$_POST['rutina_id'] : 0;

            if ($rutina_id <= 0) {
                header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=invalid_rutina");
                exit;
            }

            // Obtener el usuario_id del alumno
            $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
            $stmtUsr->execute([$alumno_id]);
            $student_user_id = $stmtUsr->fetchColumn();

            // Buscar datos de la rutina antes de borrarla
            $stmtPrev = $pdo->prepare("SELECT * FROM rutina_asignada WHERE id = ? AND alumno_id = ?");
            $stmtPrev->execute([$rutina_id, $alumno_id]);
            $rutina = $stmtPrev->fetch();

            if ($rutina) {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("DELETE FROM rutina_asignada WHERE id = ? AND alumno_id = ?");
                $stmt->execute([$rutina_id, $alumno_id]);

                // Registrar auditoría
                registrarAuditoria($pdo, [
                    'accion' => 'eliminar_rutina',
                    'entidad' => 'rutina',
                    'entidad_id' => $rutina_id,
                    'alumno_id' => $alumno_id,
                    'detalle' => "Eliminó el entrenamiento programado para el día " . $rutina['fecha'] . " ('" . $rutina['titulo'] . "').",
                    'datos_anteriores' => $rutina
                ]);

                // Notificar al alumno
                if ($student_user_id) {
                    crearNotificacion($pdo, $student_user_id, "Entrenamiento Cancelado", "Se eliminó el entrenamiento programado para el " . date('d/m', strtotime($rutina['fecha'])) . ".", "/alumno/dashboard.php");
                }

                $pdo->commit();
            }

            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=delete_ok");
            exit;

        } elseif ($action === 'restore_rutina') {
            // Solo admin y entrenador_total pueden restaurar
            require_rol(['admin', 'entrenador_total']);

            $log_id = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
            if ($log_id <= 0) {
                header("Location: /admin/historial.php?error=invalid_log");
                exit;
            }

            // Obtener el log de auditoría
            $stmtLog = $pdo->prepare("SELECT * FROM audit_log WHERE id = ?");
            $stmtLog->execute([$log_id]);
            $log = $stmtLog->fetch();

            if (!$log || empty($log['datos_anteriores'])) {
                header("Location: /admin/historial.php?error=not_restorable");
                exit;
            }

            $datos = json_decode($log['datos_anteriores'], true);
            $rec_alumno_id = $datos['alumno_id'];
            $rec_fecha = $datos['fecha'];

            // Verificar si ya hay una rutina en esa fecha
            $stmtCheck = $pdo->prepare("SELECT id FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtCheck->execute([$rec_alumno_id, $rec_fecha]);
            if ($stmtCheck->fetch()) {
                header("Location: /admin/historial.php?error=date_occupied");
                exit;
            }

            // Obtener el usuario_id del alumno
            $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
            $stmtUsr->execute([$rec_alumno_id]);
            $student_user_id = $stmtUsr->fetchColumn();

            $pdo->beginTransaction();

            // Re-insertar la rutina
            $stmtInsert = $pdo->prepare("
                INSERT INTO rutina_asignada (alumno_id, fecha, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno, completada) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmtInsert->execute([
                $rec_alumno_id,
                $rec_fecha,
                $datos['titulo'],
                $datos['descripcion'],
                $datos['tipo_sesion'],
                $datos['distancia_km'],
                $datos['ritmo_sugerido'],
                $datos['terreno']
            ]);
            $nueva_rutina_id = $pdo->lastInsertId();

            // Registrar auditoría de la restauración
            registrarAuditoria($pdo, [
                'accion' => 'restaurar_rutina',
                'entidad' => 'rutina',
                'entidad_id' => $nueva_rutina_id,
                'alumno_id' => $rec_alumno_id,
                'detalle' => "Restauró la rutina borrada para el día $rec_fecha ('" . $datos['titulo'] . "').",
                'datos_nuevos' => $datos
            ]);

            // Notificar al alumno
            if ($student_user_id) {
                crearNotificacion($pdo, $student_user_id, "Entrenamiento Restaurado", "Tu entrenador restauró la rutina para el " . date('d/m', strtotime($rec_fecha)) . ": " . $datos['titulo'] . ".", "/alumno/dashboard.php");
            }

            $pdo->commit();
            header("Location: /admin/historial.php?msg=restore_ok");
            exit;
        } elseif ($action === 'assign_content') {
            $titulo = trim($_POST['titulo']);
            $tipo = trim($_POST['tipo']);
            $descripcion = trim($_POST['descripcion']);
            $origen = isset($_POST['origen_recurso']) ? trim($_POST['origen_recurso']) : 'url';
            
            $url = '';
            if ($origen === 'url') {
                $url = trim($_POST['url']);
            } else {
                if (isset($_FILES['recurso_archivo']) && $_FILES['recurso_archivo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['recurso_archivo'];
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                    
                    // Validar tipo de archivo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mime, $allowed_types)) {
                        header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=invalid_file_type");
                        exit;
                    }
                    
                    // Guardar archivo
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'resource_' . uniqid() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../uploads/recursos/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $url = '/uploads/recursos/' . $filename;
                    } else {
                        header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=file_save_err");
                        exit;
                    }
                } else {
                    header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=file_upload_err");
                    exit;
                }
            }

            if (empty($titulo) || empty($tipo) || empty($url)) {
                header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=empty_content");
                exit;
            }

            $pdo->beginTransaction();

            // 1. Insertar recurso
            $stmtRec = $pdo->prepare("
                INSERT INTO contenido_recurso (titulo, tipo, url, descripcion) 
                VALUES (?, ?, ?, ?)
            ");
            $stmtRec->execute([$titulo, $tipo, $url, $descripcion]);
            $recurso_id = $pdo->lastInsertId();

            // 2. Asignar recurso al alumno
            $stmtAsig = $pdo->prepare("
                INSERT INTO contenido_asignado (alumno_id, recurso_id) 
                VALUES (?, ?)
            ");
            $stmtAsig->execute([$alumno_id, $recurso_id]);

            $pdo->commit();
            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=content_ok");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error en acciones del planificador: " . $e->getMessage());
        header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=db");
        exit;
    }
} else {
    header("Location: /admin/alumnos.php");
    exit;
}
?>

