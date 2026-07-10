<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestión de entrenamiento y comunidad de IB Trailrunning. Planifica tus carreras de montaña con profesionales.">
    <title><?php echo isset($page_title) ? $page_title . " - IB Trailrunning" : "IB Trailrunning - Entrenamiento de Montaña"; ?></title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/img/logo.jpeg" type="image/jpeg">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Premium CSS -->
    <link href="/assets/css/styles.css" rel="stylesheet">
</head>
<?php
$is_landing = (basename($_SERVER['PHP_SELF']) == 'index.php' || basename($_SERVER['PHP_SELF']) == 'login.php');
$body_class = $is_landing ? '' : 'theme-light';
?>
<body class="<?php echo $body_class; ?>">
