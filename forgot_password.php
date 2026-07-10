<?php
session_start();
$page_title = "Recuperar Contraseña";
require_once __DIR__ . '/includes/header.php';

$msg = "";
$type = "info";

if (isset($_SESSION['reset_msg'])) {
    $msg = $_SESSION['reset_msg'];
    $type = $_SESSION['reset_type'] ?? 'info';
    unset($_SESSION['reset_msg'], $_SESSION['reset_type']);
}
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 70px);">
    <div class="card-premium p-4 p-md-5" style="max-width: 450px; width: 100%; border-top: 4px solid var(--trail-orange);">
        <div class="text-center mb-4">
            <h2 class="text-white fw-bold mb-2">Recuperar Contraseña</h2>
            <p class="text-secondary small">Ingresa el correo electrónico asociado a tu cuenta. Te enviaremos un enlace para restablecer tu contraseña.</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $type === 'success' ? 'success alert-success-custom' : ($type === 'error' ? 'danger' : 'info bg-dark border-info text-info'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/actions/auth_reset.php" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="request_reset">
            <div class="mb-4">
                <label for="email" class="form-label form-label-custom">Correo Electrónico</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" id="email" class="form-control form-control-custom" placeholder="tucorreo@ejemplo.com" required>
                </div>
            </div>

            <button type="submit" class="btn btn-trail w-100 py-2 mb-3">
                <i class="fa-solid fa-paper-plane me-2"></i>Enviar Enlace
            </button>
            
            <div class="text-center">
                <a href="/login.php" class="text-secondary text-decoration-none small hover-orange">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver al Login
                </a>
            </div>
        </form>
    </div>
</div>

<style>
    .hover-orange:hover {
        color: var(--trail-orange) !important;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
