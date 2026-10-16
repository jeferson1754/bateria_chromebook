<?php

class TuyaAPI
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;

    public function __construct($clientId, $clientSecret, $region = 'us')
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        // URL para la región de América (Western America Data Center)
        $this->baseUrl = ($region === 'us') ? 'https://openapi.tuyaus.com' : 'https://openapi.tuyacn.com';
    }

    /**
     * Obtiene el Access Token dinámico de Tuya
     */
    private function getAccessToken()
    {
        $method = 'GET';
        $path = '/v1.0/token?grant_type=1';
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        $stringToSign = $this->clientId . $timestamp . $method . "\n" .
            hash('sha256', '') . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256"
        ];

        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // --- CONFIGURACIONES CLAVE PARA EVITAR ERRORES EN XAMPP ---
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // FUERZA HTTP/1.1 EVITANDO EL PROTOCOL_ERROR
        // ---------------------------------------------------------

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        /*
        echo "<div style='background:#e2f0d9; padding:10px; border:1px solid #c8e6c9; margin:10px 0; font-family:monospace;'>";
        echo "<b>[DIAGNÓSTICO cURL CORREGIDO]:</b> HTTP Code: $httpCode | Error cURL: " . ($curlError ? $curlError : "Ninguno") . "<br>";
        echo "Respuesta: " . htmlspecialchars($response);
        echo "</div>";
        */

        $json = json_decode($response, true);
        return $json['result']['access_token'] ?? null;
    }

    /**
     * Envía la cuenta regresiva en segundos al enchufe (countdown_1)
     */
    public function programarApagado($deviceId, int $segundos)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No se pudo obtener el Token de Tuya'];
        }

        $method = 'POST';
        $path = "/v1.0/devices/{$deviceId}/commands";
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        // Estructura del comando para SmartLife
        $bodyData = [
            'commands' => [
                [
                    'code' => 'countdown_1',
                    'value' => $segundos
                ]
            ]
        ];
        $bodyJson = json_encode($bodyData);
        $bodyHash = hash('sha256', $bodyJson);

        // Firma para enviar comandos con Token
        $stringToSign = $this->clientId . $token . $timestamp . $method . "\n" .
            $bodyHash . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "access_token: {$token}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Enciende el enchufe y programa su apagado en un determinado número de segundos (en una sola llamada API)
     */
    public function encenderYProgramarApagado($deviceId, int $segundos)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No se pudo obtener el Token de Tuya'];
        }

        $method = 'POST';
        $path = "/v1.0/devices/{$deviceId}/commands";
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        // Enviamos ambos comandos combinados en la misma llamada
        $bodyData = [
            'commands' => [
                [
                    'code' => 'switch_1',
                    'value' => true  // 1. Enciende el enchufe
                ],
                [
                    'code' => 'countdown_1',
                    'value' => $segundos // 2. Le fija el temporizador de apagado
                ]
            ]
        ];

        $bodyJson = json_encode($bodyData);
        $bodyHash = hash('sha256', $bodyJson);

        $stringToSign = $this->clientId . $token . $timestamp . $method . "\n" .
            $bodyHash . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "access_token: {$token}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function verificarAlarmasActivas($deviceId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['tiene_alarma' => false];
        }

        $method = 'GET';
        // Endpoint específico de Tuya para consultar timers/programaciones horarias de la app
        $path = "/v1.0/devices/{$deviceId}/timers";
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        $stringToSign = $this->clientId . $token . $timestamp . $method . "\n" .
            hash('sha256', '') . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "access_token: {$token}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);

        // Validamos si la API devolvió timers activos
        if (isset($json['success']) && $json['success'] === true && !empty($json['result'])) {
            // Dependiendo de la estructura de Tuya, las rutinas vienen en un arreglo dentro del resultado
            $timers = $json['result'] ?? [];

            if (count($timers) > 0) {
                return [
                    'tiene_alarma' => true,
                    'tipo' => 'horario_fijo',
                    'detalles' => $timers
                ];
            }
        }

        return ['tiene_alarma' => false];
    }

    /**
     * Valida la conexión consultando el estado actual del enchufe
     */

    public function probarConexion($deviceId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'exito' => false,
                'mensaje' => '❌ Error crítico: No se pudo obtener el Token de acceso.'
            ];
        }

        $method = 'GET';
        $path = "/v1.0/devices/{$deviceId}";
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        $stringToSign = $this->clientId . $token . $timestamp . $method . "\n" .
            hash('sha256', '') . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "access_token: {$token}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256"
        ];

        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // ----------------------------------------------------

        $json = json_decode($response, true);

        return [
            'exito' => ($json['success'] ?? false),
            'respuesta_completa' => $json
        ];
    }

    public function obtenerEstadoTemporizador($deviceId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['exito' => false, 'mensaje' => 'Error de token'];
        }

        $method = 'GET';
        $path = "/v1.0/devices/{$deviceId}";
        $timestamp = sprintf('%.0f', microtime(true) * 1000);

        $stringToSign = $this->clientId . $token . $timestamp . $method . "\n" .
            hash('sha256', '') . "\n" .
            "\n" .
            $path;

        $sign = strtoupper(hash_hmac('sha256', $stringToSign, $this->clientSecret));

        $headers = [
            "client_id: {$this->clientId}",
            "access_token: {$token}",
            "sign: {$sign}",
            "t: {$timestamp}",
            "sign_method: HMAC-SHA256"
        ];

        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);

        if ($json['success'] ?? false) {
            $statusList = $json['result']['status'] ?? [];
            $countdownSeconds = 0;
            $enendido = false;

            // Recorremos los estados del enchufe para encontrar el temporizador y el switch
            foreach ($statusList as $item) {
                if ($item['code'] === 'countdown_1') {
                    $countdownSeconds = $item['value']; // Segundos restantes para el apagado
                }
                if ($item['code'] === 'switch_1') {
                    $enendido = $item['value']; // true o false
                }
            }

            return [
                'exito' => true,
                'encendido' => $enendido,
                'countdown_seconds' => $countdownSeconds,
                // Si hay segundos restantes, calculamos exactamente a qué hora ocurrirá el apagado
                'hora_apagado_estimada' => ($countdownSeconds > 0) ? date('H:i:s', time() + $countdownSeconds) : null
            ];
        }

        return ['exito' => false, 'mensaje' => 'No se pudo obtener el estado de la API'];
    }
}

