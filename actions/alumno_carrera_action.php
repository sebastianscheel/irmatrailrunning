<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /alumno/calendario.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'register') {
        $carrera_id = (int)$_POST['carrera_id'];
        $alumno_id = (int)$_POST['alumno_id'];
        $distancia = trim($_POST['distancia_elegida']);
        $objetivo = trim($_POST['objetivo']);

        if ($carrera_id <= 0 || $alumno_id <= 0 || empty($distancia) || empty($objetivo)) {
            throw new Exception("Datos incompletos.");
        }

        // Check if already registered
        $stmtCheck = $pdo->prepare("SELECT id FROM alumno_carrera WHERE alumno_id = ? AND carrera_id = ?");
        $stmtCheck->execute([$alumno_id, $carrera_id]);
        if ($stmtCheck->fetch()) {
            throw new Exception("Ya estás anotado a esta carrera.");
        }

        $stmt = $pdo->prepare("INSERT INTO alumno_carrera (alumno_id, carrera_id, objetivo, distancia_elegida) VALUES (?, ?, ?, ?)");
        $stmt->execute([$alumno_id, $carrera_id, $objetivo, $distancia]);

        header("Location: /alumno/calendario.php?msg=" . urlencode("¡Genial! Tu entrenador ya puede ver tu objetivo."));
        exit;

    } elseif ($action === 'create_custom') {
        $alumno_id = (int)$_POST['alumno_id'];
        $titulo = trim($_POST['titulo']);
        $fecha = trim($_POST['fecha']);
        $lugar = trim($_POST['lugar'] ?? '');
        $distancia = trim($_POST['distancia_elegida'] ?? '');
        $objetivo = trim($_POST['objetivo']);

        if ($alumno_id <= 0 || empty($titulo) || empty($fecha) || empty($objetivo)) {
            throw new Exception("Datos obligatorios incompletos.");
        }

        // Get actual alumno_id from session
        $stmtAlumno = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
        $stmtAlumno->execute([$_SESSION['user_id']]);
        $my_alumno_id = $stmtAlumno->fetchColumn();

        if ($alumno_id != $my_alumno_id) {
            throw new Exception("No tienes permiso para realizar esta acción.");
        }

        // Insert custom carrera
        $stmtCar = $pdo->prepare("INSERT INTO carreras (titulo, fecha, lugar, distancias, alumno_creador_id) VALUES (?, ?, ?, ?, ?)");
        $stmtCar->execute([$titulo, $fecha, $lugar ?: null, $distancia ?: null, $alumno_id]);
        $carrera_id = $pdo->lastInsertId();

        // Link in alumno_carrera
        $stmtSub = $pdo->prepare("INSERT INTO alumno_carrera (alumno_id, carrera_id, objetivo, distancia_elegida) VALUES (?, ?, ?, ?)");
        $stmtSub->execute([$alumno_id, $carrera_id, $objetivo, $distancia]);

        header("Location: /alumno/calendario.php?msg=" . urlencode("¡Objetivo personal guardado con éxito!"));
        exit;

    } elseif ($action === 'cancel') {
        $inscripcion_id = (int)$_POST['inscripcion_id'];
        
        // Get registration details
        $stmtCheck = $pdo->prepare("SELECT alumno_id, carrera_id FROM alumno_carrera WHERE id = ?");
        $stmtCheck->execute([$inscripcion_id]);
        $reg = $stmtCheck->fetch();

        if (!$reg) {
            throw new Exception("Inscripción no encontrada.");
        }

        $owner = $reg['alumno_id'];
        $carrera_id = $reg['carrera_id'];

        // Get actual alumno_id from session
        $stmtAlumno = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
        $stmtAlumno->execute([$_SESSION['user_id']]);
        $my_alumno_id = $stmtAlumno->fetchColumn();

        if ($owner != $my_alumno_id) {
            throw new Exception("No tienes permiso para cancelar esta inscripción.");
        }

        // Check if the carrera is custom
        $stmtCarrera = $pdo->prepare("SELECT alumno_creador_id FROM carreras WHERE id = ?");
        $stmtCarrera->execute([$carrera_id]);
        $alumno_creador_id = $stmtCarrera->fetchColumn();

        if ($alumno_creador_id == $my_alumno_id) {
            // Delete the custom carrera (which cascades and deletes alumno_carrera)
            $stmtDel = $pdo->prepare("DELETE FROM carreras WHERE id = ?");
            $stmtDel->execute([$carrera_id]);
        } else {
            // Delete only the subscription link
            $stmtDel = $pdo->prepare("DELETE FROM alumno_carrera WHERE id = ?");
            $stmtDel->execute([$inscripcion_id]);
        }

        header("Location: /alumno/calendario.php?msg=" . urlencode("Se ha eliminado el objetivo."));
        exit;
    }

} catch (Exception $e) {
    header("Location: /alumno/calendario.php?err=" . urlencode($e->getMessage()));
    exit;
}
