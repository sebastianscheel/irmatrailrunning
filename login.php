<?php
$page_title = "Iniciar Sesión";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Si ya está logueado, redirigir a su dashboard correspondiente
if (isset($_SESSION['user_rol'])) {
    if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado'])) {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /alumno/dashboard.php");
    }
    exit;
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'db':
            $error_msg = "Error de conexión con la base de datos.";
            break;
        case 'invalid':
            $error_msg = "Credenciales incorrectas. Verifica tu DNI/email o contraseña.";
            break;
        case 'empty':
            $error_msg = "Por favor, completa todos los campos obligatorios.";
            break;
        default:
            $error_msg = "Ocurrió un error inesperado al intentar iniciar sesión.";
    }
}
?>

<div class="container my-5 flex-grow-1 d-flex align-items-center justify-content-center">
    <div class="card-premium p-4 w-100" style="max-width: 450px;">
        <div class="text-center mb-4">
            <i class="fa-solid fa-mountain-sun text-warning fa-3x mb-3"></i>
            <h3 class="text-white fw-bold">Acceso a Panel</h3>
            <p class="text-muted small">Ingresa tus credenciales para ver tu plan y reportes</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary); border-radius: 8px;">
                <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/actions/auth_login.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="identifier" class="form-label form-label-custom">Correo Electrónico o DNI</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="identifier" id="identifier" class="form-control form-control-custom" placeholder="ejemplo@mail.com o 38123456" required>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between mb-1">
                    <label for="password" class="form-label form-label-custom mb-0">Contraseña</label>
                    <a href="/forgot_password.php" class="text-trail small text-decoration-none">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control form-control-custom" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-trail w-100 py-2.5 fw-bold mb-3">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Iniciar Sesión
            </button>
        </form>

        <div class="text-center mt-3 pt-3 border-top border-secondary">
            <p class="text-muted small mb-1">¿No tienes cuenta de alumno?</p>
            <p class="text-secondary small mb-0">
                La cuenta es creada directamente por tu entrenador. Contáctalo para obtener tus datos de acceso.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
