<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol(['admin', 'entrenador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    if ($alumno_id <= 0 || !in_array($action, ['aprobar', 'rechazar'])) {
        header("Location: /admin/certificados.php?error=invalid");
        exit;
    }

    try {
        if ($action === 'aprobar') {
            $stmt = $pdo->prepare("
                UPDATE alumno_perfil 
                SET certificado_medico_estado = 'aprobado',
                    certificado_medico_comentario = NULL
                WHERE id = ?
            ");
            $stmt->execute([$alumno_id]);
            header("Location: /admin/certificados.php?msg=aprobado_ok");
            exit;
        } elseif ($action === 'rechazar') {
            if (empty($comentario)) {
                header("Location: /admin/certificados.php?error=empty_comment");
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE alumno_perfil 
                SET certificado_medico_estado = 'rechazado',
                    certificado_medico_comentario = ?
                WHERE id = ?
            ");
            $stmt->execute([$comentario, $alumno_id]);
            header("Location: /admin/certificados.php?msg=rechazado_ok");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error al procesar certificado mÃ©dico: " . $e->getMessage());
        header("Location: /admin/certificados.php?error=db");
        exit;
    }
} else {
    header("Location: /admin/certificados.php");
    exit;
}
?>

