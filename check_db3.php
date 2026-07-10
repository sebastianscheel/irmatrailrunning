<?php
require 'config/db.php';
$tables = ['rutina_asignada', 'pago_registro', 'contenido_asignado'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SHOW CREATE TABLE $t");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";
}
?>
