<?php
require 'config/db.php';
$stmt=$pdo->query('SELECT * FROM usuarios'); 
print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); 
$stmt2=$pdo->query('SELECT * FROM alumno_perfil'); 
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC)); 
?>
