<?php
$page_title = "Irma Trail Running";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-section position-relative" style="background: linear-gradient(135deg, rgba(16, 21, 34, 0.95), rgba(16, 21, 34, 0.8)), url('https://images.unsplash.com/photo-1544198365-f5d60b6d8190?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; min-height: 85vh; display: flex; align-items: center;">
    <div class="container text-center text-lg-start">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-5 mb-lg-0">
                <div class="badge text-dark mb-4 px-3 py-2 fw-semibold text-uppercase" style="background-color: var(--trail-orange); border-radius: 30px; letter-spacing: 2px;">
                    <i class="fa-solid fa-fire me-1"></i> Entrena. Supera. Descubre.
                </div>
                <h1 class="display-3 fw-bold text-white mb-4" style="line-height: 1.2;">
                    Bienvenido a <br><span style="color: #663399; text-shadow: 0px 2px 10px rgba(102, 51, 153, 0.4);">Irma Trail Running</span>
                </h1>
                <p class="text-secondary fs-5 mb-5" style="max-width: 600px;">
                    Somos una comunidad apasionada por la montaña. Planes personalizados a distancia y entrenamientos grupales presenciales para que alcances tu máximo nivel.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <a href="#planes" class="btn btn-trail btn-lg px-5"><i class="fa-solid fa-arrow-right me-2"></i>Ver Planes</a>
                    <a href="/login.php" class="btn btn-outline-light btn-lg px-5"><i class="fa-solid fa-user me-2"></i>Ingresar</a>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <!-- Large Logo Display -->
                <img src="/assets/img/logo.jpeg" alt="Irma Trail Running Logo" class="img-fluid rounded-circle shadow-lg" style="max-width: 400px; border: 5px solid rgba(255, 107, 53, 0.3);">
            </div>
        </div>
    </div>
</section>

<!-- Floating WhatsApp -->
<a href="https://wa.me/5492944552162" class="whatsapp-float" target="_blank" title="Contactar por WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Estilos para el floating button -->
<style>
.whatsapp-float {
    position: fixed;
    width: 60px;
    height: 60px;
    bottom: 40px;
    right: 40px;
    background-color: #25d366;
    color: #FFF;
    border-radius: 50px;
    text-align: center;
    font-size: 30px;
    box-shadow: 0px 4px 15px rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    text-decoration: none !important; /* Sacar subrayado */
}
.whatsapp-float:hover {
    background-color: #128c7e;
    color: #fff;
    transform: scale(1.1);
}
</style>

<!-- Sobre Nosotros -->
<section id="sobre-nosotros" class="py-5" style="background-color: var(--bg-primary);">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <img src="/assets/img/foto1.png" alt="Trail Running Group" class="img-fluid rounded-4 shadow" style="border: 1px solid var(--border-color);">
            </div>
            <div class="col-lg-6 ps-lg-5">
                <h2 class="text-white fw-bold mb-4">¿Qué es <span style="color: #663399; text-shadow: 0px 2px 10px rgba(102, 51, 153, 0.4);">Irma Trail Running</span>?</h2>
                
                <p class="text-secondary mb-4 fs-5 fw-light">
                    Más que un grupo de entrenamiento, somos una familia que comparte la pasión por recorrer senderos, conquistar cumbres y superar obstáculos.
                </p>
                <p class="text-secondary mb-4">
                    En Irma Trail Running, transformamos tu relación con la montaña mediante una metodología integral basada en el alto rendimiento deportivo. Nuestra propuesta incluye:
                </p>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-users text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Comunidad Activa y Enfoque Humano</h5>
                        <p class="text-secondary mb-0">Entrenamientos en grupo donde la motivación y el compañerismo son fundamentales. No solo enseñamos técnica, fomentamos una comunidad donde compartimos la pasión.</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-ranking-star text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Niveles Adaptados y Flexibilidad</h5>
                        <p class="text-secondary mb-0">Desde planes iniciales hasta alto rendimiento. Tu rutina se diseña para ti, adaptada a tu ubicación, disponibilidad y objetivos individuales.</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-mountain-sun text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Inspiración Real</h5>
                        <p class="text-secondary mb-0">Nos mueve el ejemplo de superación, como el de Irma, quien demuestra que no hay límites de edad para disfrutar del movimiento. ¡Únete a nuestra familia y descubre paisajes inolvidables!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Galería / Carrusel -->
<section id="galeria" class="py-5" style="background-color: var(--bg-primary);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="text-white fw-bold display-5">Nuestra <span style="color: var(--trail-orange);">Comunidad</span> en Acción</h2>
            <p class="text-secondary mx-auto fs-5" style="max-width: 600px;">Viví la experiencia de Irma Trail Running a través de nuestros entrenamientos y desafíos.</p>
        </div>
        
        <div id="premiumCarousel" class="carousel slide carousel-fade carousel-premium-container" data-bs-ride="carousel" data-bs-interval="5000">
            <!-- Indicators -->
            <div class="carousel-indicators carousel-indicators-premium">
                <button type="button" data-bs-target="#premiumCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#premiumCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#premiumCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                <button type="button" data-bs-target="#premiumCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
                <button type="button" data-bs-target="#premiumCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
            </div>
            
            <!-- Slide list -->
            <div class="carousel-inner">
                <!-- Slide 1 -->
                <div class="carousel-item active">
                    <div class="carousel-premium-background" style="background-image: url('/assets/img/c1.jpg');"></div>
                    <img src="/assets/img/c1.jpg" class="carousel-premium-img-foreground" alt="Comunidad de Montaña">
                    <div class="carousel-caption-premium d-none d-md-block">
                        <h3>Comunidad de Montaña</h3>
                        <p>Compartimos la pasión por la naturaleza, el compañerismo y la motivación mutua en cada entrenamiento.</p>
                    </div>
                </div>
                <!-- Slide 2 -->
                <div class="carousel-item">
                    <div class="carousel-premium-background" style="background-image: url('/assets/img/c2.jpg');"></div>
                    <img src="/assets/img/c2.jpg" class="carousel-premium-img-foreground" alt="Superación Personal">
                    <div class="carousel-caption-premium d-none d-md-block">
                        <h3>Superación Personal</h3>
                        <p>Planes personalizados adaptados a tus objetivos individuales para conquistar nuevas cumbres.</p>
                    </div>
                </div>
                <!-- Slide 3 -->
                <div class="carousel-item">
                    <div class="carousel-premium-background" style="background-image: url('/assets/img/c3.jpg');"></div>
                    <img src="/assets/img/c3.jpg" class="carousel-premium-img-foreground" alt="Entrenamientos Grupales">
                    <div class="carousel-caption-premium d-none d-md-block">
                        <h3>Entrenamientos Grupales</h3>
                        <p>Sumate a nuestras clases presenciales con corrección técnica, fuerza y cuestas en Bariloche.</p>
                    </div>
                </div>
                <!-- Slide 4 -->
                <div class="carousel-item">
                    <div class="carousel-premium-background" style="background-image: url('/assets/img/c5.jpg');"></div>
                    <img src="/assets/img/c5.jpg" class="carousel-premium-img-foreground" alt="Exploración sin Límites">
                    <div class="carousel-caption-premium d-none d-md-block">
                        <h3>Exploración sin Límites</h3>
                        <p>Corré por senderos únicos, descubrí bosques mágicos y paisajes inigualables de la Patagonia.</p>
                    </div>
                </div>
                <!-- Slide 5 -->
                <div class="carousel-item">
                    <div class="carousel-premium-background" style="background-image: url('/assets/img/c6.jpg');"></div>
                    <img src="/assets/img/c6.jpg" class="carousel-premium-img-foreground" alt="Inspiración en Movimiento">
                    <div class="carousel-caption-premium d-none d-md-block">
                        <h3>Inspiración en Movimiento</h3>
                        <p>Como Irma, demostramos que no existen límites de edad ni de tiempo para disfrutar del running.</p>
                    </div>
                </div>
            </div>
            
            <!-- Controls -->
            <button class="carousel-control-prev-premium" type="button" data-bs-target="#premiumCarousel" data-bs-slide="prev">
                <i class="fa-solid fa-chevron-left fa-lg" aria-hidden="true"></i>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next-premium" type="button" data-bs-target="#premiumCarousel" data-bs-slide="next">
                <i class="fa-solid fa-chevron-right fa-lg" aria-hidden="true"></i>
                <span class="visually-hidden">Siguiente</span>
            </button>
        </div>
        
        <!-- Mobile Captions display (below the carousel on small screens for readability) -->
        <div class="d-md-none text-center mt-4 px-3 py-3 rounded-4" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);" id="mobile-caption-container">
            <h4 class="text-white fw-bold mb-2" id="mobile-caption-title">Comunidad de Montaña</h4>
            <p class="text-secondary mb-0" id="mobile-caption-desc">Compartimos la pasión por la naturaleza, el compañerismo y la motivación mutua en cada entrenamiento.</p>
        </div>
    </div>
