<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que sea entrenador o admin
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
    $mes = isset($_POST['mes']) ? trim($_POST['mes']) : '';
    $comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
    $fecha_redirect = isset($_POST['fecha_redirect']) ? trim($_POST['fecha_redirect']) : date('Y-m-d');
    $entrenador_id = $_SESSION['user_id'];

    if ($alumno_id <= 0 || empty($mes) || empty($comentario)) {
        header("Location: /admin/planificador.php?alumno_id=$alumno_id&fecha=$fecha_redirect&error=empty_feedback");
        exit;
    }

    try {
        // Guardar o actualizar la retroalimentación
        $stmtSave = $pdo->prepare("
            INSERT INTO feedback_mensual (alumno_id, entrenador_id, mes, comentario)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                comentario = VALUES(comentario),
                entrenador_id = VALUES(entrenador_id),
                fecha_creacion = NOW()
        ");
        $stmtSave->execute([$alumno_id, $entrenador_id, $mes, $comentario]);

        // Notificar al alumno
        // Buscar el usuario_id del alumno
        $stmtUser = $pdo->prepare("SELECT usuario_id FROM alumno_perfil WHERE id = ?");
        $stmtUser->execute([$alumno_id]);
        $student_user_id = $stmtUser->fetchColumn();

        if ($student_user_id) {
            $meses_es = [
                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
            ];
            $mes_parts = explode('-', $mes);
            $mes_nombre = isset($meses_es[$mes_parts[1]]) ? $meses_es[$mes_parts[1]] . " " . $mes_parts[0] : $mes;

            crearNotificacion(
                $pdo, 
                $student_user_id, 
                "Retroalimentación Mensual", 
                "Tu entrenador dejó un comentario general para el mes de $mes_nombre.", 
                "/alumno/dashboard.php"
            );
        }

        header("Location: /admin/planificador.php?alumno_id=$alumno_id&fecha=$fecha_redirect&msg=feedback_mensual_ok");
        exit;
    } catch (PDOException $e) {
        error_log("Error al guardar feedback mensual: " . $e->getMessage());
        header("Location: /admin/planificador.php?alumno_id=$alumno_id&fecha=$fecha_redirect&error=db");
        exit;
    }
} else {
    header("Location: /admin/planificador.php");
    exit;
}
?>
