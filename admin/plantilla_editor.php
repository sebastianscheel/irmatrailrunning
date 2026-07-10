<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea entrenador o admin
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$entrenador_id = $_SESSION['user_id'];
$plantilla_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$plantilla = null;
$rutinas = [];

if ($plantilla_id > 0) {
    try {
        $user_rol = $_SESSION['user_rol'];
        if (in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            $stmtS = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
            $stmtS->execute([$plantilla_id]);
        } else {
            $stmtS = $pdo->prepare("SELECT * FROM plantillas WHERE id = ? AND entrenador_id = ?");
            $stmtS->execute([$plantilla_id, $entrenador_id]);
        }
        $plantilla = $stmtS->fetch();

        if ($plantilla) {
            $stmtR = $pdo->prepare("SELECT * FROM plantilla_rutinas WHERE plantilla_id = ? ORDER BY dia_offset ASC");
            $stmtR->execute([$plantilla_id]);
            $res = $stmtR->fetchAll();
            foreach ($res as $r) {
                $rutinas[$r['dia_offset']] = $r;
            }
        } else {
            $plantilla_id = 0;
        }
    } catch (PDOException $e) {
        $plantilla_id = 0;
    }
}

$page_title = $plantilla_id > 0 ? "Editar Plantilla" : "Nueva Plantilla";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container dashboard-container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold">
                <i class="fa-solid fa-layer-group text-warning me-2"></i>
                <?php echo $plantilla_id > 0 ? "Editar Plantilla" : "Crear Nueva Plantilla"; ?>
            </h2>
            <p class="text-secondary mb-0">Configura el nombre, semanas y rutinas diarias en un solo paso.</p>
        </div>
        <a href="javascript:void(0)" onclick="confirmarCancelar()" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-xmark me-2"></i>Cancelar
        </a>
    </div>

    <form action="/actions/admin_plantilla_action.php" method="POST" id="formPlantillaFull">
        <?php if ($plantilla_id > 0): ?>
            <input type="hidden" name="action" value="update_plantilla_full">
            <input type="hidden" name="plantilla_id" value="<?php echo $plantilla_id; ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create_plantilla_full">
        <?php 
        endif; 
        $semanas_default = $plantilla ? ceil($plantilla['duracion_dias'] / 7) : 4;
        ?>
        <input type="hidden" name="duracion_dias" id="duracion_dias_hidden" value="<?php echo $plantilla ? $plantilla['duracion_dias'] : 28; ?>">

        <!-- Datos Generales -->
        <div class="card-premium p-4 mb-4">
            <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-circle-info text-warning me-2"></i>Datos de la Plantilla</h5>
            <div class="row g-3">
                <div class="col-md-6 col-12">
                    <label class="form-label form-label-custom">Nombre de la Plantilla *</label>
                    <input type="text" name="titulo" id="titulo_plantilla" class="form-control form-control-custom" placeholder="Ej: Acondicionamiento General 4 Semanas" value="<?php echo $plantilla ? htmlspecialchars($plantilla['titulo']) : ''; ?>" required oninput="marcarDirty()">
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label form-label-custom">Cantidad de Semanas *</label>
                    <select name="semanas" id="semanas_select" class="form-select form-control-custom" onchange="generarGridSemanas(); marcarDirty();" required>
                        <?php for ($s = 1; $s <= 12; $s++): ?>
                            <option value="<?php echo $s; ?>" <?php echo $s == $semanas_default ? 'selected' : ''; ?>><?php echo $s; ?> <?php echo $s == 1 ? 'Semana' : 'Semanas'; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label form-label-custom">Descripción Breve</label>
                    <textarea name="descripcion" id="descripcion_plantilla" class="form-control form-control-custom" rows="2" placeholder="Indicaciones rápidas sobre el enfoque de este plan..." oninput="marcarDirty()"><?php echo $plantilla ? htmlspecialchars($plantilla['descripcion']) : ''; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Contenedor Dinámico de Semanas -->
        <h4 class="text-white fw-bold mb-3"><i class="fa-solid fa-calendar-days text-warning me-2"></i>Días de Entrenamiento</h4>
        <div id="weeks_grid_container">
            <!-- Cargado por JavaScript -->
        </div>

        <!-- Botones de Acción -->
        <div class="card-premium p-3 text-end mb-5">
            <button type="button" onclick="confirmarCancelar()" class="btn btn-outline-danger me-2">
                <i class="fa-solid fa-trash me-2"></i>Descartar
            </button>
            <button type="submit" class="btn btn-success-custom fw-bold px-4">
                <i class="fa-solid fa-save me-2"></i><?php echo $plantilla_id > 0 ? "Guardar Cambios" : "Crear Plantilla"; ?>
            </button>
        </div>
    </form>
