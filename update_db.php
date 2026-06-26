<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil_url VARCHAR(255) DEFAULT NULL;");
    echo "Columna agregada exitosamente.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La columna ya existe.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
