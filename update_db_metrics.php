<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE rutina_asignada 
                ADD COLUMN distancia_real DECIMAL(5,2) DEFAULT NULL AFTER distancia_km, 
                ADD COLUMN desnivel_real INT DEFAULT NULL AFTER distancia_real;");
    echo "Columnas distancia_real y desnivel_real agregadas exitosamente.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Las columnas ya existen.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
