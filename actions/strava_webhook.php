<?php
// actions/strava_webhook.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/strava_sync_helper.php';

// Definir el token de verificación acordado para Strava Webhooks
define('STRAVA_WEBHOOK_VERIFY_TOKEN', 'irma_trailrunning_verify_token_2026');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 1. Verificación de la suscripción (Handshake de Strava)
    // Strava realiza una llamada GET a este endpoint para validar el callback
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === STRAVA_WEBHOOK_VERIFY_TOKEN) {
        header('Content-Type: application/json');
        echo json_encode(["hub.challenge" => $challenge]);
        exit;
    } else {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
} elseif ($method === 'POST') {
    // 2. Recepción de eventos
    // Strava envía un JSON con la descripción del cambio
    $rawInput = file_get_contents('php://input');
    $event = json_decode($rawInput, true);

    if ($event) {
        $objectType = $event['object_type'] ?? '';
        $aspectType = $event['aspect_type'] ?? '';
        $ownerId = $event['owner_id'] ?? ''; // ID del atleta de Strava
        
        // Solo nos interesan actividades nuevas o modificadas
        if ($objectType === 'activity' && ($aspectType === 'create' || $aspectType === 'update')) {
            // Buscar al alumno correspondiente mediante su athlete_id
            $stmt = $pdo->prepare("SELECT alumno_id FROM strava_tokens WHERE athlete_id = ?");
            $stmt->execute([$ownerId]);
            $alumno_id = $stmt->fetchColumn();

            if ($alumno_id) {
                // Sincronizar las actividades para este alumno
                sincronizarActividadesStrava($alumno_id, $pdo);
            }
        }
    }
    
    // Responder inmediatamente a Strava con un HTTP 200 para confirmar recepción del evento
    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit;
} else {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}
