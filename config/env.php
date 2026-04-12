<?php
/**
 * Chargement centralisé du fichier .env
 * Usage : require_once __DIR__ . '/env.php'; puis $_ENV['CLE']
 */
(function () {
    $file = __DIR__ . '/../.env';
    if (!file_exists($file)) return;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $val;
    }
})();
