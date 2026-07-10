<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Solo admin y entrenadores pueden gestionar alumnos
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Gestión de Alumnos";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$plan_filter = isset($_GET['plan_filter']) ? trim($_GET['plan_filter']) : '';
$entrenador_filter = isset($_GET['entrenador_filter']) ? trim($_GET['entrenador_filter']) : '';
$edad_filter = isset($_GET['edad_filter']) ? trim($_GET['edad_filter']) : '';

// Armar consulta SQL con filtros
$sql = "
    SELECT u.id AS usuario_id, u.nombre, u.apellido, u.email, u.foto_perfil_url, u.dni, u.fecha_creacion,
           ap.id AS alumno_id, ap.telefono, ap.fecha_nacimiento, 
           ap.plan_tipo, ap.nivel, ap.observaciones_medicas, ap.activo, ap.sexo,
           ap.ddjj_aceptada, ap.certificado_medico_estado, ap.certificado_medico_url,
           ap.entrenador_id,
           ent.nombre AS ent_nombre, ent.apellido AS ent_apellido,
           TIMESTAMPDIFF(YEAR, ap.fecha_nacimiento, CURDATE()) AS edad,
           (SELECT c.titulo 
            FROM alumno_carrera ac 
            JOIN carreras c ON ac.carrera_id = c.id 
            WHERE ac.alumno_id = ap.id AND c.fecha >= CURDATE()
            ORDER BY c.fecha ASC LIMIT 1) AS proximo_objetivo,
           (SELECT COUNT(id) FROM pago_registro 
            WHERE alumno_id = ap.id AND mes_pagado = DATE_FORMAT(CURDATE(), '%Y-%m') AND comprobante_url = 'BECA-RENOVADA' AND estado = 'aprobado'
           ) AS es_becado
    FROM usuarios u
    JOIN alumno_perfil ap ON u.id = ap.usuario_id
    LEFT JOIN usuarios ent ON ap.entrenador_id = ent.id
    WHERE u.rol = 'alumno'
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR u.dni LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($plan_filter)) {
    $sql .= " AND ap.plan_tipo = ?";
    $params[] = $plan_filter;
}

if (!empty($entrenador_filter)) {
    $sql .= " AND ap.entrenador_id = ?";
    $params[] = $entrenador_filter;
}

if (!empty($edad_filter)) {
    if ($edad_filter == 'u30') {
        $sql .= " AND TIMESTAMPDIFF(YEAR, ap.fecha_nacimiento, CURDATE()) < 30";
    } elseif ($edad_filter == '30-40') {
        $sql .= " AND TIMESTAMPDIFF(YEAR, ap.fecha_nacimiento, CURDATE()) BETWEEN 30 AND 40";
    } elseif ($edad_filter == '40-50') {
        $sql .= " AND TIMESTAMPDIFF(YEAR, ap.fecha_nacimiento, CURDATE()) BETWEEN 41 AND 50";
    } elseif ($edad_filter == 'o50') {
        $sql .= " AND TIMESTAMPDIFF(YEAR, ap.fecha_nacimiento, CURDATE()) > 50";
    }
}

$sql .= " ORDER BY u.apellido ASC, u.nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alumnos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar alumnos: " . $e->getMessage());
}

// Obtener planes únicos
try {
    $stmtPlanes = $pdo->query("SELECT DISTINCT plan_tipo FROM alumno_perfil ORDER BY plan_tipo ASC");
    $planes = $stmtPlanes->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $planes = []; }

// Obtener entrenadores para selectores
try {
    $stmtEntrenadores = $pdo->query("SELECT id, nombre, apellido FROM usuarios WHERE rol IN ('entrenador_total', 'entrenador_limitado') ORDER BY apellido ASC");
    $lista_entrenadores = $stmtEntrenadores->fetchAll();
} catch (PDOException $e) { $lista_entrenadores = []; }