</section>

<!-- Mobile Caption Sync Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const carouselEl = document.getElementById('premiumCarousel');
    if (!carouselEl) return;
    
    const mobileTitle = document.getElementById('mobile-caption-title');
    const mobileDesc = document.getElementById('mobile-caption-desc');
    
    if (!mobileTitle || !mobileDesc) return;
    
    const captions = [
        {
            title: "Comunidad de Montaña",
            desc: "Compartimos la pasión por la naturaleza, el compañerismo y la motivación mutua en cada entrenamiento."
        },
        {
            title: "Superación Personal",
            desc: "Planes personalizados adaptados a tus objetivos individuales para conquistar nuevas cumbres."
        },
        {
            title: "Entrenamientos Grupales",
            desc: "Sumate a nuestras clases presenciales con corrección técnica, fuerza y cuestas en Bariloche."
        },
        {
            title: "Exploración sin Límites",
            desc: "Corré por senderos únicos, descubrí bosques mágicos y paisajes inigualables de la Patagonia."
        },
        {
            title: "Inspiración en Movimiento",
            desc: "Como Irma, demostramos que no existen límites de edad ni de tiempo para disfrutar del running."
        }
    ];
    
    carouselEl.addEventListener('slide.bs.carousel', function(event) {
        const nextIndex = event.to;
        if (captions[nextIndex]) {
            // Smooth text transition
            mobileTitle.style.opacity = 0;
            mobileDesc.style.opacity = 0;
            setTimeout(() => {
                mobileTitle.innerText = captions[nextIndex].title;
                mobileDesc.innerText = captions[nextIndex].desc;
                mobileTitle.style.opacity = 1;
                mobileDesc.style.opacity = 1;
            }, 250);
        }
    });
});
</script>

