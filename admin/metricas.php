<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Métricas de Alumnos";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$entrenador_id = $_SESSION['user_id'];
$es_admin = ($_SESSION['user_rol'] === 'admin');

// 1. Obtener la lista de alumnos según permisos
if ($es_admin) {
    $stmtList = $pdo->query("
        SELECT ap.id, u.nombre, u.apellido 
        FROM alumno_perfil ap 
        JOIN usuarios u ON ap.usuario_id = u.id 
        ORDER BY u.apellido ASC, u.nombre ASC
    ");
} else {
    // Si es entrenador total puede ver a todos, si es limitado solo a los suyos
    if ($_SESSION['user_rol'] === 'entrenador_total') {
        $stmtList = $pdo->query("
            SELECT ap.id, u.nombre, u.apellido 
            FROM alumno_perfil ap 
            JOIN usuarios u ON ap.usuario_id = u.id 
            ORDER BY u.apellido ASC, u.nombre ASC
        ");
    } else {
        $stmtList = $pdo->prepare("
            SELECT ap.id, u.nombre, u.apellido 
            FROM alumno_perfil ap 
            JOIN usuarios u ON ap.usuario_id = u.id 
            WHERE ap.entrenador_id = ?
            ORDER BY u.apellido ASC, u.nombre ASC
        ");
        $stmtList->execute([$entrenador_id]);
    }
}
$alumnos_lista = $stmtList->fetchAll();

$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes'; // semana, mes, semestre, ano

$labels = [];
$data_distancia = [];
$data_tiempo = [];
$data_desnivel = [];
$total_dist = 0;
$total_tiem = 0;
$total_desn = 0;
$chart_label = "";

if ($alumno_id > 0) {
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

    $total_dist = array_sum($data_distancia);
    $total_tiem = array_sum($data_tiempo);
    $total_desn = array_sum($data_desnivel);
}
?>

<div class="container dashboard-container mt-4">
    
    <div class="mb-4">
        <h2 class="text-white fw-bold"><i class="fa-solid fa-chart-area text-warning me-2"></i>Métricas de Alumnos</h2>
        <p class="text-secondary mb-0">Selecciona un alumno para visualizar su volumen y progresión de entrenamiento.</p>
    </div>

    <!-- SELECTOR DE ALUMNO Y RANGO -->
    <div class="card-premium p-4 mb-4 border border-secondary">
        <form method="GET" action="metricas.php" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="alumno_id" class="form-label text-secondary small fw-bold">Seleccionar Corredor</label>
                <select name="alumno_id" id="alumno_id" class="form-select form-select-custom bg-dark text-white border-secondary" required>
                    <option value="">-- Elige un alumno --</option>
                    <?php foreach ($alumnos_lista as $al): ?>
                        <option value="<?php echo $al['id']; ?>" <?php echo $alumno_id == $al['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($al['apellido'] . ', ' . $al['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="rango" class="form-label text-secondary small fw-bold">Periodo</label>
                <select name="rango" id="rango" class="form-select form-select-custom bg-dark text-white border-secondary">
                    <option value="semana" <?php echo $rango === 'semana' ? 'selected' : ''; ?>>Semanal (7 días)</option>
                    <option value="mes" <?php echo $rango === 'mes' ? 'selected' : ''; ?>>Mensual (30 días)</option>
                    <option value="semestre" <?php echo ($rango === 'semestre' || $rango === '6meses') ? 'selected' : ''; ?>>Últimos 6 Meses</option>
                    <option value="ano" <?php echo $rango === 'ano' ? 'selected' : ''; ?>>Último Año</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-trail w-100"><i class="fa-solid fa-search me-2"></i>Ver Métricas</button>
            </div>
        </form>
    </div>

    <?php if ($alumno_id > 0): ?>
        
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
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const labels = <?php echo json_encode($labels); ?>;
            
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
                        data: <?php echo json_encode($data_distancia); ?>,
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
                        data: <?php echo json_encode($data_desnivel); ?>,
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
                        data: <?php echo json_encode($data_tiempo); ?>,
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

    <?php else: ?>
        <div class="text-center py-5 text-secondary">
            <i class="fa-solid fa-chart-line fa-3x mb-3 text-muted"></i>
            <p class="mb-0">Selecciona un alumno para ver sus métricas.</p>
        </div>
    <?php endif; ?>
</div>

<?php 
// Helper
if(!function_exists('json_script')) {
    function json_script($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }
}
require_once __DIR__ . '/../includes/footer.php'; 
?>