// Mensajes
$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Todos los campos con (*) son obligatorios."; break;
        case 'email_exists': $error_msg = "El correo electrónico ya se encuentra registrado."; break;
        case 'dni_exists': $error_msg = "El DNI ingresado ya está asignado a otro alumno."; break;
        case 'unauthorized': $error_msg = "No tienes permisos para realizar esta acción."; break;
        case 'db': $error_msg = "Error interno de base de datos."; break;
    }
}
$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'create_ok': $success_msg = "Alumno creado con éxito."; break;
        case 'edit_ok': $success_msg = "Datos del alumno actualizados."; break;
        case 'delete_ok': $success_msg = "Alumno eliminado."; break;
        case 'reset_ok': $success_msg = "Contraseña restablecida con éxito a su DNI."; break;
        case 'beca_ok': $success_msg = "Alumno becado exitosamente por el mes actual."; break;
        case 'import_ok': 
            $imp = isset($_GET['importados']) ? $_GET['importados'] : 0;
            $err = isset($_GET['errores']) ? $_GET['errores'] : 0;
            $success_msg = "Importación finalizada: $imp alumnos importados, $err errores/duplicados."; 
            break;
    }
}
?>

<div class="container dashboard-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-users text-warning me-2"></i>Gestión de Alumnos</h2>
            <p class="text-secondary mb-0">Registra y administra a los corredores y sus planes de entrenamiento.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0 align-items-center">
            <?php if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total'])): ?>
                <button class="btn btn-success btn-sm px-3" onclick="exportarExcel()">
                    <i class="fa-solid fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-danger btn-sm px-3" onclick="exportarPDF()">
                    <i class="fa-solid fa-file-pdf me-1"></i> PDF
                </button>
                <button class="btn btn-info btn-sm px-3 text-dark fw-bold" onclick="exportarCSV()">
                    <i class="fa-solid fa-file-csv me-1"></i> CSV
                </button>
            <?php endif; ?>
            <?php if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])): ?>
                <button class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                    <i class="fa-solid fa-upload me-2"></i>Importar CSV
                </button>
                <button class="btn btn-trail btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createAlumnoModal">
                    <i class="fa-solid fa-user-plus me-2"></i>Nuevo Alumno
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2 text-success"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Buscador y Filtros -->
    <div class="card-premium p-3 mb-4">
        <form method="GET" action="alumnos.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <input type="text" name="search" class="form-control form-control-custom w-100" placeholder="Buscar por Nombre, Email o DNI..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-12 col-md-2">
                <select name="plan_filter" class="form-select form-control-custom w-100">
                    <option value="">Todos los Planes</option>
                    <?php foreach ($planes as $plan): ?>
                        <option value="<?php echo htmlspecialchars($plan); ?>" <?php if ($plan_filter === $plan) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($plan); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="entrenador_filter" class="form-select form-control-custom w-100">
                    <option value="">Cualquier Entrenador</option>
                    <option value="unassigned" <?php if ($entrenador_filter === 'unassigned') echo 'selected'; ?>>-- Sin Asignar --</option>
                    <?php foreach ($lista_entrenadores as $ent): ?>
                        <option value="<?php echo $ent['id']; ?>" <?php if ($entrenador_filter == $ent['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="edad_filter" class="form-select form-control-custom w-100">
                    <option value="">Todas las edades</option>
                    <option value="u30" <?php if ($edad_filter === 'u30') echo 'selected'; ?>>Menor a 30</option>
                    <option value="30-40" <?php if ($edad_filter === '30-40') echo 'selected'; ?>>30 a 40 años</option>
                    <option value="40-50" <?php if ($edad_filter === '40-50') echo 'selected'; ?>>41 a 50 años</option>
                    <option value="o50" <?php if ($edad_filter === 'o50') echo 'selected'; ?>>Mayor a 50</option>
                </select>
            </div>
            <div class="col-12 col-md-2 text-end">
                <button type="submit" class="btn btn-warning text-dark w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Tabla de Alumnos -->
    <div class="card-premium mb-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 250px;">Alumno</th>
                        <th>Edad</th>
                        <th>Plan & Nivel</th>
                        <th>Entrenador Asignado</th>
                        <th>Próximo Objetivo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($alumnos) > 0): ?>
                        <?php foreach ($alumnos as $alumno): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($alumno['foto_perfil_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($alumno['foto_perfil_url']); ?>" alt="Foto" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--trail-orange);">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px; border: 2px solid var(--trail-orange);">
                                                <i class="fa-solid fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-white"><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></h6>
                                            <small class="text-secondary d-block"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($alumno['email']); ?></small>
                                            <small class="text-muted" style="font-size: 0.7rem;"><i class="fa-solid fa-calendar-plus me-1"></i>Alta: <?php echo date('d/m/Y', strtotime($alumno['fecha_creacion'])); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $alumno['edad'] ? $alumno['edad'] . ' años' : '-'; ?></td>
                                <td>
                                    <span class="d-block text-white mb-1"><i class="fa-solid fa-dumbbell text-trail me-1"></i><?php echo htmlspecialchars($alumno['plan_tipo']); ?></span>
                                    <span class="badge bg-dark border border-secondary text-secondary"><?php echo htmlspecialchars($alumno['nivel']); ?></span>
                                </td>
                                <td>
                                    <?php if ($alumno['ent_nombre']): ?>
                                        <span class="text-info"><i class="fa-solid fa-user-tie me-1"></i><?php echo htmlspecialchars($alumno['ent_nombre'] . ' ' . $alumno['ent_apellido']); ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($alumno['proximo_objetivo']): ?>
                                        <span class="badge bg-trail text-dark"><i class="fa-solid fa-mountain-sun me-1"></i><?php echo htmlspecialchars($alumno['proximo_objetivo']); ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary small">Ninguno</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div id="status_badge_<?php echo $alumno['alumno_id']; ?>">
                                        <?php if ($alumno['es_becado'] > 0): ?>
                                            <span class="badge bg-info bg-opacity-25 text-info border border-info"><i class="fa-solid fa-gift me-1"></i>Becado</span>
                                        <?php elseif ($alumno['activo'] == 1): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="fa-solid fa-check-circle me-1"></i>Activo</span>
                                        <?php elseif ($alumno['activo'] == 2): ?>
                                            <span class="badge bg-danger bg-opacity-25 text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Falta de pago</span>
                                        <?php elseif ($alumno['activo'] == 3): ?>
                                            <span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><i class="fa-solid fa-clock me-1"></i>Activo, falta pago</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary"><i class="fa-solid fa-times-circle me-1"></i>Inactivo</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-1">
                                        <?php if ($alumno['ddjj_aceptada']): ?>
                                            <span class="badge bg-secondary" title="DDJJ Aceptada"><i class="fa-solid fa-file-signature text-success"></i> DDJJ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="DDJJ Pendiente"><i class="fa-solid fa-file-signature text-danger"></i> DDJJ</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($alumno['certificado_medico_estado'] === 'Aprobado'): ?>
                                            <span class="badge bg-secondary" title="Certificado Aprobado"><i class="fa-solid fa-notes-medical text-success"></i> Méd.</span>
                                        <?php elseif ($alumno['certificado_medico_estado'] === 'Rechazado'): ?>
                                            <span class="badge bg-secondary" title="Certificado Rechazado"><i class="fa-solid fa-notes-medical text-danger"></i> Méd.</span>
                                        <?php elseif (!empty($alumno['certificado_medico_url'])): ?>
                                            <span class="badge bg-secondary" title="Certificado Pendiente"><i class="fa-solid fa-notes-medical text-warning"></i> Méd.</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Falta Certificado Médico"><i class="fa-solid fa-notes-medical text-danger"></i> Méd.</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <!-- Interruptor rápido Activo/Inactivo -->
                                    <div class="form-check form-switch d-inline-block me-3 align-middle" style="margin-bottom: 0;">
                                        <input class="form-check-input check-activo-toggle" type="checkbox" role="switch" 
                                               id="switch_activo_<?php echo $alumno['alumno_id']; ?>"
                                               data-alumno-id="<?php echo $alumno['alumno_id']; ?>" 
                                               data-current-state="<?php echo $alumno['activo']; ?>"
                                               <?php echo ($alumno['activo'] == 1 || $alumno['activo'] == 3) ? 'checked' : ''; ?>
                                               title="Activar/Desactivar Alumno">
                                    </div>
                                    <div class="d-inline-flex gap-2">
                                        <?php if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])): ?>
                                        <button class="btn btn-outline-info px-2 py-1" data-bs-toggle="modal" data-bs-target="#becarAlumnoModal_<?php echo $alumno['alumno_id']; ?>" title="Becar Mes Actual">
                                            <i class="fa-solid fa-gift"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline-warning px-2 py-1" data-bs-toggle="modal" data-bs-target="#resetPasswordModal_<?php echo $alumno['usuario_id']; ?>" title="Restablecer Contraseña">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        <?php if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])): ?>
                                        <button class="btn btn-outline-light px-2 py-1" data-bs-toggle="modal" data-bs-target="#editAlumnoModal_<?php echo $alumno['alumno_id']; ?>" title="Editar Perfil">
                                            <i class="fa-solid fa-edit text-dark"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (in_array($_SESSION['user_rol'], ['admin', 'entrenador_total'])): ?>
                                        <button class="btn btn-outline-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#deleteAlumnoModal_<?php echo $alumno['alumno_id']; ?>" title="Eliminar Alumno">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-secondary mb-3">
                                    <i class="fa-solid fa-users-slash fa-3x"></i>
                                </div>
                                <h5>No se encontraron alumnos</h5>
                                <p class="small">Prueba cambiando los filtros o registra un alumno nuevo.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (count($alumnos) > 0): ?>
    <?php foreach ($alumnos as $alumno): ?>
        <!-- Modal Editar Alumno -->
        <div class="modal fade" id="editAlumnoModal_<?php echo $alumno['alumno_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content modal-custom">
                    <form action="/actions/admin_alumno_action.php" method="POST" autocomplete="off">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="usuario_id" value="<?php echo $alumno['usuario_id']; ?>">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno['alumno_id']; ?>">
                        
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-white"><i class="fa-solid fa-user-pen me-2 text-warning"></i>Editar Alumno</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-start">
                            <!-- Datos Personales -->
                            <h6 class="text-trail mb-3 border-bottom border-secondary pb-2">Datos Personales</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Nombre *</label>
                                    <input type="text" class="form-control form-control-custom" name="nombre" value="<?php echo htmlspecialchars($alumno['nombre']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Apellido *</label>
                                    <input type="text" class="form-control form-control-custom" name="apellido" value="<?php echo htmlspecialchars($alumno['apellido']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Correo Electrónico *</label>
                                    <input type="email" class="form-control form-control-custom" name="email" value="<?php echo htmlspecialchars($alumno['email']); ?>" autocomplete="new-password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Teléfono *</label>
                                    <input type="text" class="form-control form-control-custom" name="telefono" value="<?php echo htmlspecialchars($alumno['telefono']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">DNI / Pasaporte *</label>
                                    <input type="text" class="form-control form-control-custom" name="dni" value="<?php echo htmlspecialchars($alumno['dni']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Fecha de Nacimiento *</label>
                                    <input type="date" class="form-control form-control-custom" name="fecha_nacimiento" value="<?php echo htmlspecialchars($alumno['fecha_nacimiento']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Sexo *</label>
                                    <select class="form-select form-control-custom" name="sexo" required>
                                        <option value="M" <?php echo ($alumno['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo ($alumno['sexo'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Nueva Contraseña (Opcional)</label>
                                    <input type="password" class="form-control form-control-custom" name="password" placeholder="Dejar en blanco para mantener actual">
                                </div>
                            </div>

                            <!-- Perfil Deportivo -->
                            <h6 class="text-trail mb-3 border-bottom border-secondary pb-2">Perfil Deportivo</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Entrenador Asignado</label>
                                    <select class="form-select form-control-custom" name="entrenador_id">
                                        <option value="">Sin Asignar</option>
                                        <?php foreach ($lista_entrenadores as $ent): ?>
                                            <option value="<?php echo $ent['id']; ?>" <?php echo ($alumno['entrenador_id'] == $ent['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Tipo de Plan *</label>
                                    <select class="form-select form-control-custom" name="plan_tipo" required>
                                        <option value="A Distancia" <?php echo ($alumno['plan_tipo'] === 'A Distancia') ? 'selected' : ''; ?>>A Distancia</option>
                                        <option value="Presencial" <?php echo ($alumno['plan_tipo'] === 'Presencial') ? 'selected' : ''; ?>>Presencial</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Nivel *</label>
                                    <select class="form-select form-control-custom" name="nivel" required>
                                        <option value="Principiante" <?php echo ($alumno['nivel'] === 'Principiante') ? 'selected' : ''; ?>>Principiante</option>
                                        <option value="Intermedio" <?php echo ($alumno['nivel'] === 'Intermedio') ? 'selected' : ''; ?>>Intermedio</option>
                                        <option value="Avanzado" <?php echo ($alumno['nivel'] === 'Avanzado') ? 'selected' : ''; ?>>Avanzado</option>
                                        <option value="Elite" <?php echo ($alumno['nivel'] === 'Elite') ? 'selected' : ''; ?>>Elite</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label form-label-custom">Estado Cuenta</label>
                                    <?php 
                                    $disable_status = !in_array($_SESSION['user_rol'], ['admin', 'entrenador_total']) ? 'disabled' : '';
                                    ?>
                                    <select class="form-select form-control-custom" name="activo" <?php echo $disable_status; ?>>
                                        <option value="1" <?php echo ($alumno['activo'] == 1) ? 'selected' : ''; ?>>Activo</option>
                                        <option value="0" <?php echo ($alumno['activo'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                                        <option value="2" <?php echo ($alumno['activo'] == 2) ? 'selected' : ''; ?>>Inactivo / Falta de pago</option>
                                        <option value="3" <?php echo ($alumno['activo'] == 3) ? 'selected' : ''; ?>>Activo / Falta de pago</option>
                                    </select>
                                    <?php if (!empty($disable_status)): ?>
                                        <input type="hidden" name="activo" value="<?php echo $alumno['activo']; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label form-label-custom">Observaciones Médicas</label>
                                    <textarea class="form-control form-control-custom" name="observaciones_medicas" rows="3"><?php echo htmlspecialchars($alumno['observaciones_medicas'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-trail">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar Alumno -->
        <div class="modal fade" id="deleteAlumnoModal_<?php echo $alumno['alumno_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content modal-custom">
                    <form action="/actions/admin_alumno_action.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="usuario_id" value="<?php echo $alumno['usuario_id']; ?>">
                        
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar Alumno</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-start text-white">
                            <p>¿Estás seguro que deseas eliminar a <strong><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></strong>?</p>
                            <p class="small text-secondary mb-0">Esta acción eliminará su cuenta, historial de rutinas, pagos y certificados asociados. <strong>No se puede deshacer.</strong></p>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Sí, Eliminar Permanentemente</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Restablecer Contraseña Alumno -->
        <div class="modal fade" id="resetPasswordModal_<?php echo $alumno['usuario_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content modal-custom">
                    <form action="/actions/admin_alumno_action.php" method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="usuario_id" value="<?php echo $alumno['usuario_id']; ?>">
                        
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-white"><i class="fa-solid fa-key me-2 text-warning"></i>Restablecer Contraseña</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-start text-white">
                             <p>¿Estás seguro que deseas restablecer la contraseña de <strong><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></strong>?</p>
                             <p class="small text-secondary mb-0">La nueva contraseña del alumno será restablecida a su valor por defecto: <strong>su DNI</strong>.</p>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning text-dark fw-bold">Sí, Restablecer Contraseña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Becar Alumno -->
        <div class="modal fade" id="becarAlumnoModal_<?php echo $alumno['alumno_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content modal-custom">
                    <form action="/actions/admin_alumno_action.php" method="POST">
                        <input type="hidden" name="action" value="becar">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno['alumno_id']; ?>">
                        
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-info"><i class="fa-solid fa-gift me-2"></i>Liberar Alumno (Beca)</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-start text-white">
                             <p>¿Deseas otorgar una beca a <strong><?php echo htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']); ?></strong> para el mes actual?</p>
                             <p class="small text-secondary mb-0">Esta acción registrará un pago automático de $0, permitiendo que el alumno mantenga su cuenta activa. La beca debe renovarse manualmente cada mes.</p>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-info text-dark fw-bold">Sí, Becar Mes Actual</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal Importar CSV -->
<div class="modal fade" id="importCsvModal" tabindex="-1" aria-labelledby="importCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="importCsvModalLabel">Importar Alumnos desde CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/actions/admin_alumno_action.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_csv">
                    
                    <div class="alert alert-info bg-dark text-white border-info mb-4">
                        <i class="fa-solid fa-circle-info me-2 text-info"></i>
                        Descarga la plantilla, rellénala (sin alterar las cabeceras) y súbela. La contraseña por defecto será el DNI de cada alumno.
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="/actions/admin_alumno_action.php?action=download_template" class="btn btn-outline-info btn-sm">
                            <i class="fa-solid fa-download me-1"></i> Descargar Plantilla CSV
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label form-label-custom">Archivo CSV *</label>
                        <input type="file" class="form-control form-control-custom" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Importar Alumnos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nuevo Alumno -->
<div class="modal fade" id="createAlumnoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-custom">
            <form action="/actions/admin_alumno_action.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-user-plus me-2 text-trail"></i>Nuevo Alumno</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                     <div class="alert alert-info bg-dark border-info text-info small">
                         <i class="fa-solid fa-circle-info me-2"></i> La contraseña inicial del alumno será su número de DNI.
                     </div>
                    
                    <h6 class="text-trail mb-3 border-bottom border-secondary pb-2">Datos Personales</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nombre *</label>
                            <input type="text" class="form-control form-control-custom" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Apellido *</label>
                            <input type="text" class="form-control form-control-custom" name="apellido" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Correo Electrónico *</label>
                            <input type="email" class="form-control form-control-custom" name="email" autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Teléfono *</label>
                            <input type="text" class="form-control form-control-custom" name="telefono" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">DNI / Pasaporte *</label>
                            <input type="text" class="form-control form-control-custom" name="dni" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Fecha de Nacimiento *</label>
                            <input type="date" class="form-control form-control-custom" name="fecha_nacimiento" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Sexo *</label>
                            <select class="form-select form-control-custom" name="sexo" required>
                                <option value="" disabled selected>Selecciona sexo...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-trail mb-3 border-bottom border-secondary pb-2">Perfil Deportivo</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Entrenador Asignado</label>
                            <select class="form-select form-control-custom" name="entrenador_id">
                                <option value="">Sin Asignar</option>
                                <?php foreach ($lista_entrenadores as $ent): ?>
                                    <option value="<?php echo $ent['id']; ?>">
                                        <?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Tipo de Plan *</label>
                            <select class="form-select form-control-custom" name="plan_tipo" required>
                                <option value="" disabled selected>Selecciona un plan...</option>
                                <option value="A Distancia">A Distancia</option>
                                <option value="Presencial">Presencial</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nivel *</label>
                            <select class="form-select form-control-custom" name="nivel" required>
                                <option value="Principiante" selected>Principiante</option>
                                <option value="Intermedio">Intermedio</option>
                                <option value="Avanzado">Avanzado</option>
                                <option value="Elite">Elite</option>
                            </select>
                        </div>
                         <div class="col-md-6">
                             <label class="form-label form-label-custom">Estado Inicial</label>
                             <?php 
                             $disable_status_create = !in_array($_SESSION['user_rol'], ['admin', 'entrenador_total']) ? 'disabled' : '';
                             ?>
                             <select class="form-select form-control-custom" name="activo" <?php echo $disable_status_create; ?>>
                                 <option value="1" selected>Activo</option>
                                 <option value="0">Inactivo</option>
                                 <option value="2">Inactivo / Falta de pago</option>
                                 <option value="3">Activo / Falta de pago</option>
                             </select>
                             <?php if (!empty($disable_status_create)): ?>
                                 <input type="hidden" name="activo" value="1">
                             <?php endif; ?>
                         </div>
                        <div class="col-12">
                            <label class="form-label form-label-custom">Observaciones Médicas (Opcional)</label>
                            <textarea class="form-control form-control-custom" name="observaciones_medicas" rows="3" placeholder="Alergias, lesiones previas, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Crear Alumno</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CDN Dependencias para Exportar -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
// Lista de alumnos inyectada para exportación
const alumnosExport = <?php echo json_encode($alumnos); ?>;

function exportarExcel() {
    if (!alumnosExport || alumnosExport.length === 0) {
        alert("No hay alumnos para exportar.");
        return;
    }
    // Preparar datos para SheetJS
    const data = alumnosExport.map(al => ({
        "Nombre": al.nombre,
        "Apellido": al.apellido,
        "DNI": al.dni,
        "Email": al.email,
        "Teléfono": al.telefono,
        "Edad": al.edad ? al.edad + " años" : '-',
        "Sexo": al.sexo === 'M' ? 'Masculino (M)' : (al.sexo === 'F' ? 'Femenino (F)' : '-'),
        "Plan": al.plan_tipo,
        "Nivel": al.nivel,
        "Entrenador": al.ent_nombre ? (al.ent_nombre + ' ' + al.ent_apellido) : 'Sin asignar',
        "Estado": al.activo == 1 ? 'Activo' : (al.activo == 3 ? 'Activo / Falta de pago' : (al.activo == 2 ? 'Falta de pago' : 'Inactivo'))
    }));

    const worksheet = XLSX.utils.json_to_sheet(data);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Alumnos");
    
    // Auto-ajustar ancho de columnas
    const max_widths = [];
    data.forEach(row => {
        Object.keys(row).forEach((key, col_idx) => {
            const val = row[key] ? row[key].toString() : '';
            max_widths[col_idx] = Math.max(max_widths[col_idx] || 0, val.length, key.length);
        });
    });
    worksheet['!cols'] = max_widths.map(w => ({ wch: w + 2 }));

    XLSX.writeFile(workbook, "padron_alumnos_ib_trailrunning.xlsx");
}

function exportarPDF() {
    if (!alumnosExport || alumnosExport.length === 0) {
        alert("No hay alumnos para exportar.");
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape'); // Horizontal

    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.setTextColor(217, 125, 84); // Trail Orange
    doc.text("Padrón de Alumnos - IB Trailrunning", 14, 20);
    
    doc.setFont("helvetica", "normal");
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text("Fecha de generación: " + new Date().toLocaleDateString(), 14, 26);

    const headers = [["Nombre", "Apellido", "DNI", "Email", "Teléfono", "Sexo", "Plan", "Nivel", "Entrenador", "Estado"]];
    const data = alumnosExport.map(al => [
        al.nombre,
        al.apellido,
        al.dni,
        al.email,
        al.telefono,
        al.sexo || '-',
        al.plan_tipo,
        al.nivel,
        al.ent_nombre ? (al.ent_nombre + ' ' + al.ent_apellido) : 'Sin asignar',
        al.activo == 1 ? 'Activo' : (al.activo == 3 ? 'Activo / Falta de pago' : (al.activo == 2 ? 'Falta de Pago' : 'Inactivo'))
    ]);

    doc.autoTable({
        startY: 32,
        head: headers,
        body: data,
        theme: 'striped',
        headStyles: { fillColor: [217, 125, 84], textColor: [255, 255, 255] }, // Cabecera naranja
        styles: { fontSize: 8, cellPadding: 2 }
    });

    doc.save("padron_alumnos_ib_trailrunning.pdf");
}

function exportarCSV() {
    if (!alumnosExport || alumnosExport.length === 0) {
        alert("No hay alumnos para exportar.");
        return;
    }
    const headers = ["Nombre", "Apellido", "DNI", "Email", "Telefono", "Edad", "Sexo", "Plan", "Nivel", "Entrenador", "Estado"];
    let csvContent = "\uFEFF"; // BOM UTF-8
    csvContent += headers.join(";") + "\r\n";

    alumnosExport.forEach(al => {
        const row = [
            al.nombre,
            al.apellido,
            al.dni,
            al.email,
            al.telefono,
            al.edad || '',
            al.sexo || '',
            al.plan_tipo,
            al.nivel,
            al.ent_nombre ? (al.ent_nombre + ' ' + al.ent_apellido) : 'Sin asignar',
            al.activo == 1 ? 'Activo' : (al.activo == 3 ? 'Activo / Falta de pago' : (al.activo == 2 ? 'Falta de pago' : 'Inactivo'))
        ].map(val => {
            let text = val ? val.toString() : '';
            text = text.replace(/"/g, '""');
            if (text.includes(";") || text.includes("\n") || text.includes("\r") || text.includes('"')) {
                return `"${text}"`;
            }
            return text;
        });
        csvContent += row.join(";") + "\r\n";
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "padron_alumnos_ib_trailrunning.csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Manejador AJAX para los interruptores rápidos de estado activo/inactivo
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll('.check-activo-toggle');
    checkboxes.forEach(chk => {
        chk.addEventListener('change', function() {
            const alumnoId = this.getAttribute('data-alumno-id');
            const isChecked = this.checked ? 1 : 0;
            const statusContainer = document.getElementById('status_badge_' + alumnoId);

            this.disabled = true;

            const formData = new FormData();
            formData.append('action', 'toggle_active_ajax');
            formData.append('alumno_id', alumnoId);
            formData.append('checked', isChecked);

            fetch('/actions/admin_alumno_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (statusContainer) {
                        statusContainer.innerHTML = data.badge_html;
                    }
                    this.setAttribute('data-current-state', data.new_state);
                } else {
                    alert('Error: ' + data.error);
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Ocurrió un error al cambiar el estado de actividad.');
                this.checked = !this.checked;
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
