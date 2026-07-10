<?php
// Configurar zona horaria por defecto para la aplicación
date_default_timezone_set('America/Argentina/Buenos_Aires');

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
    
    // Sincronizar zona horaria de MySQL con Argentina
    $pdo->exec("SET time_zone = '-03:00'");
    
    // Verificación automática de Falta de Pago (activo = 2)
    // Se ejecuta automáticamente después del día 7 de cada mes si no se registra pago pendiente o aprobado
    if ((int)date('j') > 7) {
        try {
            $current_month = date('Y-m');
            $stmtUpdate = $pdo->prepare("
                UPDATE alumno_perfil ap
                SET ap.activo = 2
                WHERE ap.activo = 1
                  AND NOT EXISTS (
                      SELECT 1 
                      FROM pago_registro pr 
                      WHERE pr.alumno_id = ap.id 
                        AND pr.mes_pagado = ? 
                        AND pr.estado IN ('pendiente', 'aprobado')
                  )
            ");
            $stmtUpdate->execute([$current_month]);
        } catch (Exception $ex) {
            error_log("Error en verificación automática de falta de pago: " . $ex->getMessage());
        }
    }
} catch (PDOException $e) {
    die("Error crítico de conexión a la Base de Datos: " . $e->getMessage());
}
?>