<!-- Planes de Entrenamiento -->
<section id="planes" class="py-5" style="background-color: var(--bg-secondary);">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="text-white fw-bold display-5">Nuestros <span style="color: var(--trail-orange);">Planes</span></h2>
            <p class="text-secondary mx-auto fs-5" style="max-width: 600px;">Elige la modalidad que mejor se adapte a tus horarios, objetivos y ubicación.</p>
        </div>
        
        <div class="row g-4 justify-content-center">
            <!-- Plan Distancia -->
            <div class="col-md-5 d-flex flex-column">
                <div class="card-premium h-100 p-1 plan-card-hover" style="background: linear-gradient(to bottom, var(--bg-secondary), var(--bg-primary)); transition: all 0.3s ease;">
                    <div class="p-5 text-center d-flex flex-column h-100">
                        <div>
                            <div class="mb-4">
                                <i class="fa-solid fa-earth-americas fa-3x icon-plan" style="color: var(--text-muted); transition: color 0.3s ease;"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-3">Plan a Distancia</h3>
                            <p class="text-secondary mb-4">Lleva tu entrenamiento a cualquier parte del mundo. Planificación 100% personalizada a través de nuestra plataforma web.</p>
                            
                            <ul class="text-start text-secondary list-unstyled mb-5">
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Rutinas semanales en tu panel</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Registro de feedback post-entreno</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Adaptado a tus horarios locales</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Comunicación vía WhatsApp</li>
                            </ul>
                        </div>
                        <div class="mt-auto">
                            <button type="button" class="btn btn-outline-warning w-100 py-3 fw-bold" style="border-radius: 8px;" onclick="abrirConsultaModal('Plan a Distancia')">Consultar Info</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Plan Presencial -->
            <div class="col-md-5 d-flex flex-column">
                <div class="card-premium h-100 p-1 plan-card-hover" style="background: linear-gradient(to bottom, var(--bg-secondary), var(--bg-primary)); transition: all 0.3s ease;">
                    <div class="p-5 text-center d-flex flex-column h-100">
                        <div>
                            <div class="mb-4">
                                <i class="fa-solid fa-people-group fa-3x icon-plan" style="color: var(--text-muted); transition: color 0.3s ease;"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-3">Plan Presencial</h3>
                            <p class="text-secondary mb-4">Suma la energía del grupo a tus entrenamientos. Clases presenciales con corrección técnica y seguimiento en vivo.</p>
                            
                            <ul class="text-start text-secondary list-unstyled mb-5">
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Entrenamientos presenciales guiados</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Todo lo incluido en el Plan Distancia</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Trabajo de fuerza, técnica y cuestas</li>
                                <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Logística para carreras en grupo</li>
                            </ul>
                        </div>
                        <div class="mt-auto">
                            <button type="button" class="btn btn-outline-warning w-100 py-3 fw-bold" style="border-radius: 8px;" onclick="abrirConsultaModal('Plan Presencial')">Consultar Cupos</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contacto Corto -->
