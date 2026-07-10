<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Solo accesible para administradores y entrenadores totales
require_rol(['admin', 'entrenador_total']);

$page_title = "Planificador Asistido";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Obtener lista de alumnos activos
try {
    $stmtList = $pdo->query("
        SELECT ap.id AS alumno_id, u.nombre, u.apellido, ap.nivel, ap.plan_tipo
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.activo IN (1, 3)
        ORDER BY u.apellido ASC, u.nombre ASC
    ");
    $alumnos = $stmtList->fetchAll();
} catch (PDOException $e) {
    $alumnos = [];
}
?>

<div class="container dashboard-container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-white fw-bold"><i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i>Asistente de Planificación</h2>
            <p class="text-secondary mb-0">Genera entrenamientos periodizados de forma automática para tus alumnos usando tus plantillas o inteligencia artificial.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulario de Configuración -->
        <div class="col-lg-4">
            <div class="card-premium p-4 h-100">
                <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-sliders text-warning me-2"></i>Configuración del Plan</h5>
                
                <form id="formGenerador">
                    <input type="hidden" name="action" value="generar">
                    
                    <!-- Selección de Alumno -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Alumno *</label>
                        <select name="alumno_id" id="selectAlumno" class="form-select form-control-custom" required>
                            <option value="">Seleccionar alumno...</option>
                            <?php foreach ($alumnos as $a): ?>
                                <option value="<?php echo $a['alumno_id']; ?>" data-nivel="<?php echo $a['nivel']; ?>">
                                    <?php echo htmlspecialchars($a['apellido'] . ", " . $a['nombre']); ?> 
                                    (<?php echo htmlspecialchars($a['nivel']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Modo de Generación -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom d-block">Modo de Generación *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="modo" id="modoPlantillas" value="plantillas" checked autocomplete="off">
                            <label class="btn btn-outline-info py-2" for="modoPlantillas">
                                <i class="fa-solid fa-layer-group me-1"></i> Plantillas
                            </label>
                            
                            <input type="radio" class="btn-check" name="modo" id="modoIA" value="ia" autocomplete="off">
                            <label class="btn btn-outline-warning py-2" for="modoIA">
                                <i class="fa-solid fa-robot me-1"></i> Asistente IA
                            </label>
                        </div>
                    </div>

                    <!-- Directivas de Estructura IA (solo visible en modo IA) -->
                    <div class="mb-3" id="seccionEstructuraIA" style="display: none;">
                        <label class="form-label form-label-custom">Estructura del Plan / Directivas para la IA</label>
                        <textarea name="estructura_ia" id="estructuraIA" class="form-control form-control-custom" rows="4" placeholder="Ej: Lunes descanso, Martes pasadas, Miércoles fuerza, Sábado fondo. Evitar rodajes largos en plano..."></textarea>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">Indícale a la IA cómo estructurar los días de entrenamiento, tipos de sesión, progresiones u otras directivas que prefieras.</div>
                    </div>

                    <!-- Nivel (Auto-seleccionado al elegir alumno, pero modificable) -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Nivel de Entrenamiento *</label>
                        <select name="nivel" id="selectNivel" class="form-select form-control-custom" required>
                            <option value="Principiante">Principiante</option>
                            <option value="Intermedio">Intermedio</option>
                            <option value="Avanzado">Avanzado</option>
                            <option value="Elite">Elite</option>
                        </select>
                    </div>

                    <!-- Objetivo -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Objetivo del Plan *</label>
                        <select name="objetivo" class="form-select form-control-custom" required>
                            <option value="Adaptación">Adaptación General</option>
                            <option value="Base Aeróbica">Base Aeróbica / Volumen</option>
                            <option value="Fuerza y Cuestas">Fuerza / Desnivel</option>
                            <option value="Carrera Objetivo">Preparación de Carrera Objetivo</option>
                        </select>
                    </div>

                    <!-- Semanas y Días -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label form-label-custom">Duración (Semanas) *</label>
                            <select name="semanas" class="form-select form-control-custom" required>
                                <option value="4">4 Semanas</option>
                                <option value="8" selected>8 Semanas</option>
                                <option value="12">12 Semanas</option>
                                <option value="16">16 Semanas</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-custom">Días / Semana *</label>
                            <select name="dias_semana" class="form-select form-control-custom" required>
                                <option value="3" selected>3 Días</option>
                                <option value="4">4 Días</option>
                                <option value="5">5 Días</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fecha de Inicio -->
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Carrera Objetivo (Opcional) -->
                    <div class="p-3 bg-dark rounded border border-secondary mb-4" id="seccionCarrera" style="display: none;">
                        <h6 class="text-warning fw-bold mb-2"><i class="fa-solid fa-mountain me-2"></i>Detalles de la Carrera</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label form-label-custom small">Distancia (km)</label>
                                <input type="number" name="carrera_distancia" class="form-control form-control-custom small" placeholder="Ej: 21" min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-custom small">Desnivel (D+)</label>
                                <input type="number" name="carrera_desnivel" class="form-control form-control-custom small" placeholder="Ej: 600" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Botón Generar -->
                    <button type="submit" class="btn btn-trail w-100 py-2.5 fw-bold" id="btnGenerar">
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generar Propuesta
                    </button>
                </form>
            </div>
        </div>

        <!-- Vista Previa y Edición -->
        <div class="col-lg-8">
            <div class="card-premium p-4 h-100 d-flex flex-column justify-content-between" style="min-height: 500px;">
                <div>
                    <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-eye text-warning me-2"></i>Propuesta de Planificación</h5>
                    
                    <div id="loadingState" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                        <h6 class="text-white fw-bold">Generando entrenamientos...</h6>
                        <p class="text-secondary small mb-0" id="loadingText">Esto puede tardar unos segundos mientras consultamos a la IA.</p>
                    </div>

                    <div id="emptyState" class="text-center py-5 text-secondary">
                        <i class="fa-solid fa-calendar-days fa-3x mb-3 text-muted"></i>
                        <h6 class="text-white fw-bold">No hay ninguna propuesta generada</h6>
                        <p class="small mb-0">Completa la configuración de la izquierda y haz clic en "Generar Propuesta".</p>
                    </div>

                    <div id="planContent" style="display: none;">
                        <div class="accordion accordion-flush bg-transparent" id="accordionPlan">
                            <!-- Semanas se renderizarán dinámicamente aquí -->
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top border-secondary text-end" id="planActions" style="display: none;">
                    <button class="btn btn-secondary me-2" onclick="cancelarPropuesta()">Descartar</button>
                    <button class="btn btn-success-custom fw-bold" id="btnAplicar" onclick="aplicarPlanAlCalendario()">
                        <i class="fa-solid fa-calendar-check me-2"></i>Aplicar Plan al Alumno
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts de Gestión AJAX -->
<script>
// Auto-seleccionar nivel al elegir alumno
document.getElementById('selectAlumno').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const nivel = selectedOption.getAttribute('data-nivel');
    if (nivel) {
        document.getElementById('selectNivel').value = nivel;
    }
});

// Mostrar/Ocultar detalles de la carrera según objetivo
document.querySelector('select[name="objetivo"]').addEventListener('change', function() {
    const seccionCarrera = document.getElementById('seccionCarrera');
    if (this.value === 'Carrera Objetivo') {
        seccionCarrera.style.display = 'block';
    } else {
        seccionCarrera.style.display = 'none';
    }
});

// Mostrar/Ocultar directivas de la estructura IA según el modo de generación
document.querySelectorAll('input[name="modo"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const seccionEstructuraIA = document.getElementById('seccionEstructuraIA');
        if (document.getElementById('modoIA').checked) {
            seccionEstructuraIA.style.display = 'block';
        } else {
            seccionEstructuraIA.style.display = 'none';
        }
    });
});

