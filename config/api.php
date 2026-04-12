<?php
/**
 * Client PHP — Cissokho WhatsApp API (Baileys)
 *
 * Toute la logique d'envoi passe par ce fichier.
 * L'URL de l'API et le token sont lus depuis .env.
 */

require_once __DIR__ . '/env.php';

function getApiBase(): string {
    return rtrim($_ENV['WHATSAPP_API_URL'] ?? 'http://localhost:3000', '/');
}

function getApiToken(): string {
    return $_ENV['API_TOKEN'] ?? '';
}

/**
 * Appel générique vers l'API WhatsApp locale.
 *
 * @param  string $endpoint  Ex : '/api/v1/message'
 * @param  array  $body      Payload JSON
 * @return array{ok:bool, status:int, data:array, error:string}
 */
function callWaApi(string $endpoint, array $body): array {
    $url   = getApiBase() . $endpoint;
    $token = getApiToken();

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'cURL : ' . $curlError];
    }

    $data = json_decode($response, true) ?? [];
    $ok   = ($httpCode === 200 || $httpCode === 201) && ($data['ok'] ?? false);

    return ['ok' => $ok, 'status' => $httpCode, 'data' => $data, 'error' => $data['error'] ?? ''];
}

// ── Fonctions d'envoi ─────────────────────────────────────────

/**
 * Envoyer un message texte WhatsApp.
 */
function sendMessage(string $to, string $text): bool {
    $result = callWaApi('/api/v1/message', ['to' => $to, 'text' => $text]);
    if (!$result['ok']) {
        error_log('[WA API] sendMessage échoué → ' . $result['error']);
    }
    return $result['ok'];
}

/**
 * Envoyer un document (chemin absolu sur le même serveur).
 */
function sendDocument(
    string $to,
    string $filePath,
    string $filename = '',
    string $caption  = '',
    string $mimetype = ''
): bool {
    $result = callWaApi('/api/v1/document', [
        'to'       => $to,
        'filePath' => $filePath,
        'filename' => $filename,
        'caption'  => $caption,
        'mimetype' => $mimetype,
    ]);
    if (!$result['ok']) {
        error_log('[WA API] sendDocument échoué → ' . $result['error']);
    }
    return $result['ok'];
}

/**
 * Envoyer un document depuis une URL publique.
 */
function sendDocumentFromUrl(
    string $to,
    string $url,
    string $filename = '',
    string $caption  = ''
): bool {
    $result = callWaApi('/api/v1/document-url', [
        'to'       => $to,
        'url'      => $url,
        'filename' => $filename,
        'caption'  => $caption,
    ]);
    if (!$result['ok']) {
        error_log('[WA API] sendDocumentFromUrl échoué → ' . $result['error']);
    }
    return $result['ok'];
}

/**
 * Vérifier si l'API WhatsApp est connectée.
 */
function isWaConnected(): bool {
    $url   = getApiBase() . '/api/v1/status';
    $token = getApiToken();

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true) ?? [];
    return (bool)($data['connected'] ?? false);
}
