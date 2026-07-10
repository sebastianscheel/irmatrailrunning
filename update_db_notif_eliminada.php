<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE notificaciones 
                ADD COLUMN eliminada TINYINT DEFAULT 0 AFTER leido;");
    echo "Columna 'eliminada' agregada exitosamente a la tabla notificaciones.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna 'eliminada' ya existe.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
