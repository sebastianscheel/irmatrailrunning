<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';
    $sexo = isset($_POST['sexo']) ? trim($_POST['sexo']) : '';

    if (empty($telefono) || empty($fecha_nacimiento) || empty($sexo)) {
        header("Location: /alumno/perfil.php?error=empty");
        exit;
    }

    try {
        // Obtener datos antes del cambio
        $stmtPrev = $pdo->prepare("SELECT id, telefono, fecha_nacimiento, sexo FROM alumno_perfil WHERE usuario_id = ?");
        $stmtPrev->execute([$_SESSION['user_id']]);
        $perfil_prev = $stmtPrev->fetch();
        $alumno_id = $perfil_prev ? $perfil_prev['id'] : null;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE alumno_perfil 
            SET telefono = ?, fecha_nacimiento = ?, sexo = ? 
            WHERE usuario_id = ?
        ");
        $stmt->execute([$telefono, $fecha_nacimiento, $sexo, $_SESSION['user_id']]);

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'usuario_id' => $_SESSION['user_id'],
            'accion' => 'actualizar_perfil',
            'entidad' => 'perfil',
            'alumno_id' => $alumno_id,
            'detalle' => "Actualizó su información de contacto (Teléfono, Fecha de Nacimiento y Sexo).",
            'datos_anteriores' => $perfil_prev,
            'datos_nuevos' => ['telefono' => $telefono, 'fecha_nacimiento' => $fecha_nacimiento, 'sexo' => $sexo]
        ]);

        $pdo->commit();

        header("Location: /alumno/perfil.php?msg=perfil_ok");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al actualizar perfil del alumno: " . $e->getMessage());
        header("Location: /alumno/perfil.php?error=db");
        exit;
    }
} else {
    header("Location: /alumno/perfil.php");
    exit;
}
?>
