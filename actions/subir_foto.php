<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_perfil'])) {
    $file = $_FILES['foto_perfil'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: /alumno/perfil.php?error=upload_err");
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        header("Location: /alumno/perfil.php?error=invalid_type");
        exit;
    }

    if ($file['size'] > $max_size) {
        header("Location: /alumno/perfil.php?error=invalid_size");
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/perfiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'perfil_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $db_path = '/uploads/perfiles/' . $filename;
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil_url = ? WHERE id = ?");
            $stmt->execute([$db_path, $_SESSION['user_id']]);
            
            header("Location: /alumno/perfil.php?msg=foto_ok");
            exit;
        } catch (PDOException $e) {
            header("Location: /alumno/perfil.php?error=db");
            exit;
        }
    } else {
        header("Location: /alumno/perfil.php?error=move_err");
        exit;
    }
} else {
    header("Location: /alumno/perfil.php");
    exit;
}
?>
