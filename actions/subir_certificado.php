<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
        header("Location: /alumno/perfil.php?error=upload_err");
        exit;
    }

    $file = $_FILES['certificado'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5 MB

    // Validar tipo de archivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        header("Location: /alumno/perfil.php?error=invalid_type");
        exit;
    }

    // Validar tamaño
    if ($file['size'] > $max_size) {
        header("Location: /alumno/perfil.php?error=invalid_size");
        exit;
    }

    // Generar nombre de archivo único
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cert_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $ext;
    $upload_dir = __DIR__ . '/../uploads/certificados/';
    $dest_path = $upload_dir . $filename;

    // Asegurarse de que el directorio existe
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        try {
            // Guardar en la base de datos (guardamos la ruta relativa)
            $db_url = '/uploads/certificados/' . $filename;
            
            // Borrar certificado anterior físicamente si existe
            $stmtGet = $pdo->prepare("SELECT certificado_medico_url FROM alumno_perfil WHERE usuario_id = ?");
            $stmtGet->execute([$_SESSION['user_id']]);
            $prev = $stmtGet->fetch();
            if ($prev && !empty($prev['certificado_medico_url'])) {
                $prev_physical = __DIR__ . '/..' . $prev['certificado_medico_url'];
                if (file_exists($prev_physical)) {
                    unlink($prev_physical);
                }
            }

            // Actualizar registro en base de datos
            $stmtUpdate = $pdo->prepare("
                UPDATE alumno_perfil 
                SET certificado_medico_url = ?, 
                    certificado_medico_estado = 'pendiente', 
                    certificado_medico_comentario = NULL 
                WHERE usuario_id = ?
            ");
            
            $pdo->beginTransaction();
            $stmtUpdate->execute([$db_url, $_SESSION['user_id']]);

            // Obtener alumno_id y entrenador_id
            $stmtA = $pdo->prepare("SELECT id, entrenador_id FROM alumno_perfil WHERE usuario_id = ?");
            $stmtA->execute([$_SESSION['user_id']]);
            $perfil = $stmtA->fetch();
            $alumno_id = $perfil ? $perfil['id'] : null;
            $entrenador_id = $perfil ? $perfil['entrenador_id'] : null;

            // Obtener nombre del alumno
            $stmtName = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
            $stmtName->execute([$_SESSION['user_id']]);
            $usr = $stmtName->fetch();
            $alumno_nombre = $usr ? ($usr['nombre'] . ' ' . $usr['apellido']) : 'Un alumno';

            // Registrar auditoría
            require_once __DIR__ . '/../includes/audit_helper.php';
            registrarAuditoria($pdo, [
                'usuario_id' => $_SESSION['user_id'],
                'accion' => 'subir_certificado',
                'entidad' => 'perfil',
                'alumno_id' => $alumno_id,
                'detalle' => "Subió un nuevo certificado médico para revisión.",
                'datos_nuevos' => ['certificado_medico_url' => $db_url]
            ]);

            // Notificar al entrenador
            if ($entrenador_id) {
                crearNotificacion(
                    $pdo, 
                    $entrenador_id, 
                    "Apto Médico Cargado", 
                    "$alumno_nombre subió un nuevo apto médico para su aprobación.", 
                    "/admin/certificados.php"
                );
            }

            $pdo->commit();

            header("Location: /alumno/perfil.php?msg=cert_ok");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al subir certificado a BD: " . $e->getMessage());
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
