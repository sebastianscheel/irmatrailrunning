<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    if ($action === 'create_plantilla_full') {
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $duracion = (int)$_POST['duracion_dias'];
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $entrenador_id = $_SESSION['user_id'];

        if (empty($titulo) || $duracion <= 0) {
            header("Location: /admin/plantillas.php?msg=error");
            exit;
        }

        $pdo->beginTransaction();

        // 1. Insert template
        $stmt = $pdo->prepare("INSERT INTO plantillas (entrenador_id, titulo, descripcion, duracion_dias, fecha_inicio) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$entrenador_id, $titulo, $descripcion, $duracion, $fecha_inicio]);
        $plantilla_id = $pdo->lastInsertId();

        // 2. Insert day routines
        if (isset($_POST['rutinas']) && is_array($_POST['rutinas'])) {
            $stmtR = $pdo->prepare("
                INSERT INTO plantilla_rutinas (plantilla_id, dia_offset, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['rutinas'] as $dia_offset => $r) {
                if (isset($r['activo']) && $r['activo'] == '1') {
                    $r_titulo = trim($r['titulo']);
                    $r_desc = trim($r['descripcion']);
                    $r_tipo = trim($r['tipo_sesion']);
                    $r_dist = !empty($r['distancia_km']) ? (float)$r['distancia_km'] : 0.00;
                    $r_ritmo = trim($r['ritmo_sugerido']);
                    $r_terr = trim($r['terreno']);

                    $stmtR->execute([$plantilla_id, $dia_offset, $r_titulo, $r_desc, $r_tipo, $r_dist, $r_ritmo, $r_terr]);
                }
            }
        }

        // Auditoría
        registrarAuditoria($pdo, [
            'accion' => 'crear_plantilla_full',
            'entidad' => 'plantilla',
            'entidad_id' => $plantilla_id,
            'detalle' => "Creó la plantilla de entrenamiento '$titulo' ($duracion días) con su cronograma.",
            'datos_nuevos' => ['titulo' => $titulo, 'descripcion' => $descripcion, 'duracion_dias' => $duracion]
        ]);

        $pdo->commit();
        header("Location: /admin/plantillas.php?msg=plantilla_ok");
        exit;
    }

    if ($action === 'update_plantilla_full') {
        $plantilla_id = (int)$_POST['plantilla_id'];
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $duracion = (int)$_POST['duracion_dias'];
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $entrenador_id = $_SESSION['user_id'];

        if ($plantilla_id <= 0 || empty($titulo) || $duracion <= 0) {
            header("Location: /admin/plantillas.php?msg=error");
            exit;
        }

        $pdo->beginTransaction();

        // 1. Update template basic info
        $user_rol = $_SESSION['user_rol'];
        if (in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            $stmtU = $pdo->prepare("UPDATE plantillas SET titulo = ?, descripcion = ?, duracion_dias = ?, fecha_inicio = ? WHERE id = ?");
            $stmtU->execute([$titulo, $descripcion, $duracion, $fecha_inicio, $plantilla_id]);
        } else {
            $stmtU = $pdo->prepare("UPDATE plantillas SET titulo = ?, descripcion = ?, duracion_dias = ?, fecha_inicio = ? WHERE id = ? AND entrenador_id = ?");
            $stmtU->execute([$titulo, $descripcion, $duracion, $fecha_inicio, $plantilla_id, $entrenador_id]);
        }

        // 2. Delete existing routines for this template to overwrite cleanly
        $stmtD = $pdo->prepare("DELETE FROM plantilla_rutinas WHERE plantilla_id = ?");
        $stmtD->execute([$plantilla_id]);

        // 3. Re-insert day routines
        if (isset($_POST['rutinas']) && is_array($_POST['rutinas'])) {
            $stmtR = $pdo->prepare("
                INSERT INTO plantilla_rutinas (plantilla_id, dia_offset, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['rutinas'] as $dia_offset => $r) {
                if ($dia_offset > $duracion) continue; // Skip days outside the new duration
                if (isset($r['activo']) && $r['activo'] == '1') {
                    $r_titulo = trim($r['titulo']);
                    $r_desc = trim($r['descripcion']);
                    $r_tipo = trim($r['tipo_sesion']);
                    $r_dist = !empty($r['distancia_km']) ? (float)$r['distancia_km'] : 0.00;
                    $r_ritmo = trim($r['ritmo_sugerido']);
                    $r_terr = trim($r['terreno']);

                    $stmtR->execute([$plantilla_id, $dia_offset, $r_titulo, $r_desc, $r_tipo, $r_dist, $r_ritmo, $r_terr]);
                }
            }
        }

        // Auditoría
        registrarAuditoria($pdo, [
            'accion' => 'actualizar_plantilla_full',
            'entidad' => 'plantilla',
            'entidad_id' => $plantilla_id,
            'detalle' => "Actualizó la plantilla de entrenamiento '$titulo' y sus rutinas.",
            'datos_nuevos' => ['titulo' => $titulo, 'descripcion' => $descripcion, 'duracion_dias' => $duracion]
        ]);

        $pdo->commit();
        header("Location: /admin/plantillas.php?msg=plantilla_ok");
        exit;
    }

    if ($action === 'delete_plantilla') {
        $plantilla_id = (int)$_GET['id'];
        $entrenador_id = $_SESSION['user_id'];

        // Obtener datos de la plantilla antes de borrarla
        $stmtP = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
        $stmtP->execute([$plantilla_id]);
        $plantilla = $stmtP->fetch();
        
        if ($plantilla) {
            $user_rol = $_SESSION['user_rol'];
            if (in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
                $stmt = $pdo->prepare("DELETE FROM plantillas WHERE id = ?");
                $stmt->execute([$plantilla_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM plantillas WHERE id = ? AND entrenador_id = ?");
                $stmt->execute([$plantilla_id, $entrenador_id]);
            }

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'eliminar_plantilla',
                'entidad' => 'plantilla',
                'entidad_id' => $plantilla_id,
                'detalle' => "Eliminó la plantilla de entrenamiento '" . $plantilla['titulo'] . "'.",
                'datos_anteriores' => $plantilla
            ]);
        }

        header("Location: /admin/plantillas.php?msg=delete_ok");
        exit;
    }

    if ($action === 'add_rutina') {
        $plantilla_id = (int)$_POST['plantilla_id'];
        $dia_offset = (int)$_POST['dia_offset'];
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $tipo_sesion = $_POST['tipo_sesion'];
        $distancia = !empty($_POST['distancia_km']) ? (float)$_POST['distancia_km'] : 0.00;
        $ritmo = trim($_POST['ritmo_sugerido']);
        $terreno = trim($_POST['terreno']);

        // Insert or Update the routine for that specific day_offset
        $stmtCheck = $pdo->prepare("SELECT id FROM plantilla_rutinas WHERE plantilla_id = ? AND dia_offset = ?");
        $stmtCheck->execute([$plantilla_id, $dia_offset]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe) {
            $stmt = $pdo->prepare("
                UPDATE plantilla_rutinas 
                SET titulo = ?, descripcion = ?, tipo_sesion = ?, distancia_km = ?, ritmo_sugerido = ?, terreno = ?
                WHERE plantilla_id = ? AND dia_offset = ?
            ");
            $stmt->execute([$titulo, $descripcion, $tipo_sesion, $distancia, $ritmo, $terreno, $plantilla_id, $dia_offset]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO plantilla_rutinas (plantilla_id, dia_offset, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$plantilla_id, $dia_offset, $titulo, $descripcion, $tipo_sesion, $distancia, $ritmo, $terreno]);
        }

        header("Location: /admin/plantillas.php?id=$plantilla_id&msg=rutina_ok");
        exit;
    }

    if ($action === 'delete_rutina') {
        $rutina_id = (int)$_GET['rutina_id'];
        $plantilla_id = (int)$_GET['plantilla_id'];
        
        $stmt = $pdo->prepare("DELETE FROM plantilla_rutinas WHERE id = ? AND plantilla_id = ?");
        $stmt->execute([$rutina_id, $plantilla_id]);

        header("Location: /admin/plantillas.php?id=$plantilla_id&msg=delete_rutina_ok");
        exit;
    }

    if ($action === 'aplicar_plantilla') {
        $plantilla_id = (int)$_POST['plantilla_id'];
        $alumno_id = (int)$_POST['alumno_id'];
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d');

        // Fetch template
        $stmtP = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
        $stmtP->execute([$plantilla_id]);
        $plantilla = $stmtP->fetch();

        if (!$plantilla) {
            header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=invalid_plantilla");
            exit;
        }

        // Fetch routines of the template
        $stmtR = $pdo->prepare("SELECT * FROM plantilla_rutinas WHERE plantilla_id = ? ORDER BY dia_offset ASC");
        $stmtR->execute([$plantilla_id]);
        $rutinas = $stmtR->fetchAll();

        // Calcular rango de fechas para respaldar rutinas anteriores
        $duracion = $plantilla['duracion_dias'];
        $fecha_fin = date('Y-m-d', strtotime("$fecha_inicio + " . ($duracion - 1) . " days"));

        // Obtener rutinas anteriores que serán sobrescritas en este rango
        $stmtPrev = $pdo->prepare("SELECT * FROM rutina_asignada WHERE alumno_id = ? AND fecha >= ? AND fecha <= ?");
        $stmtPrev->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
        $rutinas_anteriores = $stmtPrev->fetchAll();

        // Obtener usuario_id del alumno para notificaciones
        $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
        $stmtUsr->execute([$alumno_id]);
        $student_user_id = $stmtUsr->fetchColumn();

        $pdo->beginTransaction();

        foreach ($rutinas as $r) {
            $offset = $r['dia_offset'] - 1; // dia_offset 1 means the start date itself.
            $fecha_target = date('Y-m-d', strtotime("$fecha_inicio +$offset days"));

            // Check if routine exists for that date
            $stmtCheck = $pdo->prepare("SELECT id FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtCheck->execute([$alumno_id, $fecha_target]);
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
                     INSERT INTO rutina_asignada (alumno_id, fecha, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtI->execute([$alumno_id, $fecha_target, $r['titulo'], $r['descripcion'], $r['tipo_sesion'], $r['distancia_km'], $r['ritmo_sugerido'], $r['terreno']]);
            }
        }

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'aplicar_plantilla',
            'entidad' => 'plantilla',
            'entidad_id' => $plantilla_id,
            'alumno_id' => $alumno_id,
            'detalle' => "Aplicó la plantilla '" . $plantilla['titulo'] . "' desde el $fecha_inicio hasta el $fecha_fin.",
            'datos_anteriores' => $rutinas_anteriores,
            'datos_nuevos' => ['plantilla_id' => $plantilla_id, 'fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin]
        ]);

        // Notificar al alumno
        if ($student_user_id) {
            crearNotificacion($pdo, $student_user_id, "Planificación Semanal Cargada", "Tu entrenador aplicó la plantilla '" . $plantilla['titulo'] . "' a tu calendario desde el " . date('d/m', strtotime($fecha_inicio)) . ".", "/alumno/dashboard.php");
        }

        $pdo->commit();
        $redirect_source = isset($_POST['redirect_source']) ? trim($_POST['redirect_source']) : 'planificador';
        if ($redirect_source === 'plantillas') {
            header("Location: /admin/plantillas.php?msg=plantilla_aplicada");
        } else {
            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=plantilla_aplicada");
        }
        exit;
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en plantillas: " . $e->getMessage());
    header("Location: /admin/plantillas.php?error=db");
    exit;
}

header("Location: /admin/plantillas.php");
exit;
