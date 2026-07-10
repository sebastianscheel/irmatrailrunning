<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar login y rol
require_rol('alumno');

// check_alumno_status valida el estado de DDJJ y contraseña
check_alumno_status($pdo);

$page_title = "Cambiar Contraseña";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$forced = isset($_GET['forced']) || ($_SESSION['debe_cambiar_password'] == 1);
$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Por favor completa todos los campos requeridos."; break;
        case 'match': $error_msg = "Las nuevas contraseñas no coinciden."; break;
        case 'length': $error_msg = "La nueva contraseña debe tener al menos 6 caracteres."; break;
        case 'incorrect': $error_msg = "La contraseña actual es incorrecta."; break;
        case 'db': $error_msg = "Ocurrió un problema de base de datos."; break;
    }
}
$success_msg = "";
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $success_msg = "¡Tu contraseña ha sido actualizada con éxito!";
}
?>

<div class="container dashboard-container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card-premium p-4 shadow-lg border border-secondary">
                <div class="text-center mb-4">
                    <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary shadow mx-auto mb-3" style="width: 70px; height: 70px;">
                        <i class="fa-solid fa-key fa-2x text-trail"></i>
                    </div>
                    <h3 class="text-white fw-bold mb-1">Actualizar Contraseña</h3>
                    <?php if ($forced): ?>
                        <p class="text-danger small mt-2">
                            <i class="fa-solid fa-triangle-exclamation me-1 animate-pulse"></i> 
                            Por motivos de seguridad, debes cambiar tu contraseña inicial antes de continuar en el portal.
                        </p>
                    <?php else: ?>
                        <p class="text-secondary small mt-2">Introduce tus datos para modificar la contraseña de acceso.</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
                        <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success-custom alert-dismissible fade show text-start" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="/actions/cambiar_password_action.php" method="POST" class="mt-2">
                    <?php if (!$forced): ?>
                        <div class="mb-3">
                            <label for="current_password" class="form-label form-label-custom">Contraseña Actual</label>
                            <input type="password" name="current_password" id="current_password" class="form-control form-control-custom" required autocomplete="current-password">
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="new_password" class="form-label form-label-custom">Nueva Contraseña</label>
                        <input type="password" name="new_password" id="new_password" class="form-control form-control-custom" minlength="6" placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label form-label-custom">Confirmar Nueva Contraseña</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom" minlength="6" required autocomplete="new-password">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-trail py-2 fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar Contraseña</button>
                        <?php if (!$forced): ?>
                            <a href="/alumno/perfil.php" class="btn btn-outline-light"><i class="fa-solid fa-arrow-left me-2"></i>Volver a Perfil</a>
                        <?php else: ?>
                            <a href="/logout.php" class="btn btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-2"></i>Salir</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