let planGeneradoGlobal = null;

// Enviar formulario para generar propuesta
document.getElementById('formGenerador').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isIA = document.getElementById('modoIA').checked;
    
    // Cambiar estados visuales
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('planContent').style.display = 'none';
    document.getElementById('planActions').style.display = 'none';
    document.getElementById('loadingState').style.display = 'block';
    
    // Ajustar texto de carga
    const loadingText = document.getElementById('loadingText');
    if (isIA) {
        loadingText.innerText = "El Asistente IA está construyendo descripciones detalladas personalizadas.";
    } else {
        loadingText.innerText = "Buscando rutinas ideales en tu biblioteca de entrenamientos.";
    }
    
    fetch('/actions/admin_asistente_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('loadingState').style.display = 'none';
        
        if (data.success) {
            planGeneradoGlobal = data.plan;
            renderPlan(data.plan);
            document.getElementById('planContent').style.display = 'block';
            document.getElementById('planActions').style.display = 'block';
        } else {
            alert("Error al generar plan: " + data.error);
            document.getElementById('emptyState').style.display = 'block';
        }
    })
    .catch(err => {
        document.getElementById('loadingState').style.display = 'none';
        alert("Error de conexión al servidor.");
        document.getElementById('emptyState').style.display = 'block';
    });
});

