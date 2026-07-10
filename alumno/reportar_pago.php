<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar login y rol
require_rol('alumno');

$page_title = "Reportar Pago";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Obtener ID del perfil del alumno
$stmt = $pdo->prepare("SELECT id, activo FROM alumno_perfil WHERE usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$perfil = $stmt->fetch();

if (!$perfil) {
    header("Location: /logout.php");
    exit;
}
$alumno_id = $perfil['id'];

// Mensajes de error/éxito
$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Por favor completa todos los campos requeridos."; break;
        case 'upload_err': $error_msg = "Error al subir el comprobante o procesar el pago. Inténtalo nuevamente."; break;
        case 'invalid_type': $error_msg = "Formato no permitido. Solo se aceptan imágenes (JPG, PNG) o PDF."; break;
        case 'invalid_size': $error_msg = "El tamaño máximo permitido para el comprobante es 5MB."; break;
        case 'move_err': $error_msg = "Error al guardar el archivo en el servidor."; break;
        case 'db': $error_msg = "Error de base de datos al registrar el reporte."; break;
        case 'mp_fail': $error_msg = "El pago a través de Mercado Pago fue cancelado o falló. Inténtalo nuevamente."; break;
        case 'mp_pending': $error_msg = "El pago de Mercado Pago está pendiente de procesamiento por la plataforma."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'pago_ok': $success_msg = "¡Comprobante de pago subido correctamente! Tu entrenador revisará el reporte y activará tu cuenta."; break;
    }
}

// Generar meses seleccionables (Mes anterior, Mes actual, Mes siguiente)
$meses_es = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$opciones_meses = [];
for ($i = -1; $i <= 1; $i++) {
    $time = strtotime("$i month");
    $val = date('Y-m', $time);
    $mes_num = date('m', $time);
    $ano = date('Y', $time);
    $label = $meses_es[$mes_num] . " " . $ano;
    $opciones_meses[$val] = $label;
}
?>

