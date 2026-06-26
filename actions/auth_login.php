<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($identifier) || empty($password)) {
        header("Location: /login.php?error=empty");
        exit;
    }

    try {
        // Buscamos al usuario por email o por DNI (si es alumno)
        $stmt = $pdo->prepare("
            SELECT u.*, ap.activo, ap.ddjj_aceptada 
            FROM usuarios u 
            LEFT JOIN alumno_perfil ap ON u.id = ap.usuario_id 
            WHERE u.email = ? OR (ap.dni = ? AND u.rol = 'alumno')
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Guardamos los datos del usuario en la sesión
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_apellido'] = $user['apellido'];
            $_SESSION['user_rol'] = $user['rol'];

            if (in_array($user['rol'], ['admin', 'entrenador'])) {
                header("Location: /admin/dashboard.php");
            } else {
                $_SESSION['alumno_activo'] = (int)$user['activo'];
                $_SESSION['alumno_ddjj'] = (int)$user['ddjj_aceptada'];
                header("Location: /alumno/dashboard.php");
            }
            exit;
        } else {
            // Contraseña incorrecta o usuario no encontrado
            header("Location: /login.php?error=invalid");
            exit;
        }
    } catch (PDOException $e) {
        // Log del error para depuración
        error_log("Error en el login: " . $e->getMessage());
        header("Location: /login.php?error=db");
        exit;
    }
} else {
    // Si no es POST, redirigir al login
    header("Location: /login.php");
    exit;
}
?>
