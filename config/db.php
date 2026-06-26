<?php
// Configuración de la Base de Datos
$host = 'localhost';
$dbname = 'ib_trailrunning';
$username = 'root';
$password = '1234'; // Ajustado al password detectado

try {
    // Primero, conexión sin base de datos por si no existe
    $pdo_setup = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo_setup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_setup->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Ahora, conexión con la base de datos seleccionada
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error crítico de conexión a la Base de Datos: " . $e->getMessage());
}
?>
