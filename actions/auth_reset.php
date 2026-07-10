<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /login.php");
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'request_reset') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reset_msg'] = "Por favor, ingresa un correo electrónico válido.";
        $_SESSION['reset_type'] = "error";
        header("Location: /forgot_password.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expires, $user['id']]);

        // Construir link y asunto
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $reset_link = "$protocol://$host/reset_password.php?token=$token";

        $to = $email;
        $subject = "Recuperar Contrasena - IB Trailrunning";
        $message = "Hola " . $user['nombre'] . ",\n\n";
        $message .= "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.\n";
        $message .= "Por favor, haz clic en el siguiente enlace (válido por 1 hora):\n\n";
        $message .= $reset_link . "\n\n";
        $message .= "Si no solicitaste este cambio, puedes ignorar este correo.\n";

        $headers = "From: noreply@$host\r\n";
        $headers .= "Reply-To: noreply@$host\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Utilizamos la función mail nativa (puede fallar en local sin SMTP, pero funcionará en prod con sendmail)
        @mail($to, $subject, $message, $headers);
    }
    
    // Por seguridad, siempre mostramos mensaje de éxito para no revelar si el correo existe o no
    $_SESSION['reset_msg'] = "Si el correo está registrado, recibirás un enlace para recuperar tu contraseña en breve.";
    $_SESSION['reset_type'] = "success";
    header("Location: /forgot_password.php");
    exit;

} elseif ($action === 'update_password') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($token) || empty($password) || empty($password_confirm)) {
        die("Datos incompletos.");
    }

    if ($password !== $password_confirm) {
        echo "<script>alert('Las contraseñas no coinciden.'); window.history.back();</script>";
        exit;
    }

    if (strlen($password) < 8) {
        echo "<script>alert('La contraseña debe tener al menos 8 caracteres.'); window.history.back();</script>";
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password_hash = ?, reset_token = NULL, reset_expires = NULL, debe_cambiar_password = 0 WHERE id = ?");
        $stmtUpdate->execute([$hash, $user['id']]);

        echo "<script>alert('Contraseña actualizada con éxito. Ya puedes iniciar sesión.'); window.location.href = '/login.php';</script>";
        exit;
    } else {
        echo "<script>alert('El enlace ha expirado o es inválido.'); window.location.href = '/forgot_password.php';</script>";
        exit;
    }
} else {
    header("Location: /login.php");
    exit;
}
?>
