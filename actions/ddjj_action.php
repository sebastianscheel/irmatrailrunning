<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accept_terms = isset($_POST['accept_terms']) ? 1 : 0;

    if ($accept_terms !== 1) {
        header("Location: /alumno/ddjj.php?error=not_accepted");
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE alumno_perfil 
            SET ddjj_aceptada = 1, ddjj_fecha_aceptacion = NOW() 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // Actualizar sesión
        $_SESSION['alumno_ddjj'] = 1;

        header("Location: /alumno/dashboard.php?msg=ddjj_ok");
        exit;
    } catch (PDOException $e) {
        error_log("Error al aceptar DDJJ: " . $e->getMessage());
        header("Location: /alumno/ddjj.php?error=db");
        exit;
    }
} else {
    header("Location: /alumno/ddjj.php");
    exit;
}
?>
