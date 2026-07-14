<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE configuracion_ia ADD COLUMN proveedor_ia VARCHAR(50) DEFAULT 'Gemini'");
    $pdo->exec("ALTER TABLE configuracion_ia ADD COLUMN api_key VARCHAR(255) DEFAULT NULL");
    echo "Columnas proveedor_ia y api_key agregadas correctamente.";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
