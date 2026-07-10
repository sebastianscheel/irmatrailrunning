<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar login y rol
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /alumno/cambiar_password.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// 1. Validar campos requeridos
if (empty($new_password) || empty($confirm_password)) {
    header("Location: /alumno/cambiar_password.php?error=empty");
    exit;
}

// 2. Validar coincidencia y largo
if ($new_password !== $confirm_password) {
    header("Location: /alumno/cambiar_password.php?error=match");
    exit;
}

if (strlen($new_password) < 6) {
    header("Location: /alumno/cambiar_password.php?error=length");
    exit;
}

try {
    // 3. Consultar estado del usuario
    $stmt = $pdo->prepare("SELECT password_hash, debe_cambiar_password FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: /logout.php");
        exit;
    }

    $is_forced = (int)$user['debe_cambiar_password'] === 1;

    // 4. Si es voluntario, validar contraseña actual
    if (!$is_forced) {
        $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
        if (empty($current_password)) {
            header("Location: /alumno/cambiar_password.php?error=empty");
            exit;
        }

        if (!password_verify($current_password, $user['password_hash'])) {
            header("Location: /alumno/cambiar_password.php?error=incorrect");
            exit;
        }
    }

    // 5. Guardar la nueva contraseña
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $pdo->beginTransaction();
    
    $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 0 WHERE id = ?");
    $stmtUpdate->execute([$new_hash, $user_id]);

    // Registrar auditoría
    require_once __DIR__ . '/../includes/audit_helper.php';
    registrarAuditoria($pdo, [
        'usuario_id' => $user_id,
        'accion' => 'cambiar_password',
        'entidad' => 'usuario',
        'entidad_id' => $user_id,
        'detalle' => "Cambió su propia contraseña de acceso."
    ]);

    $pdo->commit();

    // 6. Actualizar variable de sesión
    $_SESSION['debe_cambiar_password'] = 0;

    // 7. Redireccionar según el flujo
    if ($is_forced) {
        header("Location: /alumno/dashboard.php?msg=ddjj_ok"); // O simplemente dashboard si ya firmó DDJJ
    } else {
        header("Location: /alumno/perfil.php?msg=pw_ok");
    }
    exit;

} catch (PDOException $e) {
    error_log("Error al cambiar contraseña de alumno: " . $e->getMessage());
    header("Location: /alumno/cambiar_password.php?error=db");
    exit;
}
