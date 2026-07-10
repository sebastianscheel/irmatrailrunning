<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador o entrenador total
require_rol(['admin', 'entrenador_total']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

    if ($alumno_id <= 0 || !in_array($action, ['aprobar', 'rechazar'])) {
        header("Location: /admin/certificados.php?error=invalid");
        exit;
    }

    try {
        // Obtener usuario_id del alumno y su nombre
        $stmtUsr = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
        $stmtUsr->execute([$alumno_id]);
        $student_user_id = $stmtUsr->fetchColumn();

        $alumno_nombre = '';
        if ($student_user_id) {
            $stmtName = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
            $stmtName->execute([$student_user_id]);
            $usr = $stmtName->fetch();
            if ($usr) {
                $alumno_nombre = trim($usr['nombre'] . ' ' . $usr['apellido']);
            }
        }

        require_once __DIR__ . '/../includes/audit_helper.php';

        if ($action === 'aprobar') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE alumno_perfil 
                SET certificado_medico_estado = 'aprobado',
                    certificado_medico_comentario = NULL
                WHERE id = ?
            ");
            $stmt->execute([$alumno_id]);

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'aprobar_certificado',
                'entidad' => 'perfil',
                'alumno_id' => $alumno_id,
                'alumno_nombre' => $alumno_nombre,
                'detalle' => "Aprobó el apto médico presentado por el alumno $alumno_nombre."
            ]);

            // Notificar al alumno
            if ($student_user_id) {
                crearNotificacion($pdo, $student_user_id, "Apto Médico Aprobado", "Tu certificado médico fue aprobado con éxito.", "/alumno/perfil.php");
            }

            $pdo->commit();

            header("Location: /admin/certificados.php?msg=aprobado_ok");
            exit;
        } elseif ($action === 'rechazar') {
            if (empty($comentario)) {
                header("Location: /admin/certificados.php?error=empty_comment");
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE alumno_perfil 
                SET certificado_medico_estado = 'rechazado',
                    certificado_medico_comentario = ?
                WHERE id = ?
            ");
            $stmt->execute([$comentario, $alumno_id]);

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'rechazar_certificado',
                'entidad' => 'perfil',
                'alumno_id' => $alumno_id,
                'alumno_nombre' => $alumno_nombre,
                'detalle' => "Rechazó el apto médico de $alumno_nombre. Motivo: '$comentario'.",
                'datos_nuevos' => ['comentario' => $comentario]
            ]);

            // Notificar al alumno
            if ($student_user_id) {
                crearNotificacion($pdo, $student_user_id, "Apto Médico Rechazado", "Tu certificado médico fue rechazado. Motivo: $comentario. Por favor, sube uno nuevo.", "/alumno/perfil.php");
            }

            $pdo->commit();

            header("Location: /admin/certificados.php?msg=rechazado_ok");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al procesar certificado médico: " . $e->getMessage());
        header("Location: /admin/certificados.php?error=db");
        exit;
    }
} else {
    header("Location: /admin/certificados.php");
    exit;
}
?>

