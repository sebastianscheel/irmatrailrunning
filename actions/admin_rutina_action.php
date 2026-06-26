<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea admin
require_rol(['admin', 'entrenador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;

    if ($alumno_id <= 0) {
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

            $pdo->beginTransaction();

            // Eliminar si ya existe rutina en esa fecha para ese alumno para evitar duplicaciones del mismo dÃ­a
            $stmtDel = $pdo->prepare("DELETE FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
            $stmtDel->execute([$alumno_id, $fecha]);

            // Insertar nueva rutina
            $stmtInsert = $pdo->prepare("
                INSERT INTO rutina_asignada (alumno_id, fecha, titulo, descripcion, tipo_sesion, distancia_km, ritmo_sugerido, terreno) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$alumno_id, $fecha, $titulo, $descripcion, $tipo_sesion, $distancia_km, $ritmo_sugerido, $terreno]);

            $pdo->commit();
            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=rutina_ok");
            exit;

        } elseif ($action === 'delete_rutina') {
            $rutina_id = isset($_POST['rutina_id']) ? (int)$_POST['rutina_id'] : 0;

            if ($rutina_id <= 0) {
                header("Location: /admin/planificador.php?alumno_id=$alumno_id&error=invalid_rutina");
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM rutina_asignada WHERE id = ? AND alumno_id = ?");
            $stmt->execute([$rutina_id, $alumno_id]);

            header("Location: /admin/planificador.php?alumno_id=$alumno_id&msg=delete_ok");
            exit;

        } elseif ($action === 'assign_content') {
            $titulo = trim($_POST['titulo']);
            $tipo = trim($_POST['tipo']);
            $url = trim($_POST['url']);
            $descripcion = trim($_POST['descripcion']);

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

