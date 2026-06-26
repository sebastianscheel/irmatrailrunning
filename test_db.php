<?php
$passwords = ['', 'root', '1234', '123456', 'admin'];
foreach ($passwords as $pass) {
    try {
        $pdo = new PDO('mysql:host=localhost', 'root', $pass);
        echo "MySQL Connection OK with password: '$pass'\n";
        exit;
    } catch (PDOException $e) {
        // failed
    }
}
echo "Failed all passwords\n";
?>
