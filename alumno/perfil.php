<?php
$page_title = "Mi Perfil";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar login y rol
require_rol('alumno');

// Consultar datos de usuario y perfil
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, u.email, ap.* 
    FROM usuarios u
    JOIN alumno_perfil ap ON u.id = ap.usuario_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: /logout.php");
    exit;
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Por favor completa todos los campos requeridos."; break;
        case 'upload_err': $error_msg = "Ocurrió un error al cargar el archivo. Inténtalo de nuevo."; break;
        case 'invalid_type': $error_msg = "Formato no permitido. Solo se aceptan PDFs e imágenes (JPG, PNG)."; break;
        case 'invalid_size': $error_msg = "El archivo excede el tamaño máximo permitido de 5MB."; break;
        case 'move_err': $error_msg = "No se pudo guardar el archivo en el servidor. Verifica permisos."; break;
        case 'db': $error_msg = "Ocurrió un problema de base de datos."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'cert_ok': $success_msg = "¡Certificado médico subido con éxito! Pendiente de aprobación por tu entrenador."; break;
        case 'foto_ok': $success_msg = "¡Foto de perfil actualizada correctamente!"; break;
    }
}
?>

<div class="container dashboard-container">
    <div class="row">
        <!-- Columna de datos personales -->
        <div class="col-lg-6 mb-4">
            <div class="card-premium p-4">
                <h4 class="text-white mb-3 fw-bold"><i class="fa-solid fa-id-card text-warning me-2"></i>Mis Datos Personales</h4>
                <p class="text-secondary small mb-4">Manten tus datos de contacto actualizados para que el entrenador pueda comunicarse contigo.</p>

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

                <form action="/actions/alumno_update.php" method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nombre</label>
                            <input type="text" class="form-control form-control-custom bg-dark text-muted" value="<?php echo htmlspecialchars($user['nombre']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Apellido</label>
                            <input type="text" class="form-control form-control-custom bg-dark text-muted" value="<?php echo htmlspecialchars($user['apellido']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">DNI</label>
                            <input type="text" class="form-control form-control-custom bg-dark text-muted" value="<?php echo htmlspecialchars($user['dni']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Correo Electrónico</label>
                            <input type="email" class="form-control form-control-custom bg-dark text-muted" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telefono" class="form-label form-label-custom">Teléfono de Contacto</label>
                            <input type="text" name="telefono" id="telefono" class="form-control form-control-custom" value="<?php echo htmlspecialchars($user['telefono']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="fecha_nacimiento" class="form-label form-label-custom">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control form-control-custom" value="<?php echo htmlspecialchars($user['fecha_nacimiento']); ?>" required>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-trail w-100"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar Cambios</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Columna Secundaria (Foto y Certificado) -->
        <div class="col-lg-6 mb-4">
            
            <!-- Foto de Perfil -->
            <div class="card-premium p-4 mb-4">
                <h4 class="text-white mb-3 fw-bold"><i class="fa-solid fa-camera-retro text-warning me-2"></i>Foto de Perfil</h4>
                
                <div class="d-flex align-items-center mb-4">
                    <div class="me-4 position-relative">
                        <?php if (!empty($user['foto_perfil_url'])): ?>
                            <img src="<?php echo htmlspecialchars($user['foto_perfil_url']); ?>" alt="Foto de perfil" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid var(--trail-orange);">
                        <?php else: ?>
                            <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 100px; height: 100px; border: 3px solid var(--border-color);">
                                <i class="fa-solid fa-user fa-3x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-secondary small mb-2">Sube una foto tuya para que el entrenador te identifique más fácilmente.</p>
                        <form action="/actions/subir_foto.php" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                            <input class="form-control form-control-sm form-control-custom" type="file" name="foto_perfil" accept="image/png, image/jpeg, image/jpg" required>
                            <button type="submit" class="btn btn-trail btn-sm px-3"><i class="fa-solid fa-upload"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Certificado Médico -->
            <div class="card-premium p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <h4 class="text-white mb-3 fw-bold"><i class="fa-solid fa-file-medical text-warning me-2"></i>Certificado Médico</h4>
                    <p class="text-secondary small mb-4">El apto médico es obligatorio y de validez anual para poder realizar los entrenamientos presenciales y a distancia de forma segura.</p>

                    <!-- Estado del Certificado -->
                    <div class="p-3 rounded mb-4 text-start <?php 
                        if (empty($user['certificado_medico_url'])) echo 'bg-dark border border-secondary';
                        elseif ($user['certificado_medico_estado'] === 'aprobado') echo 'alert-success-custom';
                        elseif ($user['certificado_medico_estado'] === 'pendiente') echo 'alert-trail';
                        elseif ($user['certificado_medico_estado'] === 'rechazado') echo 'bg-danger-glow border border-danger text-white';
                    ?>">
                        <small class="d-block text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">Estado de Documentación:</small>
                        
                        <?php if (empty($user['certificado_medico_url'])): ?>
                            <span class="text-warning fw-bold small"><i class="fa-solid fa-file-circle-exclamation me-1"></i>No presentado</span>
                            <p class="text-secondary small mt-2 mb-0">Por favor, sube una foto o PDF de tu certificado firmado por tu médico de cabecera.</p>
                        
                        <?php elseif ($user['certificado_medico_estado'] === 'aprobado'): ?>
                            <span class="text-success fw-bold small"><i class="fa-solid fa-circle-check me-1"></i>Aprobado / Al día</span>
                            <p class="text-secondary small mt-2 mb-0">Tu certificado ha sido verificado. Puedes ver el documento actual haciendo click <a href="<?php echo htmlspecialchars($user['certificado_medico_url']); ?>" target="_blank" class="text-warning">aquí</a>.</p>
                        
                        <?php elseif ($user['certificado_medico_estado'] === 'pendiente'): ?>
                            <span class="text-warning fw-bold small"><i class="fa-solid fa-clock me-1"></i>Pendiente de Revisión</span>
                            <p class="text-secondary small mt-2 mb-0">Subiste tu documento correctamente. El entrenador lo revisará a la brevedad. Archivo: <a href="<?php echo htmlspecialchars($user['certificado_medico_url']); ?>" target="_blank" class="text-warning">Ver Archivo</a>.</p>
                        
                        <?php elseif ($user['certificado_medico_estado'] === 'rechazado'): ?>
                            <span class="text-danger fw-bold small"><i class="fa-solid fa-circle-xmark me-1"></i>Rechazado</span>
                            <p class="text-secondary small mt-2 mb-1">Tu certificado médico ha sido rechazado.</p>
                            <?php if (!empty($user['certificado_medico_comentario'])): ?>
                                <div class="p-2 bg-dark rounded border border-danger text-danger small mt-2">
                                    <strong>Motivo de rechazo:</strong> "<?php echo htmlspecialchars($user['certificado_medico_comentario']); ?>"
                                </div>
                            <?php endif; ?>
                            <p class="text-secondary small mt-2 mb-0">Sube uno nuevo con las correcciones indicadas.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario de subida de archivo -->
                <form action="/actions/subir_certificado.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="certificado" class="form-label form-label-custom">Seleccionar Certificado Médico (PDF, PNG, JPG - máx 5MB)</label>
                        <input class="form-control form-control-custom" type="file" name="certificado" id="certificado" accept="application/pdf, image/png, image/jpeg, image/jpg" required>
                    </div>
                    <button type="submit" class="btn btn-trail w-100 py-2"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Subir Certificado</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
