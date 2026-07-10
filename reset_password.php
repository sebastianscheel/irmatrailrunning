<?php
session_start();
require_once __DIR__ . '/config/db.php';

$page_title = "Nueva Contraseña";
require_once __DIR__ . '/includes/header.php';

$token = $_GET['token'] ?? '';
$is_valid = false;
$msg = "";
$type = "error";

if (empty($token)) {
    $msg = "Enlace inválido o incompleto.";
} else {
    // Validar token en la base de datos
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $is_valid = true;
    } else {
        $msg = "El enlace es inválido o ha expirado. Por favor, solicita uno nuevo.";
    }
}
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 70px);">
    <div class="card-premium p-4 p-md-5" style="max-width: 450px; width: 100%; border-top: 4px solid var(--trail-orange);">
        <div class="text-center mb-4">
            <h2 class="text-white fw-bold mb-2">Nueva Contraseña</h2>
            <p class="text-secondary small">Crea una nueva contraseña para acceder a tu cuenta.</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
            <div class="text-center">
                <a href="/forgot_password.php" class="btn btn-outline-light btn-sm mt-2">Volver a solicitar</a>
            </div>
        <?php endif; ?>

        <?php if ($is_valid): ?>
            <form action="/actions/auth_reset.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label form-label-custom">Nueva Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control form-control-custom" placeholder="Min. 8 caracteres" required minlength="8">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label form-label-custom">Confirmar Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password_confirm" id="password_confirm" class="form-control form-control-custom" placeholder="Repite la contraseña" required minlength="8">
                    </div>
                </div>

                <button type="submit" class="btn btn-trail w-100 py-2 mb-3">
                    <i class="fa-solid fa-save me-2"></i>Guardar y Entrar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
