<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol('alumno');

$stmtAlumno = $pdo->prepare("SELECT id, activo FROM alumno_perfil WHERE usuario_id = ?");
$stmtAlumno->execute([$_SESSION['user_id']]);
$perfil = $stmtAlumno->fetch();
$alumno_id = $perfil ? $perfil['id'] : null;
$esta_activo = $perfil ? (int)$perfil['activo'] : 0;

if (!$alumno_id) {
    header("Location: /logout.php");
    exit;
}

if ($esta_activo !== 1 && $esta_activo !== 3) {
    header("Location: /alumno/dashboard.php");
    exit;
}

$page_title = "Mis Métricas";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes'; // semana, mes, semestre, ano

if ($rango === 'semana') {
    // Últimos 7 días
    $fecha_inicio = date('Y-m-d', strtotime('-6 days'));
    $fecha_fin = date('Y-m-d');
    
    $sql = "SELECT fecha, distancia_real, feedback_tiempo_minutos, desnivel_real 
            FROM rutina_asignada 
            WHERE alumno_id = ? AND completada = 1 AND fecha BETWEEN ? AND ?
            ORDER BY fecha ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
    $resultados = $stmt->fetchAll();

    $labels = [];
    $data_distancia = [];
    $data_tiempo = [];
    $data_desnivel = [];

    $mapa_datos = [];
    for ($i = 6; $i >= 0; $i--) {
        $f = date('Y-m-d', strtotime("-$i days"));
        $mapa_datos[$f] = ['dist' => 0, 'tiempo' => 0, 'desn' => 0];
        $labels[] = date('d/m', strtotime("-$i days"));
    }

    foreach ($resultados as $row) {
        if (isset($mapa_datos[$row['fecha']])) {
            $mapa_datos[$row['fecha']]['dist'] += (float)$row['distancia_real'];
            $mapa_datos[$row['fecha']]['tiempo'] += (float)$row['feedback_tiempo_minutos'];
            $mapa_datos[$row['fecha']]['desn'] += (int)$row['desnivel_real'];
        }
    }

    foreach ($mapa_datos as $f => $vals) {
        $data_distancia[] = $vals['dist'];
        $data_tiempo[] = $vals['tiempo'];
        $data_desnivel[] = $vals['desn'];
    }

    $chart_label = "Últimos 7 días";

} elseif ($rango === 'semestre' || $rango === '6meses') {
    // Últimos 6 meses
    $fecha_inicio = date('Y-m-01', strtotime('-5 months'));
    $fecha_fin = date('Y-m-t');

    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, 
                   SUM(distancia_real) as total_dist, 
                   SUM(feedback_tiempo_minutos) as total_tiempo, 
                   SUM(desnivel_real) as total_desn
            FROM rutina_asignada 
            WHERE alumno_id = ? AND completada = 1 AND fecha BETWEEN ? AND ?
            GROUP BY mes
            ORDER BY mes ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
    $resultados = $stmt->fetchAll();

    $labels = [];
    $data_distancia = [];
    $data_tiempo = [];
    $data_desnivel = [];

    $mapa_datos = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $mapa_datos[$m] = ['dist' => 0, 'tiempo' => 0, 'desn' => 0];
        $labels[] = date('M Y', strtotime("-$i months"));
    }

    foreach ($resultados as $row) {
        $m = $row['mes'];
        if (isset($mapa_datos[$m])) {
            $mapa_datos[$m]['dist'] = (float)$row['total_dist'];
            $mapa_datos[$m]['tiempo'] = (float)$row['total_tiempo'];
            $mapa_datos[$m]['desn'] = (int)$row['total_desn'];
        }
    }

    foreach ($mapa_datos as $m => $vals) {
        $data_distancia[] = $vals['dist'];
        $data_tiempo[] = $vals['tiempo'];
        $data_desnivel[] = $vals['desn'];
    }
    
    $chart_label = "Últimos 6 meses";

} elseif ($rango === 'ano') {
    // Último año
    $fecha_inicio = date('Y-m-01', strtotime('-11 months'));
    $fecha_fin = date('Y-m-t');

    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, 
                   SUM(distancia_real) as total_dist, 
                   SUM(feedback_tiempo_minutos) as total_tiempo, 
                   SUM(desnivel_real) as total_desn
            FROM rutina_asignada 
            WHERE alumno_id = ? AND completada = 1 AND fecha BETWEEN ? AND ?
            GROUP BY mes
            ORDER BY mes ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
    $resultados = $stmt->fetchAll();

    $labels = [];
    $data_distancia = [];
    $data_tiempo = [];
    $data_desnivel = [];

    $mapa_datos = [];
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $mapa_datos[$m] = ['dist' => 0, 'tiempo' => 0, 'desn' => 0];
        $labels[] = date('M Y', strtotime("-$i months"));
    }

    foreach ($resultados as $row) {
        $m = $row['mes'];
        if (isset($mapa_datos[$m])) {
            $mapa_datos[$m]['dist'] = (float)$row['total_dist'];
            $mapa_datos[$m]['tiempo'] = (float)$row['total_tiempo'];
            $mapa_datos[$m]['desn'] = (int)$row['total_desn'];
        }
    }

    foreach ($mapa_datos as $m => $vals) {
        $data_distancia[] = $vals['dist'];
        $data_tiempo[] = $vals['tiempo'];
        $data_desnivel[] = $vals['desn'];
    }
    
    $chart_label = "Último año";

} else {
    // Últimos 30 días
    $fecha_inicio = date('Y-m-d', strtotime('-29 days'));
    $fecha_fin = date('Y-m-d');
    
    $sql = "SELECT fecha, distancia_real, feedback_tiempo_minutos, desnivel_real 
            FROM rutina_asignada 
            WHERE alumno_id = ? AND completada = 1 AND fecha BETWEEN ? AND ?
            ORDER BY fecha ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
    $resultados = $stmt->fetchAll();

    $labels = [];
    $data_distancia = [];
    $data_tiempo = [];
    $data_desnivel = [];

    $mapa_datos = [];
    for ($i = 29; $i >= 0; $i--) {
        $f = date('Y-m-d', strtotime("-$i days"));
        $mapa_datos[$f] = ['dist' => 0, 'tiempo' => 0, 'desn' => 0];
        $labels[] = date('d/m', strtotime("-$i days"));
    }

    foreach ($resultados as $row) {
        if (isset($mapa_datos[$row['fecha']])) {
            $mapa_datos[$row['fecha']]['dist'] += (float)$row['distancia_real'];
            $mapa_datos[$row['fecha']]['tiempo'] += (float)$row['feedback_tiempo_minutos'];
            $mapa_datos[$row['fecha']]['desn'] += (int)$row['desnivel_real'];
        }
    }

    foreach ($mapa_datos as $f => $vals) {
        $data_distancia[] = $vals['dist'];
        $data_tiempo[] = $vals['tiempo'];
        $data_desnivel[] = $vals['desn'];
    }

    $chart_label = "Últimos 30 días";
}

