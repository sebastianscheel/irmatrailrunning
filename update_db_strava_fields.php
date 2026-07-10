<?php
require_once __DIR__ . '/config/db.php';

try {
    // Verificar si las columnas ya existen para evitar errores
    $checkQuery = $pdo->query("SHOW COLUMNS FROM rutina_asignada");
    $columns = $checkQuery->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('strava_activity_id', $columns)) {
        $pdo->exec("ALTER TABLE rutina_asignada ADD COLUMN strava_activity_id VARCHAR(50) DEFAULT NULL AFTER desnivel_real");
        echo "✔ Columna 'strava_activity_id' agregada exitosamente.<br>";
    } else {
        echo "ℹ La columna 'strava_activity_id' ya existe.<br>";
    }

    if (!in_array('ritmo_real', $columns)) {
        $pdo->exec("ALTER TABLE rutina_asignada ADD COLUMN ritmo_real VARCHAR(50) DEFAULT NULL AFTER strava_activity_id");
        echo "✔ Columna 'ritmo_real' agregada exitosamente.<br>";
    } else {
        echo "ℹ La columna 'ritmo_real' ya existe.<br>";
    }

    echo "<h3 style='color: green;'>¡Migración de campos de Strava finalizada con éxito!</h3>";
} catch (PDOException $e) {
    die("<h3 style='color: red;'>Error en la migración:</h3>" . $e->getMessage());
}
?>