// Renderizar la propuesta en el acordeón
function renderPlan(plan) {
    const container = document.getElementById('accordionPlan');
    container.innerHTML = '';
    
    plan.forEach((semana, indexSemana) => {
        const collapsed = indexSemana === 0 ? '' : 'collapsed';
        const show = indexSemana === 0 ? 'show' : '';
        
        let rutinasHTML = '';
        semana.rutinas.forEach((r, indexRutina) => {
            const badgeColor = obtenerBadgeColor(r.tipo_sesion);
            const indexUnico = `${indexSemana}_${indexRutina}`;
            
            rutinasHTML += `
                <div class="p-3 bg-dark rounded border border-secondary mb-3 novelty-item border-left-workout" style="border-left: 4px solid ${badgeColor} !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge fw-bold" style="background-color: ${badgeColor}; color: #fff;">${r.tipo_sesion}</span>
                            <span class="text-white fw-bold">${r.dia_semana} (${formatearFechaDisplay(r.fecha)})</span>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary-custom text-secondary small">${r.terreno}</span>
                            ${r.distancia_km > 0 ? `<span class="badge bg-info text-dark small">${r.distancia_km} km</span>` : ''}
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-custom bg-transparent border-0 px-0 py-1 text-white fw-bold" 
                            style="font-size: 1rem; border-bottom: 1px dashed rgba(255,255,255,0.2) !important;" 
                            value="${r.titulo}" 
                            onchange="actualizarValorGlobal(${indexSemana}, ${indexRutina}, 'titulo', this.value)">
                    </div>
                    
                    <div>
                        <textarea class="form-control form-control-custom bg-transparent border-0 px-0 text-white-50 text-wrap" 
                            rows="4" style="font-size: 0.85rem;" 
                            onchange="actualizarValorGlobal(${indexSemana}, ${indexRutina}, 'descripcion', this.value)">${r.descripcion}</textarea>
                    </div>
                    
                    <div class="row g-2 mt-2">
                        <div class="col-sm-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-secondary small" style="font-size: 0.75rem;">Ritmo</span>
                                <input type="text" class="form-control form-control-custom bg-transparent border-secondary text-white-50 small" 
                                    style="font-size: 0.8rem;" 
                                    value="${r.ritmo_sugerido}" 
                                    onchange="actualizarValorGlobal(${indexSemana}, ${indexRutina}, 'ritmo_sugerido', this.value)">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-secondary small" style="font-size: 0.75rem;">Km</span>
                                <input type="number" step="0.1" class="form-control form-control-custom bg-transparent border-secondary text-white-50 small" 
                                    style="font-size: 0.8rem;" 
                                    value="${r.distancia_km}" 
                                    onchange="actualizarValorGlobal(${indexSemana}, ${indexRutina}, 'distancia_km', this.value)">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-secondary small" style="font-size: 0.75rem;">Terreno</span>
                                <select class="form-select form-control-custom bg-transparent border-secondary text-white-50 small" 
                                    style="font-size: 0.8rem; padding: 2px 8px;" 
                                    onchange="actualizarValorGlobal(${indexSemana}, ${indexRutina}, 'terreno', this.value)">
                                    <option value="Plano" ${r.terreno === 'Plano' ? 'selected' : ''}>Plano</option>
                                    <option value="Pista" ${r.terreno === 'Pista' ? 'selected' : ''}>Pista</option>
                                    <option value="Montaña" ${r.terreno === 'Montaña' ? 'selected' : ''}>Montaña</option>
                                    <option value="Técnico" ${r.terreno === 'Técnico' ? 'selected' : ''}>Técnico</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML += `
            <div class="accordion-item bg-transparent border-secondary">
                <h2 class="accordion-header" id="headingSemana_${semana.semana}">
                    <button class="accordion-button bg-transparent text-white ${collapsed} fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSemana_${semana.semana}" aria-expanded="${indexSemana === 0 ? 'true' : 'false'}" aria-controls="collapseSemana_${semana.semana}">
                        <div class="d-flex justify-content-between w-100 align-items-center pe-3">
                            <span>Semana ${semana.semana} — Fase: ${semana.fase}</span>
                            <span class="badge bg-secondary text-light fs-7">${semana.volumen_total_km} km totales</span>
                        </div>
                    </button>
                </h2>
                <div id="collapseSemana_${semana.semana}" class="accordion-collapse collapse ${show}" aria-labelledby="headingSemana_${semana.semana}" data-bs-parent="#accordionPlan">
                    <div class="accordion-body px-0 py-3">
                        ${rutinasHTML}
                    </div>
                </div>
            </div>
        `;
    });
}

// Actualizar valores de edición interactiva
function actualizarValorGlobal(semanaIndex, rutinaIndex, campo, valor) {
    if (planGeneradoGlobal && planGeneradoGlobal[semanaIndex] && planGeneradoGlobal[semanaIndex].rutinas[rutinaIndex]) {
        if (campo === 'distancia_km') {
            planGeneradoGlobal[semanaIndex].rutinas[rutinaIndex][campo] = parseFloat(valor) || 0.0;
        } else {
            planGeneradoGlobal[semanaIndex].rutinas[rutinaIndex][campo] = valor;
        }
    }
}

// Descartar propuesta
function cancelarPropuesta() {
    if (confirm("¿Estás seguro de que deseas descartar este plan generado?")) {
        planGeneradoGlobal = null;
        document.getElementById('planContent').style.display = 'none';
        document.getElementById('planActions').style.display = 'none';
        document.getElementById('emptyState').style.display = 'block';
    }
}

// Aplicar plan completo al alumno
function aplicarPlanAlCalendario() {
    if (!planGeneradoGlobal) return;
    
    const alumno_id = document.getElementById('selectAlumno').value;
    const btn = document.getElementById('btnAplicar');
    
    // Aplanar las rutinas de todas las semanas en un solo arreglo plano para enviar
    const rutinasAplanadas = [];
    planGeneradoGlobal.forEach(semana => {
        semana.rutinas.forEach(r => {
            rutinasAplanadas.push(r);
        });
    });
    
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando plan...`;
    
    const formData = new FormData();
    formData.append('action', 'aplicar');
    formData.append('alumno_id', alumno_id);
    formData.append('rutinas', JSON.stringify(rutinasAplanadas));
    
    fetch('/actions/admin_asistente_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-calendar-check me-2"></i>Aplicar Plan al Alumno`;
        
        if (data.success) {
            alert("¡El plan de entrenamiento ha sido aplicado y guardado con éxito!");
            window.location.href = `/admin/planificador.php?alumno_id=${alumno_id}`;
        } else {
            alert("Hubo un error al guardar el plan: " + data.error);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-calendar-check me-2"></i>Aplicar Plan al Alumno`;
        alert("Error de red al intentar guardar el plan.");
    });
}

// Helpers visuales
function obtenerBadgeColor(tipo) {
    const colores = {
        'Cuestas': '#ff6b35',          // Naranja
        'Fondo': '#2a9d8f',            // Verde azulado
        'Pasadas': '#e76f51',          // Coral
        'Cambios de Ritmo': '#f4a261', // Ocre/Amarillo
        'Bici': '#457b9d',             // Azul acero
        'Descanso': '#6c757d'          // Gris
    };
    return colores[tipo] || '#6c757d';
}

function formatearFechaDisplay(fechaStr) {
    const parts = fechaStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}`;
    }
    return fechaStr;
}
</script>

<style>
/* Estilos premium para la card del planificador asistido */
.border-left-workout {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.border-left-workout:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.fs-7 {
    font-size: 0.85rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
