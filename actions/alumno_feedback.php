<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rutina_id = isset($_POST['rutina_id']) ? (int)$_POST['rutina_id'] : 0;
    $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $tiempo = isset($_POST['tiempo']) ? (float)$_POST['tiempo'] : 0.0;
    $distancia_real = !empty($_POST['distancia_real']) ? (float)$_POST['distancia_real'] : null;
    $desnivel_real = !empty($_POST['desnivel_real']) ? (int)$_POST['desnivel_real'] : null;
    $esfuerzo = isset($_POST['esfuerzo']) ? (int)$_POST['esfuerzo'] : 5;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    // Si es una rutina nueva (rutina_id = 0), requerimos una fecha. El tiempo debe ser >= 0.
    if ($rutina_id < 0 || ($rutina_id === 0 && empty($fecha)) || $tiempo < 0) {
        header("Location: /alumno/dashboard.php?error=invalid_feedback");
        exit;
    }

    try {
        // Obtener el ID del alumno y su entrenador asignado
        $stmtPerfil = $pdo->prepare("SELECT id, entrenador_id FROM alumno_perfil WHERE usuario_id = ?");
        $stmtPerfil->execute([$_SESSION['user_id']]);
        $perfil = $stmtPerfil->fetch();

        if (!$perfil) {
            header("Location: /logout.php");
            exit;
        }
        $alumno_id = $perfil['id'];
        $entrenador_id = $perfil['entrenador_id'];

        // Obtener nombre del alumno
        $stmtName = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
        $stmtName->execute([$_SESSION['user_id']]);
        $usr = $stmtName->fetch();
        $alumno_nombre = $usr ? ($usr['nombre'] . ' ' . $usr['apellido']) : 'Un alumno';

        if ($rutina_id > 0) {
            // Respaldar rutina actual
            $stmtPrev = $pdo->prepare("SELECT * FROM rutina_asignada WHERE id = ?");
            $stmtPrev->execute([$rutina_id]);
            $rutina_prev = $stmtPrev->fetch();

            $pdo->beginTransaction();

            // Actualizar la rutina existente
            $stmtUpdate = $pdo->prepare("
                UPDATE rutina_asignada 
                SET completada = 1,
                    feedback_tiempo_minutos = ?,
                    distancia_real = ?,
                    desnivel_real = ?,
                    feedback_esfuerzo = ?,
                    feedback_comentario = ?,
                    fecha_registro_feedback = NOW()
                WHERE id = ? AND alumno_id = ?
            ");
            $stmtUpdate->execute([$tiempo, $distancia_real, $desnivel_real, $esfuerzo, $comentario, $rutina_id, $alumno_id]);

            $datos_nuevos = [
                'feedback_tiempo_minutos' => $tiempo,
                'distancia_real' => $distancia_real,
                'desnivel_real' => $desnivel_real,
                'feedback_esfuerzo' => $esfuerzo,
                'feedback_comentario' => $comentario
            ];

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'registrar_feedback',
                'entidad' => 'rutina',
                'entidad_id' => $rutina_id,
                'alumno_id' => $alumno_id,
                'detalle' => "Registró feedback para el entrenamiento del " . ($rutina_prev['fecha'] ?? '') . ".",
                'datos_anteriores' => $rutina_prev,
                'datos_nuevos' => $datos_nuevos
            ]);

            // Notificar al entrenador
            if ($entrenador_id) {
                crearNotificacion(
                    $pdo, 
                    $entrenador_id, 
                    "Feedback de Alumno", 
                    "$alumno_nombre completó y dejó comentarios en el entrenamiento del " . date('d/m', strtotime($rutina_prev['fecha'] ?? '')) . ".", 
                    "/admin/planificador.php?alumno_id=$alumno_id"
                );
            }

            $pdo->commit();
        } else {
            // Validar si ya existe una rutina programada para ese día
            $stmtCheck = $pdo->prepare("SELECT id FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtCheck->execute([$alumno_id, $fecha]);
            if ($stmtCheck->fetch()) {
                header("Location: /alumno/dashboard.php?error=duplicate_date");
                exit;
            }

            // Insertar una nueva rutina/comentario voluntario
            $tipo_sesion = isset($_POST['tipo_sesion']) ? trim($_POST['tipo_sesion']) : 'Descanso';
            $titulo = !empty($_POST['titulo']) ? trim($_POST['titulo']) : ($tipo_sesion === 'Descanso' ? 'Día de Descanso' : 'Entrenamiento Extra');
            $descripcion = "Registrado voluntariamente por el alumno.";

            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("
                INSERT INTO rutina_asignada (
                    alumno_id, fecha, titulo, descripcion, tipo_sesion, completada,
                    feedback_tiempo_minutos, distancia_real, desnivel_real, feedback_esfuerzo,
                    feedback_comentario, fecha_registro_feedback
                ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([
                $alumno_id, $fecha, $titulo, $descripcion, $tipo_sesion,
                $tiempo, $distancia_real, $desnivel_real, $esfuerzo, $comentario
            ]);
            $nueva_rutina_id = $pdo->lastInsertId();

            $datos_nuevos = [
                'alumno_id' => $alumno_id,
                'fecha' => $fecha,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'tipo_sesion' => $tipo_sesion,
                'feedback_tiempo_minutos' => $tiempo,
                'distancia_real' => $distancia_real,
                'desnivel_real' => $desnivel_real,
                'feedback_esfuerzo' => $esfuerzo,
                'feedback_comentario' => $comentario
            ];

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'registrar_feedback_extra',
                'entidad' => 'rutina',
                'entidad_id' => $nueva_rutina_id,
                'alumno_id' => $alumno_id,
                'detalle' => "Registró un entrenamiento extra/voluntario para el día $fecha.",
                'datos_nuevos' => $datos_nuevos
            ]);

            // Notificar al entrenador
            if ($entrenador_id) {
                crearNotificacion(
                    $pdo, 
                    $entrenador_id, 
                    "Entrenamiento Extra Registrado", 
                    "$alumno_nombre cargó una rutina extra/voluntaria para el " . date('d/m', strtotime($fecha)) . ".", 
                    "/admin/planificador.php?alumno_id=$alumno_id"
                );
            }

            $pdo->commit();
        }

        header("Location: /alumno/dashboard.php?msg=feedback_ok");
        exit;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al guardar feedback de alumno: " . $e->getMessage());
        header("Location: /alumno/dashboard.php?error=db");
        exit;
    }
} else {
    header("Location: /alumno/dashboard.php");
    exit;
}
?>
