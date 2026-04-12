<?php
require_once '_auth.php';
require_once '../config/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: formations.php'); exit; }

$pdo = getPDO();
$f   = $pdo->prepare("SELECT fichier FROM formations WHERE id = ?");
$f->execute([$id]);
$row = $f->fetch();

if ($row) {
    // Supprimer le fichier physique
    $path = __DIR__ . '/../uploads/' . $row['fichier'];
    if (file_exists($path)) unlink($path);

    $pdo->prepare("DELETE FROM formations WHERE id = ?")->execute([$id]);
}

header('Location: formations.php?msg=deleted');
exit;
