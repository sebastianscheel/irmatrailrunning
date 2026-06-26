<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario está logueado. Si no, redirige al login.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
}

/**
 * Requiere que el usuario tenga un rol específico o uno de los roles permitidos en un array.
 */
function require_rol($roles_permitidos) {
    check_login();
    
    if (!is_array($roles_permitidos)) {
        $roles_permitidos = [$roles_permitidos];
    }

    if (!in_array($_SESSION['user_rol'], $roles_permitidos)) {
        // Redirección forzada según el rol que realmente tenga
        if (in_array($_SESSION['user_rol'], ['admin', 'entrenador'])) {
            header("Location: /admin/dashboard.php");
        } else {
            header("Location: /alumno/dashboard.php");
        }
        exit;
    }
}

/**
 * Valida el estado actual del alumno desde la base de datos (activo y DDJJ firmada).
 * Si no ha firmado la DDJJ, lo redirige forzadamente a la página de firma.
 */
function check_alumno_status($pdo) {
    check_login();
    if ($_SESSION['user_rol'] !== 'alumno') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT ap.activo, ap.ddjj_aceptada 
        FROM alumno_perfil ap 
        WHERE ap.usuario_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perfil) {
        // Actualizar datos en sesión para coincidir con la base de datos
        $_SESSION['alumno_activo'] = (int)$perfil['activo'];
        $_SESSION['alumno_ddjj'] = (int)$perfil['ddjj_aceptada'];

        $current_script = $_SERVER['SCRIPT_NAME'];
        
        // Si no aceptó la DDJJ y no está ya en la página de DDJJ ni enviando el formulario, redirigir
        if ($_SESSION['alumno_ddjj'] == 0 && strpos($current_script, 'ddjj.php') === false && strpos($current_script, 'ddjj_action.php') === false) {
            header("Location: /alumno/ddjj.php");
            exit;
        }
    } else {
        // Por seguridad, si no tiene perfil, forzar salida
        header("Location: /logout.php");
        exit;
    }
}
?>
