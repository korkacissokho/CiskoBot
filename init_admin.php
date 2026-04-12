<?php
/**
 * Script d'initialisation — à lancer UNE SEULE FOIS
 * http://localhost:8000/init_admin.php
 * Supprimez ce fichier après utilisation !
 */
require_once __DIR__ . '/config/db.php';

$pdo  = getPDO();
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
$stmt->execute(['admin', $hash, $hash]);

echo "<h2>Admin initialisé avec succès !</h2>";
echo "<p>Identifiants : <strong>admin</strong> / <strong>admin123</strong></p>";
echo "<p style='color:red'><strong>Supprimez ce fichier maintenant !</strong></p>";
echo "<a href='/admin/login.php'>Aller à la page de connexion</a>";