function enviarAlertaTelegram($mensaje, $tokenBot, $chatId, $maxIntentos = 3)
{
    $url = "https://api.telegram.org/bot{$tokenBot}/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $mensaje,
        'parse_mode' => 'HTML'
    ];

    $intentos = 0;
    $enviadoExitosamente = false;
    $response = false;
    $errorCurl = '';
    $httpCode = 0;

    while ($intentos < $maxIntentos && !$enviadoExitosamente) {
        $intentos++;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Límite de 10 segundos por intento

        $response = curl_exec($ch);
        $errorCurl = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Verificamos si cURL no tuvo errores y Telegram respondió con un código HTTP 200 (Éxito)
        if (!$errorCurl && $httpCode === 200) {
            $enviadoExitosamente = true;
        } else {
            // Si falla, esperamos 1 segundo antes de volver a intentar (excepto en el último intento)
            if ($intentos < $maxIntentos) {
                sleep(1);
            }
        }
    }

    // Si después de los reintentos no se pudo enviar, guardamos el log y retornamos el error para mostrarlo en la web
    if (!$enviadoExitosamente) {
        $detalleError = $errorCurl ? $errorCurl : "Código HTTP: {$httpCode} - Respuesta: {$response}";
        $mensajeLog = "❌ [ALERTA TELEGRAM FALLIDA] No se pudo enviar el mensaje tras {$maxIntentos} intentos. Motivo: " . $detalleError;

        error_log($mensajeLog);

        // Retornamos el error formateado para que puedas imprimirlo en tu interfaz web
        return [
            'success' => false,
            'error' => "No se pudo notificar por Telegram. Detalle: " . $detalleError
        ];
    }

    return ['success' => true];
}
