<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

try {
    echo "<h2>Actualizando Base de Datos - Objetivos Personalizados...</h2>";

    // 1. Agregar columna alumno_creador_id a la tabla carreras
    $sql1 = "ALTER TABLE carreras ADD COLUMN alumno_creador_id INT DEFAULT NULL";
    try {
        $pdo->exec($sql1);
        echo "✔ Columna 'alumno_creador_id' añadida a 'carreras'.<br>";
    } catch (PDOException $e) {
        echo "ℹ Columna 'alumno_creador_id' ya existe o no pudo ser agregada: " . $e->getMessage() . "<br>";
    }

    // 2. Agregar Foreign Key con ON DELETE CASCADE
    $sql2 = "ALTER TABLE carreras ADD CONSTRAINT fk_alumno_creador FOREIGN KEY (alumno_creador_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE";
    try {
        $pdo->exec($sql2);
        echo "✔ Restricción Foreign Key 'fk_alumno_creador' creada.<br>";
    } catch (PDOException $e) {
        echo "ℹ Restricción Foreign Key ya existe o no pudo ser agregada: " . $e->getMessage() . "<br>";
    }

    echo "<h3 style='color:green;'>¡Migración finalizada con éxito!</h3>";

} catch (PDOException $e) {
    die("<br><h3 style='color:red;'>Error Crítico:</h3> " . $e->getMessage());
}
?>