<div class="container dashboard-container">
    <div class="row">
        <!-- Columna de Datos de Transferencia y Mercado Pago -->
        <div class="col-lg-5 mb-4">
            
            <!-- TARJETA: DATOS DE TRANSFERENCIA MANUAL -->
            <div class="card-premium p-4 mb-4">
                <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-wallet text-warning me-2"></i>Datos de Transferencia</h5>
                <p class="text-secondary small">Realiza tu transferencia a cualquiera de las siguientes cuentas y luego adjunta el comprobante abajo:</p>
                
                <div class="p-3 bg-dark rounded border border-secondary mb-3">
                    <h6 class="text-white fw-bold mb-2">Cuenta 1: Mercado Pago</h6>
                    <ul class="list-unstyled text-secondary small mb-2">
                        <li><strong>CVU:</strong> <span id="cvu_mp">0000003100012345678901</span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2" style="font-size: 0.65rem;" onclick="copiarTexto('0000003100012345678901', this)"><i class="fa-regular fa-copy"></i> Copiar</button>
                        </li>
                        <li class="mt-1"><strong>Alias:</strong> <span id="alias_mp">ib.trailrunning.mp</span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2" style="font-size: 0.65rem;" onclick="copiarTexto('ib.trailrunning.mp', this)"><i class="fa-regular fa-copy"></i> Copiar</button>
                        </li>
                        <li class="mt-1"><strong>Titular:</strong> Sebastian IB</li>
                    </ul>
                    
                    <!-- QR Accordion -->
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-warning w-100 py-1.5" type="button" data-bs-toggle="collapse" data-bs-target="#collapseQR" aria-expanded="false" aria-controls="collapseQR">
                            <i class="fa-solid fa-qrcode me-1"></i> Mostrar QR Mercado Pago
                        </button>
                        <div class="collapse mt-2" id="collapseQR">
                            <div class="card card-body bg-dark border-secondary p-2 text-center">
                                <img src="/assets/img/qr_pago.png" alt="QR Mercado Pago" class="img-fluid rounded mx-auto" style="max-height: 200px; object-fit: contain;">
                                <small class="text-muted mt-2 d-block">Escanea con la app de Mercado Pago para abonar</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-dark rounded border border-secondary">
                    <h6 class="text-white fw-bold mb-2">Cuenta 2: Banco Galicia</h6>
                    <ul class="list-unstyled text-secondary small mb-0">
                        <li><strong>CBU:</strong> <span>0070123420000001234567</span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2" style="font-size: 0.65rem;" onclick="copiarTexto('0070123420000001234567', this)"><i class="fa-regular fa-copy"></i> Copiar</button>
                        </li>
                        <li class="mt-1"><strong>Alias:</strong> <span>ib.trail.galicia</span>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2" style="font-size: 0.65rem;" onclick="copiarTexto('ib.trail.galicia', this)"><i class="fa-regular fa-copy"></i> Copiar</button>
                        </li>
                        <li class="mt-1"><strong>Titular:</strong> Sebastian IB</li>
                    </ul>
                </div>
            </div>

            <!-- TARJETA: PAGO ONLINE DIRECTO CON MERCADO PAGO -->
            <div class="card-premium p-4 mb-4">
                <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-credit-card text-warning me-2"></i>Pago Directo con Mercado Pago</h5>
                <p class="text-secondary small mb-3">Paga online de forma inmediata. Al finalizar, tu cuenta se activará automáticamente sin demoras.</p>
                
                <form action="/actions/crear_preferencia_mp.php" method="POST">
                    <div class="mb-3">
                        <label for="mp_mes_pagado" class="form-label form-label-custom">Mes a Abonar</label>
                        <select name="mes_pagado" id="mp_mes_pagado" class="form-select form-control-custom" required>
                            <?php foreach ($opciones_meses as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($val === date('Y-m')) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="mp_monto" class="form-label form-label-custom">Monto a Pagar ($)</label>
                        <input type="number" name="monto" id="mp_monto" class="form-control form-control-custom" value="22000" min="1" required>
                    </div>

                    <button type="submit" class="btn w-100 py-2.5 fw-bold text-white" style="background-color: #009ee3; border: none; border-radius: 8px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        <i class="fa-solid fa-wallet me-2"></i>Pagar con Mercado Pago
                    </button>
                </form>
            </div>

            <!-- TARJETA: REPORTAR TRANSFERENCIA MANUAL -->
            <div class="card-premium p-4">
                <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-file-invoice-dollar text-warning me-2"></i>Adjuntar Comprobante Manual</h5>
                <p class="text-secondary small mb-3">Si ya transferiste manualmente, sube tu comprobante para que lo apruebe el entrenador.</p>
                
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

                <form action="/actions/reportar_pago_action.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="mes_pagado" class="form-label form-label-custom">Mes a Abonar</label>
                        <select name="mes_pagado" id="mes_pagado" class="form-select form-control-custom" required>
                            <?php foreach ($opciones_meses as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($val === date('Y-m')) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="monto" class="form-label form-label-custom">Monto Transferido ($)</label>
                        <input type="number" name="monto" id="monto" class="form-control form-control-custom" placeholder="Ej: 22000" min="1" required>
                    </div>

                    <div class="mb-4">
                        <label for="comprobante" class="form-label form-label-custom">Comprobante de Pago (Imagen/PDF)</label>
                        <input type="file" name="comprobante" id="comprobante" class="form-control form-control-custom" accept="image/png, image/jpeg, image/jpg, application/pdf" required>
                    </div>

                    <button type="submit" class="btn btn-trail w-100 py-2.5"><i class="fa-solid fa-upload me-2"></i>Enviar Reporte Manual</button>
                </form>
            </div>
        </div>

        <!-- Columna de Historial de Pagos -->
        <div class="col-lg-7 mb-4">
            <div class="card-premium p-4 h-100">
                <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-history text-warning me-2"></i>Historial de Pagos</h5>
                <p class="text-secondary small mb-4">Detalle de los pagos reportados y su estado de verificación actual.</p>

                <?php
                // Obtener historial de pagos del alumno
                $stmtPagos = $pdo->prepare("
                    SELECT * FROM pago_registro 
                    WHERE alumno_id = ? 
                    ORDER BY fecha_reporte DESC
                ");
                $stmtPagos->execute([$alumno_id]);
                $pagos = $stmtPagos->fetchAll();
                ?>

                <?php if (count($pagos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Mes Pagado</th>
                                    <th class="border-secondary py-3">Monto</th>
                                    <th class="border-secondary py-3 text-center">Comprobante</th>
                                    <th class="border-secondary py-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $p): 
                                    $mes_array = explode('-', $p['mes_pagado']);
                                    $mes_nombre = isset($meses_es[$mes_array[1]]) ? $meses_es[$mes_array[1]] . " " . $mes_array[0] : $p['mes_pagado'];
                                ?>
                                    <tr>
                                        <td class="border-secondary py-3 fw-bold text-white"><?php echo $mes_nombre; ?></td>
                                        <td class="border-secondary py-3">$<?php echo number_format($p['monto'], 2, ',', '.'); ?></td>
                                        <td class="border-secondary py-3 text-center">
                                            <a href="<?php echo htmlspecialchars($p['comprobante_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm" title="Ver Comprobante">
                                                <i class="fa-solid fa-eye"></i> Ver
                                            </a>
                                        </td>
                                        <td class="border-secondary py-3 text-end">
                                            <?php if ($p['estado'] === 'pendiente'): ?>
                                                <span class="badge bg-warning text-dark px-2.5 py-1.5"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>
                                            <?php elseif ($p['estado'] === 'aprobado'): ?>
                                                <span class="badge bg-success text-white px-2.5 py-1.5"><i class="fa-solid fa-check-circle me-1"></i>Aprobado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white px-2.5 py-1.5"><i class="fa-solid fa-times-circle me-1"></i>Rechazado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 rounded border border-secondary text-center text-secondary small">
                        <i class="fa-solid fa-receipt fa-3x mb-3 text-muted"></i>
                        <p class="mb-0">No has registrado ningún reporte de pago todavía.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copiarTexto(texto, boton) {
    navigator.clipboard.writeText(texto).then(() => {
        const contenidoOriginal = boton.innerHTML;
        boton.innerHTML = '<i class="fa-solid fa-check text-success"></i> Copiado';
        boton.classList.remove('btn-outline-secondary');
        boton.classList.add('btn-outline-success');
        setTimeout(() => {
            boton.innerHTML = contenidoOriginal;
            boton.classList.remove('btn-outline-success');
            boton.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(err => {
        console.error('Error al copiar el texto: ', err);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
