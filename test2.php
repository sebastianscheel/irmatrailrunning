<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT * FROM entrenamientos_individuales LIMIT 2");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