</div>

<!-- MODAL INDIVIDUAL CONFIGURAR DÍA -->
<div class="modal fade" id="modalConfigurarDia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white fw-bold" id="modal_dia_titulo">Configurar Día</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">
                <input type="hidden" id="modal_dia_offset">
                
                <div class="mb-3">
                    <label class="form-label form-label-custom">Título de la Sesión *</label>
                    <input type="text" id="modal_titulo" class="form-control form-control-custom" placeholder="Ej: Fondo de Volumen / Cuestas de Potencia">
                </div>
                
                <div class="row mb-3 g-2">
                    <div class="col-md-4">
                        <label class="form-label form-label-custom">Tipo de Sesión *</label>
                        <select id="modal_tipo_sesion" class="form-select form-control-custom">
                            <option value="Bici">Bici</option>
                            <option value="Cambios de Ritmo">Cambios de Ritmo</option>
                            <option value="Cuestas">Cuestas</option>
                            <option value="Fondo" selected>Fondo</option>
                            <option value="Pasadas">Pasadas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-custom">Distancia (km)</label>
                        <input type="number" step="0.1" id="modal_distancia_km" class="form-control form-control-custom" value="0.0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-custom">Ritmo Sugerido</label>
                        <input type="text" id="modal_ritmo_sugerido" class="form-control form-control-custom" placeholder="Ej: 5:45 min/km">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label form-label-custom">Terreno Recomendado *</label>
                    <select id="modal_terreno" class="form-select form-control-custom">
                        <option value="Montaña" selected>Montaña</option>
                        <option value="Pista">Pista</option>
                        <option value="Plano">Plano</option>
                        <option value="Técnico">Técnico</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label form-label-custom">Descripción Detallada *</label>
                    <textarea id="modal_descripcion" class="form-control form-control-custom" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="borrarRutinaModal()">
                    <i class="fa-solid fa-trash me-1"></i>Borrar Día
                </button>
                <div>
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-trail btn-sm" onclick="guardarRutinaModal()">
                        <i class="fa-solid fa-check me-1"></i>Guardar Día
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Almacén de rutinas cargadas en memoria
let rutinasData = {};
let isFormDirty = false;

<?php if ($plantilla && count($rutinas) > 0): ?>
    rutinasData = {
        <?php foreach ($rutinas as $offset => $r): ?>
            "<?php echo $offset; ?>": {
                titulo: <?php echo json_encode($r['titulo']); ?>,
                tipo_sesion: <?php echo json_encode($r['tipo_sesion']); ?>,
                distancia_km: <?php echo json_encode($r['distancia_km']); ?>,
                ritmo_sugerido: <?php echo json_encode($r['ritmo_sugerido']); ?>,
                terreno: <?php echo json_encode($r['terreno']); ?>,
                descripcion: <?php echo json_encode($r['descripcion']); ?>,
                activo: 1
            },
        <?php endforeach; ?>
    };
<?php endif; ?>

