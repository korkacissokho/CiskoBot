<?php

// Chargement des variables depuis .env
$envFile = __DIR__ . '/.env';
$env = [];

if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $env[$key] = $value;
    }
}

$apiKey   = $env['API_KEY']   ?? '';
$apiToken = $env['API_TOKEN'] ?? '';

// Paramètres de l'envoi
$to   = '221757448121';          // Numéro destinataire
$text = 'Bonjour ! Votre commande est prête.';

// Données de la requête
$payload = json_encode([
    'apiKey' => $apiKey,
    'to'     => $to,
    'text'   => $text,
]);

// Initialisation cURL
$ch = curl_init('https://cursusbooster.com/api/v1/message');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Affichage du résultat
echo "=== Test d'envoi SMS ===" . PHP_EOL;
echo "Destinataire : $to" . PHP_EOL;
echo "Message      : $text" . PHP_EOL;
echo "HTTP Code    : $httpCode" . PHP_EOL;

if ($curlError) {
    echo "Erreur cURL  : $curlError" . PHP_EOL;
} else {
    $data = json_decode($response, true);
    echo "Réponse API  : " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    if ($httpCode === 200 || $httpCode === 201) {
        echo PHP_EOL . "✓ SMS envoyé avec succès !" . PHP_EOL;
    } else {
        echo PHP_EOL . "✗ Échec de l'envoi. Vérifiez vos identifiants." . PHP_EOL;
    }
}
