<?php
require_once __DIR__ . '/_auth.php';
header('Content-Type: application/json');

$logFile = __DIR__ . '/../bot/bot.log';
$lines   = [];

if (file_exists($logFile)) {
    $raw   = file($logFile, FILE_IGNORE_NEW_LINES);
    $lines = array_reverse(array_filter($raw, fn($l) => trim($l) !== ''));
}

echo json_encode(['count' => count($lines), 'lines' => array_values($lines)]);
