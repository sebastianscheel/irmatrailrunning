<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea alumno
require_rol('alumno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = isset($_POST['monto']) ? (float)$_POST['monto'] : 0;
    $mes_pagado = isset($_POST['mes_pagado']) ? trim($_POST['mes_pagado']) : '';

    if ($monto <= 0 || empty($mes_pagado)) {
        header("Location: /alumno/reportar_pago.php?error=empty");
        exit;
    }

    // Obtener el ID del alumno
    $stmt = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        header("Location: /logout.php");
        exit;
    }
    $alumno_id = $perfil['id'];

    // Determinar la URL del host para las redirecciones
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $host;

    // Estructurar los datos para Mercado Pago
    $external_reference = $alumno_id . "::" . $mes_pagado . "::" . $monto;

    $preference_data = [
        "items" => [
            [
                "title" => "Abono Mensual Irma Trail Running (" . $mes_pagado . ")",
                "quantity" => 1,
                "unit_price" => $monto,
                "currency_id" => "ARS"
            ]
        ],
        "back_urls" => [
            "success" => $baseUrl . "/actions/pago_mp_success.php",
            "failure" => $baseUrl . "/alumno/reportar_pago.php?error=mp_fail",
            "pending" => $baseUrl . "/alumno/reportar_pago.php?error=mp_pending"
        ],
        "auto_return" => "approved",
        "external_reference" => $external_reference
    ];

    // Llamar a la API de Mercado Pago usando cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/checkouts/preferences",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . MP_ACCESS_TOKEN,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($preference_data)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $result = json_decode($response, true);
        
        // Redirigir según el modo configurado (sandbox o producción)
        $checkout_url = (defined('MP_SANDBOX_MODE') && MP_SANDBOX_MODE) ? $result['sandbox_init_point'] : $result['init_point'];
        
        header("Location: " . $checkout_url);
        exit;
    } else {
        error_log("Error al crear preferencia de Mercado Pago. Código HTTP: " . $httpCode . ", Respuesta: " . $response);
        header("Location: /alumno/reportar_pago.php?error=upload_err");
        exit;
    }
} else {
    header("Location: /alumno/reportar_pago.php");
    exit;
}
?>