<section id="contacto" class="py-5 text-center" style="background: linear-gradient(rgba(16,21,34,0.9), rgba(16,21,34,0.9)), url('https://images.unsplash.com/photo-1473283147055-e39c51470b0c?q=80&w=2070&auto=format&fit=crop'); background-attachment: fixed; background-size: cover;">
    <div class="container py-5">
        <h2 class="text-white display-5 fw-bold mb-4">Súmate a la manada</h2>
        <p class="text-secondary fs-5 mb-5 mx-auto" style="max-width: 600px;">Sigue nuestras aventuras en Instagram y escríbenos para coordinar tu primer entrenamiento.</p>
        
        <div class="d-flex flex-wrap justify-content-center gap-4">
            <!-- WhatsApp -->
            <a href="https://wa.me/5492944552162" target="_blank" class="btn btn-lg px-4 py-3 shadow-lg text-white" style="min-width: 240px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #25D366, #128C7E); border: none; font-weight: bold; border-radius: 30px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fab fa-whatsapp me-2"></i> WhatsApp
            </a>
            <!-- Instagram -->
            <a href="https://instagram.com/irmatrailrunning" target="_blank" class="btn btn-lg px-4 py-3 shadow-lg text-white" style="min-width: 240px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); border: none; font-weight: bold; border-radius: 30px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fab fa-instagram me-2"></i> Instagram
            </a>
            <!-- Email -->
            <a href="mailto:irmatrailrunning@gmail.com" class="btn btn-lg px-4 py-3 shadow-lg text-white" style="min-width: 240px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(45deg, #4A5568, #2D3748); border: none; font-weight: bold; border-radius: 30px; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fa-solid fa-envelope me-2"></i> Correo Electrónico
            </a>
        </div>
    </div>
</section>

<style>
/* Animaciones hover para cards e instagram */
.card-premium:hover { transform: translateY(-5px); }
.btn[href*="instagram"]:hover { transform: scale(1.05); }

/* Plan card hover effect */
.plan-card-hover:hover {
    border-color: #663399; /* Usa el color del logo */
    box-shadow: 0 0 20px rgba(102, 51, 153, 0.3);
}
.plan-card-hover:hover .icon-plan {
    color: #663399 !important;
}
.plan-card-hover:hover .btn-outline-warning {
    background-color: #663399;
    border-color: #663399;
    color: white;
}
</style>

<!-- Modal de Consulta de Planes -->
<div class="modal fade" id="consultaPlanModal" tabindex="-1" aria-labelledby="consultaPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
            <div class="modal-header border-bottom border-dark">
                <h5 class="modal-title text-white fw-bold" id="consultaPlanModalLabel">
                    <i class="fa-solid fa-file-signature text-warning me-2"></i>Consultar <span id="modalPlanNombre" class="text-warning">Plan</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formConsultaPlan" onsubmit="enviarConsultaWhatsApp(event)">
                <input type="hidden" id="inputPlanTipo" name="plan_tipo">
                <div class="modal-body text-start">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="consulta_nombre" class="form-label form-label-custom">Nombre *</label>
                            <input type="text" id="consulta_nombre" class="form-control form-control-custom" placeholder="Ej: Juan" required>
                        </div>
                        <div class="col-6">
                            <label for="consulta_apellido" class="form-label form-label-custom">Apellido *</label>
                            <input type="text" id="consulta_apellido" class="form-control form-control-custom" placeholder="Ej: Pérez" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="consulta_email" class="form-label form-label-custom">Correo Electrónico *</label>
                        <input type="email" id="consulta_email" class="form-control form-control-custom" placeholder="Ej: juan.perez@gmail.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="consulta_mensaje" class="form-label form-label-custom">Consulta *</label>
                        <textarea id="consulta_mensaje" class="form-control form-control-custom" rows="4" placeholder="Escribe tu consulta o dudas aquí..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail btn-sm"><i class="fa-brands fa-whatsapp me-2"></i>Enviar Consulta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let consultaModalInstance = null;

function abrirConsultaModal(planNombre) {
    document.getElementById('modalPlanNombre').innerText = planNombre;
    document.getElementById('inputPlanTipo').value = planNombre;
    
    // Limpiar campos del formulario
    document.getElementById('formConsultaPlan').reset();
    
    // Abrir modal usando Bootstrap 5
    if (!consultaModalInstance) {
        consultaModalInstance = new bootstrap.Modal(document.getElementById('consultaPlanModal'));
    }
    consultaModalInstance.show();
}

function enviarConsultaWhatsApp(event) {
    event.preventDefault();
    
    const plan = document.getElementById('inputPlanTipo').value;
    const nombre = document.getElementById('consulta_nombre').value.trim();
    const apellido = document.getElementById('consulta_apellido').value.trim();
    const email = document.getElementById('consulta_email').value.trim();
    const consulta = document.getElementById('consulta_mensaje').value.trim();
    
    // Mensaje predefinido con los datos ingresados
    const mensaje = `${plan}\n` +
                    `Nombre: ${nombre} ${apellido}\n` +
                    `Email: ${email}\n` +
                    `Consulta: ${consulta}`;
                    
    const url = "https://wa.me/5492944552162?text=" + encodeURIComponent(mensaje);
    
    // Cerrar modal
    if (consultaModalInstance) {
        consultaModalInstance.hide();
    }
    
    // Abrir redirección en nueva pestaña
    window.open(url, '_blank');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
