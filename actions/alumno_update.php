<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';

    if (empty($telefono) || empty($fecha_nacimiento)) {
        header("Location: /alumno/perfil.php?error=empty");
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE alumno_perfil 
            SET telefono = ?, fecha_nacimiento = ? 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$telefono, $fecha_nacimiento, $_SESSION['user_id']]);

        header("Location: /alumno/perfil.php?msg=perfil_ok");
        exit;
    } catch (PDOException $e) {
        error_log("Error al actualizar perfil del alumno: " . $e->getMessage());
        header("Location: /alumno/perfil.php?error=db");
        exit;
    }
} else {
    header("Location: /alumno/perfil.php");
    exit;
}
?>
