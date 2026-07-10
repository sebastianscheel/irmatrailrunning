<?php
require 'config/db.php';
$email = 'eliana@test.com';
$dni = '12345678';
$stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmtCheck->execute([$email]);
if ($stmtCheck->fetch()) { echo "email_exists\n"; }
$stmtCheckDni = $pdo->prepare("SELECT id FROM alumno_perfil WHERE dni = ?");
$stmtCheckDni->execute([$dni]);
if ($stmtCheckDni->fetch()) { echo "dni_exists\n"; }
echo "Done checking";
?>