function marcarDirty() {
    isFormDirty = true;
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function generarGridSemanas() {
    const semanasSelect = document.getElementById('semanas_select');
    if (!semanasSelect) return;
    
    const semanas = parseInt(semanasSelect.value) || 4;
    const container = document.getElementById('weeks_grid_container');
    const duracionHidden = document.getElementById('duracion_dias_hidden');
    
    const diasTotales = semanas * 7;
    if (duracionHidden) duracionHidden.value = diasTotales;

    let html = '';
    const diasSemanaNombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    for (let w = 1; w <= semanas; w++) {
        html += `
        <div class="card-premium p-4 mb-4">
            <h5 class="text-warning fw-bold mb-3"><i class="fa-solid fa-calendar-week me-2"></i>Semana ${w}</h5>
            <div class="row g-2">
        `;
        
        for (let dw = 0; dw < 7; dw++) {
            const d = (w - 1) * 7 + dw + 1;
            
            let dateStr = 'Día ' + d;
            
            const rutina = rutinasData[d] || null;
            const hasRutina = (rutina && rutina.activo === 1);
            
            html += `
            <div class="col-md col-12 mb-2">
                <div class="h-100 p-2.5 rounded bg-dark border ${hasRutina ? 'border-trail' : 'border-secondary'} d-flex flex-column justify-content-between text-start" style="min-height: 155px; background: rgba(0,0,0,0.2) !important;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary pb-1">
                            <span class="text-secondary small fw-bold text-uppercase" style="font-size: 0.75rem;">${diasSemanaNombres[dw]}</span>
                            <span class="text-muted small font-monospace" style="font-size: 0.75rem; font-weight: bold;">${dateStr}</span>
                        </div>
                        
                        <div id="preview_dia_${d}">
                            ${hasRutina ? `
                                <h6 class="text-white fw-bold mb-1 text-truncate" title="${escapeHtml(rutina.titulo)}" style="font-size: 0.9rem;">${escapeHtml(rutina.titulo)}</h6>
                                <span class="badge badge-tipo badge-${rutina.tipo_sesion.toLowerCase().replace(/\s+/g, '-')} mb-1" style="font-size: 0.75rem;">${rutina.tipo_sesion}</span>
                                ${parseFloat(rutina.distancia_km) > 0 ? `<div class="text-muted" style="font-size: 0.8rem;"><i class="fa-solid fa-route me-1"></i>${rutina.distancia_km} km</div>` : ''}
                            ` : `
                                <span class="d-block text-muted italic small mt-2" style="font-size: 0.85rem; font-style: italic;">Descanso</span>
                            `}
                        </div>
                    </div>
                    
                    <div class="mt-3 text-end">
                        <button type="button" class="btn btn-sm ${hasRutina ? 'btn-outline-warning' : 'btn-trail-outline'} p-1 px-2 border-0 w-100" style="font-size: 0.8rem; background: rgba(255,255,255,0.02);" onclick="abrirConfigurarDia(${d}, '${diasSemanaNombres[dw]}', '${dateStr}')">
                            <i class="fa-solid ${hasRutina ? 'fa-edit' : 'fa-plus'} me-1"></i>${hasRutina ? 'Editar' : 'Agregar'}
                        </button>
                    </div>
                </div>
                
                <!-- Inputs ocultos para enviar por POST -->
                <div id="inputs_dia_${d}">
                    ${hasRutina ? `
                        <input type="hidden" name="rutinas[${d}][activo]" value="1">
                        <input type="hidden" name="rutinas[${d}][titulo]" value="${escapeHtml(rutina.titulo)}">
                        <input type="hidden" name="rutinas[${d}][tipo_sesion]" value="${escapeHtml(rutina.tipo_sesion)}">
                        <input type="hidden" name="rutinas[${d}][distancia_km]" value="${rutina.distancia_km}">
                        <input type="hidden" name="rutinas[${d}][ritmo_sugerido]" value="${escapeHtml(rutina.ritmo_sugerido)}">
                        <input type="hidden" name="rutinas[${d}][terreno]" value="${escapeHtml(rutina.terreno)}">
                        <input type="hidden" name="rutinas[${d}][descripcion]" value="${escapeHtml(rutina.descripcion)}">
                    ` : `
                        <input type="hidden" name="rutinas[${d}][activo]" value="0">
                    `}
                </div>
            </div>
            `;
        }
        
        html += `
            </div>
        </div>
        `;
    }
    
    container.innerHTML = html;
}

let modalConfigObj = null;

function abrirConfigurarDia(d, nombreDia, dateStr) {
    document.getElementById('modal_dia_offset').value = d;
    document.getElementById('modal_dia_titulo').innerHTML = `Configurar: ${nombreDia} <span class="text-warning">(${dateStr})</span>`;
    
    const inputTitulo = document.getElementById('modal_titulo');
    const selectTipo = document.getElementById('modal_tipo_sesion');
    const inputDist = document.getElementById('modal_distancia_km');
    const inputRitmo = document.getElementById('modal_ritmo_sugerido');
    const selectTerr = document.getElementById('modal_terreno');
    const inputDesc = document.getElementById('modal_descripcion');

    const r = rutinasData[d];
    if (r && r.activo === 1) {
        inputTitulo.value = r.titulo || '';
        selectTipo.value = r.tipo_sesion || 'Fondo';
        inputDist.value = r.distancia_km || '0.0';
        inputRitmo.value = r.ritmo_sugerido || '';
        selectTerr.value = r.terreno || 'Montaña';
        inputDesc.value = r.descripcion || '';
    } else {
        inputTitulo.value = '';
        selectTipo.value = 'Fondo';
        inputDist.value = '0.0';
        inputRitmo.value = '';
        selectTerr.value = 'Montaña';
        inputDesc.value = "Movilidad + +Elongacion\n\nNota:";
    }
    
    if (!modalConfigObj) {
        modalConfigObj = new bootstrap.Modal(document.getElementById('modalConfigurarDia'));
    }
    modalConfigObj.show();
}

function guardarRutinaModal() {
    const d = document.getElementById('modal_dia_offset').value;
    const titulo = document.getElementById('modal_titulo').value.trim();
    const tipo = document.getElementById('modal_tipo_sesion').value;
    const dist = document.getElementById('modal_distancia_km').value || '0.0';
    const ritmo = document.getElementById('modal_ritmo_sugerido').value.trim();
    const terr = document.getElementById('modal_terreno').value;
    const desc = document.getElementById('modal_descripcion').value.trim();

    if (!titulo || !desc) {
        alert("Completa el título y la descripción de la rutina.");
        return;
    }

    rutinasData[d] = {
        titulo: titulo,
        tipo_sesion: tipo,
        distancia_km: dist,
        ritmo_sugerido: ritmo,
        terreno: terr,
        descripcion: desc,
        activo: 1
    };

    marcarDirty();
    if (modalConfigObj) modalConfigObj.hide();
    generarGridSemanas();
}

function borrarRutinaModal() {
    const d = document.getElementById('modal_dia_offset').value;
    
    rutinasData[d] = {
        activo: 0
    };

    marcarDirty();
    if (modalConfigObj) modalConfigObj.hide();
    generarGridSemanas();
}

function confirmarCancelar() {
    if (isFormDirty) {
        if (!confirm("Tienes cambios sin guardar en esta plantilla. ¿Seguro que deseas salir y descartar los cambios?")) {
            return;
        }
    }
    isFormDirty = false;
    window.location.href = "/admin/plantillas.php?msg=cancel_ok";
}

// Bloqueo antes de salir del sitio
window.addEventListener('beforeunload', function (e) {
    if (isFormDirty) {
        e.preventDefault();
        e.returnValue = 'Tienes cambios sin guardar en la plantilla.';
    }
});

// Desactivar alerta antes de submit
document.getElementById('formPlantillaFull').addEventListener('submit', function() {
    isFormDirty = false;
});

document.addEventListener("DOMContentLoaded", function() {
    generarGridSemanas();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
