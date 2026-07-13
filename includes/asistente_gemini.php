<?php
// Evitar acceso directo
if (count(get_included_files()) == 1) {
    http_response_code(403);
    exit("Acceso denegado.");
}

require_once __DIR__ . '/../config/api_keys.php';

class AsistenteGemini {
    private $apiKey;
    private $model = 'gemini-1.5-flash';

    public function __construct() {
        if (!defined('GEMINI_API_KEY')) {
            throw new Exception("API Key de Gemini no configurada.");
        }
        $this->apiKey = GEMINI_API_KEY;
    }

    /**
     * Envía un prompt a Gemini y retorna el resultado en JSON.
     */
    public function generarSesion($fase, $tipo_sesion, $nivel, $volumen, $ritmo, $terreno, $carrera = '') {
        $prompt = "Actúa como un preparador físico experto en Trail Running. Genera un entrenamiento de running profesional y detallado.
Parámetros:
- Fase de periodización: $fase
- Tipo de sesión: $tipo_sesion
- Nivel del alumno: $nivel
- Distancia / Volumen sugerido: $volumen km
- Ritmo estimado sugerido: $ritmo
- Terreno: $terreno";

        if (!empty($carrera)) {
            $prompt .= "\n- Carrera objetivo del alumno: $carrera";
        }

        $prompt .= "\n\nResponde estrictamente en formato JSON utilizando el siguiente esquema de campos:
{
  \"titulo\": \"Un título motivador y descriptivo (ej. 'Pasadas cortas en cuesta' o 'Fondo aeróbico con D+')\",
  \"descripcion\": \"La descripción clara y completa del entrenamiento estructurado en tres partes: 1) Calentamiento / Movilidad, 2) Trabajo Principal (series, descansos, cuestas, distancias o tiempos específicos), 3) Vuelta a la calma / Elongación. Debe ser realista y efectivo para el nivel especificado.\",
  \"distancia_km\": $volumen,
  \"ritmo_sugerido\": \"$ritmo\",
  \"terreno\": \"$terreno\",
  \"tipo_sesion\": \"$tipo_sesion\"
}

IMPORTANTE: Responde ÚNICAMENTE con el objeto JSON crudo, sin etiquetas markdown de bloque de código (como ```json) ni textos adicionales fuera del JSON.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->model . ":generateContent?key=" . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error cURL de Gemini API: " . $error);
        }
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("Respuesta errónea de Gemini (HTTP $http_code): " . $response);
            throw new Exception("Error de respuesta de Gemini API (HTTP $http_code)");
        }

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($result['candidates'][0]['content']['parts'][0]['text']);
            
            // Decodificar el texto obtenido (debería ser el JSON limpio)
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // Reintentar limpiando posibles marcas
            $text_clean = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text_clean = preg_replace('/\s*```$/', '', $text_clean);
            $decoded_clean = json_decode(trim($text_clean), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded_clean;
            }
            
            throw new Exception("Gemini no retornó un JSON estructurado válido: " . $text);
        }

        throw new Exception("Estructura de respuesta inesperada de Gemini API: " . $response);
    }

    /**
     * Genera la semana completa estructurada de entrenamientos adaptándose a directivas personalizadas.
     */
    public function generarSemana($semana, $total_semanas, $nivel, $fase, $volumen, $dias_seleccionados, $carrera = '', $directivas = '', $config_ia = []) {
        $cantidad_dias = count($dias_seleccionados);
        $nombres_dias = [1=>'Lunes', 2=>'Martes', 3=>'Miércoles', 4=>'Jueves', 5=>'Viernes', 6=>'Sábado', 7=>'Domingo'];
        $lista_dias = implode(', ', array_map(function($d) use ($nombres_dias) { return "$nombres_dias[$d] ($d)"; }, $dias_seleccionados));
        
        $disciplina = $config_ia['disciplina'] ?? 'Trail Running';
        $rol = $config_ia['rol_entrenador'] ?? 'preparador físico experto';
        $tipos_sesion = $config_ia['tipos_sesion'] ?? 'Fondo|Fuerza|Pasadas|Regenerativo|Cuestas';
        $estructura = $config_ia['estructura_descripcion'] ?? "Entrada en calor:[texto]\nBloque principal:[texto]\nVuelta a la calma:[texto]";
        $tono = $config_ia['tono_respuesta'] ?? 'Profesional y motivador';

        $prompt = "Actúa como un $rol en $disciplina. Tu tono debe ser $tono. Genera la planificación completa de entrenamientos para la semana $semana de un macrociclo de $total_semanas semanas.
Fase actual de periodización: $fase
Nivel del corredor: $nivel
Volumen semanal sugerido: $volumen km
Días exactos de entrenamiento a programar: $lista_dias.

Directivas de estructura o preferencias específicas a seguir estrictamente:
" . (empty($directivas) ? "Estructura la semana de forma balanceada." : $directivas);

        if (!empty($carrera)) {
            $prompt .= "\nCarrera objetivo: $carrera";
        }

        $prompt .= "\n\nDEBES generar exactamente $cantidad_dias rutinas correspondientes a los días exactos mencionados ($lista_dias) y distribuir los $volumen km totales entre ellos. \n\nESTRUCTURA DE LA DESCRIPCIÓN:\nLa clave 'descripcion' DEBE estar estructurada obligatoriamente usando saltos de línea reales y estos encabezados:\n$estructura\n\nResponde estrictamente en formato JSON utilizando el siguiente esquema:
{
  \"rutinas\": [
    {
      \"dia\": 2, // El número de día, DEBE ser uno de la lista proporcionada.
      \"tipo_sesion\": \"$tipos_sesion\",
      \"titulo\": \"Título motivador (ej: Fondo Progresivo 10k)\",
      \"descripcion\": \"(Usa estrictamente la estructura solicitada)\",
      \"distancia_km\": 10.5, // Kilómetros de esta sesión
      \"ritmo_sugerido\": \"Ritmo sugerido (ej. '5:15 - 5:45 min/km' o 'Zona 2')\",
      \"terreno\": \"Plano|Pista|Montaña|Técnico\"
    }
  ]
}

IMPORTANTE: Responde ÚNICAMENTE con el objeto JSON crudo, sin etiquetas markdown de bloque de código (como ```json) ni textos adicionales fuera del JSON.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->model . ":generateContent?key=" . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error cURL de Gemini API: " . $error);
        }
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("Respuesta errónea de Gemini (HTTP $http_code): " . $response);
            throw new Exception("Error de respuesta de Gemini API (HTTP $http_code)");
        }

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($result['candidates'][0]['content']['parts'][0]['text']);
            
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            $text_clean = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text_clean = preg_replace('/\s*```$/', '', $text_clean);
            $decoded_clean = json_decode(trim($text_clean), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded_clean;
            }
            
            throw new Exception("Gemini no retornó un JSON estructurado válido para la semana: " . $text);
        }

        throw new Exception("Estructura de respuesta inesperada de Gemini API: " . $response);
    }
}
