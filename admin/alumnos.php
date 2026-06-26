<?php
$page_title = "GestiÃ³n de Alumnos";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol(['admin', 'entrenador']);

// Capturar filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$plan_filter = isset($_GET['plan_filter']) ? trim($_GET['plan_filter']) : '';

// Armar consulta SQL con filtros
$sql = "
    SELECT u.id AS usuario_id, u.nombre, u.apellido, u.email, u.foto_perfil_url,
           ap.id AS alumno_id, ap.dni, ap.telefono, ap.fecha_nacimiento, 
           ap.plan_tipo, ap.nivel, ap.observaciones_medicas, ap.activo, 
           ap.ddjj_aceptada, ap.certificado_medico_estado, ap.certificado_medico_url
    FROM usuarios u
    JOIN alumno_perfil ap ON u.id = ap.usuario_id
    WHERE u.rol = 'alumno'
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR ap.dni LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($plan_filter)) {
    $sql .= " AND ap.plan_tipo = ?";
    $params[] = $plan_filter;
}

$sql .= " ORDER BY u.apellido ASC, u.nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alumnos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar alumnos: " . $e->getMessage());
}

// Obtener planes Ãºnicos para el filtro dropdown
try {
    $stmtPlanes = $pdo->query("SELECT DISTINCT plan_tipo FROM alumno_perfil ORDER BY plan_tipo ASC");
    $planes = $stmtPlanes->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $planes = [];
}

// Manejo de mensajes de error/Ã©xito
$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Todos los campos con (*) son obligatorios."; break;
        case 'email_exists': $error_msg = "El correo electrÃ³nico ingresado ya se encuentra registrado."; break;
        case 'dni_exists': $error_msg = "El DNI ingresado ya se encuentra asignado a otro alumno."; break;
        case 'db': $error_msg = "Error interno de base de datos."; break;
        case 'invalid_delete': $error_msg = "ID de alumno no vÃ¡lido para eliminar."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'create_ok': $success_msg = "Alumno creado con Ã©xito en el sistema."; break;
        case 'edit_ok': $success_msg = "Datos del alumno actualizados correctamente."; break;
        case 'delete_ok': $success_msg = "Alumno eliminado de manera permanente."; break;
    }
}
?>

