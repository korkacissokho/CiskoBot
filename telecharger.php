<?php
/**
 * Téléchargement sécurisé par token
 * URL : /telecharger.php?token=abc123
 *
 * - Valide le token (existence, expiration)
 * - Sert le fichier PDF/ZIP directement
 * - Marque le token comme utilisé après téléchargement
 */

require_once __DIR__ . '/config/db.php';

$token = trim($_GET['token'] ?? '');

if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    show_error('Lien invalide', 'Ce lien de téléchargement est invalide.');
    exit;
}

$pdo = getPDO();

// Récupérer le token et sa commande
$stmt = $pdo->prepare("
    SELECT t.*, c.formation_id, f.titre, f.fichier
    FROM tokens_telechargement t
    JOIN commandes c ON c.id = t.commande_id
    JOIN formations f ON f.id = c.formation_id
    WHERE t.token = ?
");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    show_error('Lien introuvable', 'Ce lien de téléchargement n\'existe pas.');
    exit;
}

if (new DateTime() > new DateTime($row['expires_at'])) {
    http_response_code(410);
    show_error('Lien expiré', 'Ce lien de téléchargement a expiré (validité 48h). Contactez-nous pour en obtenir un nouveau.');
    exit;
}

$filePath = __DIR__ . '/uploads/' . $row['fichier'];

if (!file_exists($filePath)) {
    http_response_code(404);
    show_error('Fichier introuvable', 'Le fichier n\'est pas disponible. Contactez le support.');
    exit;
}

// Marquer utilisé (une seule fois optionnel — ici on autorise plusieurs téléchargements)
if (!$row['utilise']) {
    $pdo->prepare("UPDATE tokens_telechargement SET utilise = 1 WHERE token = ?")->execute([$token]);
}

// Déterminer le type MIME
$ext      = strtolower(pathinfo($row['fichier'], PATHINFO_EXTENSION));
$mimeMap  = [
    'pdf'  => 'application/pdf',
    'zip'  => 'application/zip',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'epub' => 'application/epub+zip',
];
$mime     = $mimeMap[$ext] ?? 'application/octet-stream';
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $row['titre']) . '.' . $ext;

// Servir le fichier
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;

// ── Helper affichage erreur ────────────────────────────────────
function show_error(string $titre, string $message): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titre) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg,#f0f4f8);">
<div style="background:#fff; border-radius:14px; padding:40px; max-width:420px; text-align:center; box-shadow:0 4px 24px rgba(0,0,0,0.10); border:1px solid #e2e8f0;">
  <div style="width:56px;height:56px;background:#fee2e2;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  </div>
  <h2 style="font-family:'Segoe UI',sans-serif; font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:10px;">
    <?= htmlspecialchars($titre) ?>
  </h2>
  <p style="font-family:'Segoe UI',sans-serif; color:#64748b; font-size:0.9rem; line-height:1.6;">
    <?= htmlspecialchars($message) ?>
  </p>
</div>
</body>
</html>
<?php
}
