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
        // Obtener perfil para validar si es menor
        $stmtPerfil = $pdo->prepare("SELECT id, fecha_nacimiento FROM alumno_perfil WHERE usuario_id = ?");
        $stmtPerfil->execute([$_SESSION['user_id']]);
        $perfil = $stmtPerfil->fetch();

        $es_menor = false;
        if ($perfil && !empty($perfil['fecha_nacimiento'])) {
            $nacimiento = new DateTime($perfil['fecha_nacimiento']);
            $hoy = new DateTime();
            $diferencia = $hoy->diff($nacimiento);
            if ($diferencia->y < 18) {
                $es_menor = true;
            }
        }

        $tutor_nombre = null;
        $tutor_dni = null;
        $tutor_parentesco = null;

        if ($es_menor) {
            $tutor_nombre = isset($_POST['tutor_nombre']) ? trim($_POST['tutor_nombre']) : '';
            $tutor_dni = isset($_POST['tutor_dni']) ? trim($_POST['tutor_dni']) : '';
            $tutor_parentesco = isset($_POST['tutor_parentesco']) ? trim($_POST['tutor_parentesco']) : '';

            if (empty($tutor_nombre) || empty($tutor_dni) || empty($tutor_parentesco)) {
                header("Location: /alumno/ddjj.php?error=missing_tutor");
                exit;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE alumno_perfil 
            SET ddjj_aceptada = 1, 
                ddjj_fecha_aceptacion = NOW(),
                tutor_nombre = ?,
                tutor_dni = ?,
                tutor_parentesco = ?
            WHERE usuario_id = ?
        ");
        $stmt->execute([$tutor_nombre, $tutor_dni, $tutor_parentesco, $_SESSION['user_id']]);

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
