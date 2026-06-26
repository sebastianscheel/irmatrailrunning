<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar login y rol
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mes_pagado = isset($_POST['mes_pagado']) ? trim($_POST['mes_pagado']) : '';
    $monto = isset($_POST['monto']) ? (float)$_POST['monto'] : 0.0;

    if (empty($mes_pagado) || $monto <= 0) {
        header("Location: /alumno/reportar_pago.php?error=empty");
        exit;
    }

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        header("Location: /alumno/reportar_pago.php?error=upload_err");
        exit;
    }

    $file = $_FILES['comprobante'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5 MB

    // Validar tipo de archivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        header("Location: /alumno/reportar_pago.php?error=invalid_type");
        exit;
    }

    // Validar tamaño
    if ($file['size'] > $max_size) {
        header("Location: /alumno/reportar_pago.php?error=invalid_size");
        exit;
    }

    // Generar nombre único
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'pay_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $ext;
    $upload_dir = __DIR__ . '/../uploads/comprobantes/';
    $dest_path = $upload_dir . $filename;

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        try {
            $db_url = '/uploads/comprobantes/' . $filename;

            // Obtener el ID del perfil del alumno
            $stmtPerfil = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
            $stmtPerfil->execute([$_SESSION['user_id']]);
            $perfil = $stmtPerfil->fetch();

            if (!$perfil) {
                header("Location: /logout.php");
                exit;
            }
            $alumno_id = $perfil['id'];

            // Guardar reporte de pago
            $stmtInsert = $pdo->prepare("
                INSERT INTO pago_registro (alumno_id, mes_pagado, monto, comprobante_url, estado) 
                VALUES (?, ?, ?, ?, 'pendiente')
            ");
            $stmtInsert->execute([$alumno_id, $mes_pagado, $monto, $db_url]);

            header("Location: /alumno/reportar_pago.php?msg=pago_ok");
            exit;
        } catch (PDOException $e) {
            error_log("Error al registrar pago en BD: " . $e->getMessage());
            header("Location: /alumno/reportar_pago.php?error=db");
            exit;
        }
    } else {
        header("Location: /alumno/reportar_pago.php?error=move_err");
        exit;
    }
} else {
    header("Location: /alumno/reportar_pago.php");
    exit;
}
?>
