<?php
$pdo = new PDO('mysql:host=localhost;dbname=ib_trailrunning', 'root', '1234');
$stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE email = ?');
$stmt->execute([password_hash('123456', PASSWORD_DEFAULT), 'elisabeltorres1989@gmail.com']);
echo "Password updated successfully.\n";