// Resumen total del periodo
$total_dist = array_sum($data_distancia);
$total_tiem = array_sum($data_tiempo);
$total_desn = array_sum($data_desnivel);
?>

<div class="container dashboard-container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-chart-pie text-trail me-2"></i>Mis Métricas</h2>
            <p class="text-secondary mb-0">Monitorea tu volumen de entrenamiento y tu progreso a lo largo del tiempo.</p>
        </div>
        <div class="btn-group mt-3 mt-md-0">
            <a href="?rango=semana" class="btn btn-sm <?php echo $rango === 'semana' ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>">Semanal (7 d)</a>
            <a href="?rango=mes" class="btn btn-sm <?php echo $rango === 'mes' ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>">Mensual (30 d)</a>
            <a href="?rango=semestre" class="btn btn-sm <?php echo ($rango === 'semestre' || $rango === '6meses') ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>">6 Meses</a>
            <a href="?rango=ano" class="btn btn-sm <?php echo $rango === 'ano' ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>">1 Año</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-premium p-4 border-info text-center">
                <i class="fa-solid fa-route fa-2x text-info mb-2"></i>
                <h5 class="text-secondary mb-1">Volumen de Distancia</h5>
                <h3 class="text-white fw-bold mb-0"><?php echo number_format($total_dist, 1); ?> <small class="fs-6 text-muted">km</small></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium p-4 border-warning text-center">
                <i class="fa-regular fa-clock fa-2x text-warning mb-2"></i>
                <h5 class="text-secondary mb-1">Tiempo en Movimiento</h5>
                <h3 class="text-white fw-bold mb-0">
                    <?php 
                        $horas = floor($total_tiem / 60);
                        $minutos = $total_tiem % 60;
                        echo "{$horas}h {$minutos}m";
                    ?>
                </h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium p-4 border-success text-center">
                <i class="fa-solid fa-mountain fa-2x text-success mb-2"></i>
                <h5 class="text-secondary mb-1">Desnivel Positivo Total</h5>
                <h3 class="text-white fw-bold mb-0">+<?php echo number_format($total_desn, 0, ',', '.'); ?> <small class="fs-6 text-muted">mts</small></h3>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row">
        <!-- Distancia Chart -->
        <div class="col-lg-12 mb-4">
            <div class="card-premium p-4">
                <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-chart-column text-info me-2"></i>Distancia Acumulada (<?php echo $chart_label; ?>)</h5>
                <div style="height: 300px; width: 100%;">
                    <canvas id="chartDistancia"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Desnivel y Tiempo Charts -->
        <div class="col-lg-6 mb-4">
            <div class="card-premium p-4">
                <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-mountain text-success me-2"></i>Desnivel (<?php echo $chart_label; ?>)</h5>
                <div style="height: 250px; width: 100%;">
                    <canvas id="chartDesnivel"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card-premium p-4">
                <h5 class="text-white fw-bold mb-4"><i class="fa-regular fa-clock text-warning me-2"></i>Tiempo (<?php echo $chart_label; ?>)</h5>
                <div style="height: 250px; width: 100%;">
                    <canvas id="chartTiempo"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = <?php echo json_script($labels); ?>;
    
    // Configuración general de ChartJS para el tema oscuro
    Chart.defaults.color = '#a0a0a0';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';

    // Gráfico Distancia
    const ctxDist = document.getElementById('chartDistancia').getContext('2d');
    new Chart(ctxDist, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Distancia (km)',
                data: <?php echo json_script($data_distancia); ?>,
                backgroundColor: 'rgba(13, 202, 240, 0.5)',
                borderColor: 'rgba(13, 202, 240, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Gráfico Desnivel
    const ctxDesn = document.getElementById('chartDesnivel').getContext('2d');
    new Chart(ctxDesn, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Desnivel (m)',
                data: <?php echo json_script($data_desnivel); ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.2)',
                borderColor: 'rgba(25, 135, 84, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // Gráfico Tiempo
    const ctxTime = document.getElementById('chartTiempo').getContext('2d');
    new Chart(ctxTime, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Tiempo (min)',
                data: <?php echo json_script($data_tiempo); ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.2)',
                borderColor: 'rgba(255, 193, 7, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
</script>

<?php 
// Helper para inyectar JSON seguro en scripts
function json_script($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}
require_once __DIR__ . '/../includes/footer.php'; 
?>