<div class="container dashboard-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-users text-warning me-2"></i>GestiÃ³n de Alumnos</h2>
            <p class="text-secondary mb-0">Registra nuevos corredores, gestiona su estado de membresÃ­a y edita sus perfiles.</p>
        </div>
        <button class="btn btn-trail mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#createAlumnoModal">
            <i class="fa-solid fa-user-plus me-2"></i>Nuevo Alumno
        </button>
    </div>

    <!-- Alertas -->
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
            <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success-custom alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Buscador y Filtros -->
    <div class="card-premium p-3 mb-4">
        <form method="GET" action="/admin/alumnos.php" class="row g-3">
            <div class="col-md-6 col-lg-5">
                <label for="search" class="form-label form-label-custom small">Buscar por Nombre, Email o DNI</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" id="search" class="form-control form-control-custom" placeholder="Ej: Perez o 38..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-md-4 col-lg-4">
                <label for="plan_filter" class="form-label form-label-custom small">Filtrar por Plan</label>
                <select name="plan_filter" id="plan_filter" class="form-select form-control-custom">
                    <option value="">-- Todos los Planes --</option>
                    <?php foreach ($planes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($plan_filter === $p) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-lg-3 d-flex align-items-end">
                <button type="submit" class="btn btn-trail w-100"><i class="fa-solid fa-filter me-2"></i>Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Listado de Alumnos -->
    <div class="card-premium p-4">
        <?php if (count($alumnos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                    <thead>
                        <tr class="bg-dark text-secondary">
                            <th class="border-secondary py-3">Alumno</th>
                            <th class="border-secondary py-3">DNI / TelÃ©fono</th>
                            <th class="border-secondary py-3">Plan / Nivel</th>
                            <th class="border-secondary py-3 text-center">DDJJ</th>
                            <th class="border-secondary py-3 text-center">MembresÃ­a</th>
                            <th class="border-secondary py-3 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos as $a): ?>
                            <tr>
                                <td class="border-secondary py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($a['foto_perfil_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($a['foto_perfil_url']); ?>" alt="Perfil" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover; border: 2px solid var(--trail-orange);">
                                        <?php else: ?>
                                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; font-family: var(--font-titles);">
                                                <?php echo strtoupper(substr($a['nombre'], 0, 1) . substr($a['apellido'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-white fw-bold"><?php echo htmlspecialchars($a['apellido'] . ", " . $a['nombre']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($a['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-secondary py-3">
                                    <div class="text-white small">DNI: <?php echo htmlspecialchars($a['dni']); ?></div>
                                    <?php 
                                        $edad = '--';
                                        if (!empty($a['fecha_nacimiento'])) {
                                            $edad = date_diff(date_create($a['fecha_nacimiento']), date_create('today'))->y;
                                        }
                                    ?>
                                    <div class="text-white small">Edad: <?php echo $edad; ?> aÃ±os</div>
                                    <div class="text-secondary small">Tel: <?php echo htmlspecialchars($a['telefono']); ?></div>
                                </td>
                                <td class="border-secondary py-3">
                                    <div class="badge bg-warning text-dark text-uppercase small mb-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($a['plan_tipo']); ?></div>
                                    <div class="text-secondary small"><?php echo htmlspecialchars($a['nivel']); ?></div>
                                </td>
                                <td class="border-secondary py-3 text-center">
                                    <?php if ($a['ddjj_aceptada'] == 1): ?>
                                        <span class="badge bg-success text-white" title="Firmado"><i class="fa-solid fa-signature"></i> Firmada</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white" title="Pendiente"><i class="fa-solid fa-clock"></i> Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="border-secondary py-3 text-center">
                                    <?php if ($a['activo'] == 1): ?>
                                        <span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="border-secondary py-3 text-end">
                                    <div class="d-inline-flex gap-2">
                                        <!-- Programar Rutina -->
                                        <a href="/admin/planificador.php?alumno_id=<?php echo $a['alumno_id']; ?>" class="btn btn-outline-warning btn-sm" title="Planificar Rutina">
                                            <i class="fa-solid fa-calendar-alt"></i>
                                        </a>
                                        <!-- Editar -->
                                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#editAlumnoModal<?php echo $a['alumno_id']; ?>" title="Editar">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </button>
                                        <!-- Eliminar -->
                                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAlumnoModal<?php echo $a['alumno_id']; ?>" title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL: Editar Alumno -->
                            <div class="modal fade" id="editAlumnoModal<?php echo $a['alumno_id']; ?>" tabindex="-1" aria-labelledby="editAlumnoLabel<?php echo $a['alumno_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                                        <div class="modal-header border-bottom border-dark">
                                            <h5 class="modal-title text-white fw-bold" id="editAlumnoLabel<?php echo $a['alumno_id']; ?>">
                                                <i class="fa-solid fa-user-pen text-warning me-2"></i>Editar Alumno
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="/actions/admin_alumno_action.php" method="POST">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="usuario_id" value="<?php echo $a['usuario_id']; ?>">
                                            <input type="hidden" name="alumno_id" value="<?php echo $a['alumno_id']; ?>">
                                            
                                            <div class="modal-body text-start">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Nombre *</label>
                                                        <input type="text" name="nombre" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['nombre']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Apellido *</label>
                                                        <input type="text" name="apellido" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['apellido']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">DNI *</label>
                                                        <input type="text" name="dni" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['dni']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Correo ElectrÃ³nico *</label>
                                                        <input type="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['email']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">ContraseÃ±a <small class="text-muted">(dejar vacÃ­o para no cambiar)</small></label>
                                                        <input type="password" name="password" class="form-control form-control-custom" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">TelÃ©fono *</label>
                                                        <input type="text" name="telefono" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['telefono']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Fecha de Nacimiento *</label>
                                                        <input type="date" name="fecha_nacimiento" class="form-control form-control-custom" value="<?php echo htmlspecialchars($a['fecha_nacimiento']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Plan Asignado *</label>
                                                        <select name="plan_tipo" class="form-select form-control-custom" required>
                                                            <option value="Distancia" <?php echo ($a['plan_tipo'] === 'Distancia') ? 'selected' : ''; ?>>Distancia</option>
                                                            <option value="Presencial" <?php echo ($a['plan_tipo'] === 'Presencial') ? 'selected' : ''; ?>>Presencial</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Nivel de Corredor</label>
                                                        <select name="nivel" class="form-select form-control-custom">
                                                            <option value="Principiante" <?php echo ($a['nivel'] === 'Principiante') ? 'selected' : ''; ?>>Principiante</option>
                                                            <option value="Intermedio" <?php echo ($a['nivel'] === 'Intermedio') ? 'selected' : ''; ?>>Intermedio</option>
                                                            <option value="Avanzado" <?php echo ($a['nivel'] === 'Avanzado') ? 'selected' : ''; ?>>Avanzado</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 d-flex align-items-center mt-4">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="activo" id="activoSwitch<?php echo $a['alumno_id']; ?>" <?php echo ($a['activo'] == 1) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label text-white small" for="activoSwitch<?php echo $a['alumno_id']; ?>">Alumno Activo / MembresÃ­a al dÃ­a</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label form-label-custom">Observaciones MÃ©dicas / FÃ­sicas</label>
                                                        <textarea name="observaciones_medicas" class="form-control form-control-custom" rows="3"><?php echo htmlspecialchars($a['observaciones_medicas']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top border-dark">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-trail btn-sm">Guardar Cambios</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- MODAL: Eliminar Alumno -->
                            <div class="modal fade" id="deleteAlumnoModal<?php echo $a['alumno_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                                        <div class="modal-header border-bottom border-dark">
                                            <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Eliminar Alumno</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="/actions/admin_alumno_action.php" method="POST">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="usuario_id" value="<?php echo $a['usuario_id']; ?>">
                                            <div class="modal-body text-start">
                                                <p class="text-white">Â¿EstÃ¡s seguro de que deseas eliminar permanentemente al alumno <strong><?php echo htmlspecialchars($a['nombre'] . " " . $a['apellido']); ?></strong>?</p>
                                                <p class="text-danger small mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i>Esta acciÃ³n no se puede deshacer y borrarÃ¡ todo su historial de rutinas, pagos y certificados mÃ©dicos subidos.</p>
                                            </div>
                                            <div class="modal-footer border-top border-dark">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Eliminar Permanentemente</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-user-slash fa-3x mb-3 text-muted"></i>
                <p class="mb-0">No se encontraron alumnos con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: Crear Alumno -->
<div class="modal fade" id="createAlumnoModal" tabindex="-1" aria-labelledby="createAlumnoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
            <div class="modal-header border-bottom border-dark">
                <h5 class="modal-title text-white fw-bold" id="createAlumnoLabel">
                    <i class="fa-solid fa-user-plus text-warning me-2"></i>Registrar Nuevo Alumno
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/actions/admin_alumno_action.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nombre *</label>
                            <input type="text" name="nombre" class="form-control form-control-custom" placeholder="Ej: Juan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Apellido *</label>
                            <input type="text" name="apellido" class="form-control form-control-custom" placeholder="Ej: Perez" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">DNI *</label>
                            <input type="text" name="dni" class="form-control form-control-custom" placeholder="Ej: 38123456" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Correo ElectrÃ³nico *</label>
                            <input type="email" name="email" class="form-control form-control-custom" placeholder="ejemplo@correo.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">ContraseÃ±a Temporal *</label>
                            <input type="password" name="password" class="form-control form-control-custom" placeholder="MÃ­nimo 6 caracteres" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">TelÃ©fono *</label>
                            <input type="text" name="telefono" class="form-control form-control-custom" placeholder="Ej: +54 9 11..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Fecha de Nacimiento *</label>
                            <input type="date" name="fecha_nacimiento" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Plan Asignado *</label>
                            <select name="plan_tipo" class="form-select form-control-custom" required>
                                <option value="Distancia">Distancia</option>
                                <option value="Presencial" selected>Presencial</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nivel de Corredor</label>
                            <select name="nivel" class="form-select form-control-custom">
                                <option value="Principiante" selected>Principiante</option>
                                <option value="Intermedio">Intermedio</option>
                                <option value="Avanzado">Avanzado</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center mt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" id="activoSwitchNew" checked>
                                <label class="form-check-label text-white small" for="activoSwitchNew">Activar Cuenta de Inmediato</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-custom">Observaciones MÃ©dicas / FÃ­sicas</label>
                            <textarea name="observaciones_medicas" class="form-control form-control-custom" rows="3" placeholder="Lesiones previas, medicaciÃ³n, asma, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail btn-sm">Registrar Alumno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

