<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/strava_sync_helper.php';

require_rol('alumno');

// 1. Obtener ID del Alumno
$stmtPerfil = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
$stmtPerfil->execute([$_SESSION['user_id']]);
$alumno_id = $stmtPerfil->fetchColumn();

if (!$alumno_id) {
    header("Location: /logout.php");
    exit;
}

// 2. Sincronizar
$res = sincronizarActividadesStrava($alumno_id, $pdo);

if ($res['success']) {
    header("Location: /alumno/dashboard.php?msg=strava_synced&count=" . $res['sincronizados']);
} else {
    if ($res['error'] === 'no_tokens') {
        header("Location: /alumno/perfil.php?error=no_strava");
    } elseif ($res['error'] === 'token_refresh_failed') {
        header("Location: /alumno/perfil.php?error=strava_refresh_fail");
    } else {
        header("Location: /alumno/perfil.php?error=strava_api_fail");
    }
}
exit;
