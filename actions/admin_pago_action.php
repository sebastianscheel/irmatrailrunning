<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador o entrenador total
require_rol(['admin', 'entrenador_total']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $pago_id = isset($_POST['pago_id']) ? (int)$_POST['pago_id'] : 0;
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;

    if ($pago_id <= 0 || $alumno_id <= 0 || !in_array($action, ['aprobar', 'rechazar'])) {
        header("Location: /admin/pagos.php?error=invalid");
        exit;
    }

    try {
        if ($action === 'aprobar') {
            $pdo->beginTransaction();

            // 1. Aprobar el registro de pago
            $stmtPago = $pdo->prepare("
                UPDATE pago_registro 
                SET estado = 'aprobado', 
                    fecha_aprobacion = NOW() 
                WHERE id = ?
            ");
            $stmtPago->execute([$pago_id]);

            // 2. Activar la membresía del alumno automáticamente
            $stmtAlumno = $pdo->prepare("
                UPDATE alumno_perfil 
                SET activo = 1 
                WHERE id = ?
            ");
            $stmtAlumno->execute([$alumno_id]);

            $pdo->commit();
            header("Location: /admin/pagos.php?msg=aprobado_ok");
            exit;
        } elseif ($action === 'rechazar') {
            // Rechazar el pago
            $stmtPago = $pdo->prepare("
                UPDATE pago_registro 
                SET estado = 'rechazado' 
                WHERE id = ?
            ");
            $stmtPago->execute([$pago_id]);
            header("Location: /admin/pagos.php?msg=rechazado_ok");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al procesar pago: " . $e->getMessage());
        header("Location: /admin/pagos.php?error=db");
        exit;
    }
} else {
    header("Location: /admin/pagos.php");
    exit;
}
?>
