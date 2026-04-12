<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';

$pdo = getPDO();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: formations.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
$stmt->execute([$id]);
$f = $stmt->fetch();
if (!$f) { header('Location: formations.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix        = $_POST['prix']  ?? '';
    $type        = $_POST['type']  ?? 'pdf';
    $actif       = isset($_POST['actif']) ? 1 : 0;

    if (!$titre)                          $errors[] = 'Le titre est obligatoire.';
    if (!is_numeric($prix) || $prix < 0) $errors[] = 'Prix invalide.';

    $fichier = $f['fichier'];
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        $allowExt = ['pdf', 'zip', 'docx', 'epub'];
        if ($_FILES['fichier']['size'] > 50 * 1024 * 1024) {
            $errors[] = 'Fichier trop volumineux (max 50 Mo).';
        } elseif (!in_array($ext, $allowExt)) {
            $errors[] = 'Extension non autorisée.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/';
            $safeName  = uniqid('doc_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadDir . $safeName)) {
                if ($f['fichier'] && file_exists($uploadDir . $f['fichier'])) {
                    unlink($uploadDir . $f['fichier']);
                }
                $fichier = $safeName;
            } else {
                $errors[] = 'Erreur upload fichier.';
            }
        }
    }

    if (empty($errors)) {
        $pdo->prepare(
            "UPDATE formations SET titre=?, description=?, prix=?, fichier=?, type=?, actif=? WHERE id=?"
        )->execute([$titre, $description, (float)$prix, $fichier, $type, $actif, $id]);
        header('Location: formations.php?msg=updated'); exit;
    }
    $f = array_merge($f, compact('titre','description','prix','type','actif','fichier'));
}

layout_start('Modifier la formation', 'formations');
?>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= htmlspecialchars($e) ?>
</div>
<?php endforeach; ?>

<div class="card">
  <form method="POST" enctype="multipart/form-data">

    <div class="grid-2">
      <div class="form-group">
        <label>Titre <span style="color:var(--danger)">*</span></label>
        <input type="text" name="titre" value="<?= htmlspecialchars($f['titre']) ?>" required>
      </div>
      <div class="form-group">
        <label>Prix (FCFA) <span style="color:var(--danger)">*</span></label>
        <input type="number" name="prix" value="<?= htmlspecialchars($f['prix']) ?>" min="0" step="100" required>
      </div>
    </div>

    <div class="grid-2">
      <div class="form-group">
        <label>Type</label>
        <select name="type">
          <option value="pdf"       <?= $f['type'] === 'pdf'       ? 'selected' : '' ?>>PDF</option>
          <option value="fascicule" <?= $f['type'] === 'fascicule' ? 'selected' : '' ?>>Fascicule</option>
          <option value="video"     <?= $f['type'] === 'video'     ? 'selected' : '' ?>>Video (ZIP)</option>
          <option value="autre"     <?= $f['type'] === 'autre'     ? 'selected' : '' ?>>Autre</option>
        </select>
      </div>
      <div class="form-group" style="display:flex; align-items:flex-end;">
        <label class="checkbox-wrap w-full">
          <input type="checkbox" name="actif" value="1" <?= $f['actif'] ? 'checked' : '' ?>>
          <span>Formation active</span>
        </label>
      </div>
    </div>

    <div class="form-group">
      <label>Description</label>
      <textarea name="description" rows="4"><?= htmlspecialchars($f['description']) ?></textarea>
    </div>

    <div class="form-group">
      <label>Fichier actuel</label>
      <div class="alert alert-info" style="margin-bottom:10px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        <?= htmlspecialchars($f['fichier']) ?>
      </div>
      <label class="upload-zone" for="fichier-input">
        <div class="upload-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <div class="upload-title">Remplacer le fichier</div>
        <div class="upload-sub">Laisser vide pour conserver l'actuel</div>
        <span id="file-name" class="upload-file-name" style="display:none"></span>
        <input type="file" id="fichier-input" name="fichier" accept=".pdf,.zip,.docx,.epub"
               onchange="showFileName(this)">
      </label>
    </div>

    <div class="flex gap-2 justify-between mt-3">
      <a href="formations.php" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Annuler
      </a>
      <button type="submit" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Mettre à jour
      </button>
    </div>

  </form>
</div>

<script>
function showFileName(input) {
  const el = document.getElementById('file-name');
  if (input.files[0]) {
    el.textContent = input.files[0].name;
    el.style.display = 'inline-block';
  }
}
</script>

<?php layout_end(); ?>
