<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rutina_id = isset($_POST['rutina_id']) ? (int)$_POST['rutina_id'] : 0;
    $tiempo = isset($_POST['tiempo']) ? (float)$_POST['tiempo'] : 0.0;
    $esfuerzo = isset($_POST['esfuerzo']) ? (int)$_POST['esfuerzo'] : 5;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    if ($rutina_id <= 0 || $tiempo <= 0) {
        header("Location: /alumno/dashboard.php?error=invalid_feedback");
        exit;
    }

    try {
        // Obtener el ID del alumno
        $stmtPerfil = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
        $stmtPerfil->execute([$_SESSION['user_id']]);
        $perfil = $stmtPerfil->fetch();

        if (!$perfil) {
            header("Location: /logout.php");
            exit;
        }
        $alumno_id = $perfil['id'];

        // Actualizar la rutina agregando el feedback y marcándola como completada
        $stmtUpdate = $pdo->prepare("
            UPDATE rutina_asignada 
            SET completada = 1,
                feedback_tiempo_minutos = ?,
                feedback_esfuerzo = ?,
                feedback_comentario = ?,
                fecha_registro_feedback = NOW()
            WHERE id = ? AND alumno_id = ?
        ");
        $stmtUpdate->execute([$tiempo, $esfuerzo, $comentario, $rutina_id, $alumno_id]);

        header("Location: /alumno/dashboard.php?msg=feedback_ok");
        exit;
    } catch (PDOException $e) {
        error_log("Error al guardar feedback: " . $e->getMessage());
        header("Location: /alumno/dashboard.php?error=db");
        exit;
    }
} else {
    header("Location: /alumno/dashboard.php");
    exit;
}
?>
