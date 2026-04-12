<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix        = $_POST['prix']  ?? '';
    $type        = $_POST['type']  ?? 'pdf';
    $actif       = isset($_POST['actif']) ? 1 : 0;

    if (!$titre)                          $errors[] = 'Le titre est obligatoire.';
    if (!is_numeric($prix) || $prix < 0) $errors[] = 'Le prix doit être un nombre positif.';
    if (!in_array($type, ['pdf','fascicule','video','autre'])) $errors[] = 'Type invalide.';

    $fichier = '';
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        $allowExt = ['pdf', 'zip', 'docx', 'epub'];
        if ($_FILES['fichier']['size'] > 50 * 1024 * 1024) {
            $errors[] = 'Fichier trop volumineux (max 50 Mo).';
        } elseif (!in_array($ext, $allowExt)) {
            $errors[] = 'Extension non autorisée (pdf, zip, docx, epub).';
        } else {
            $safeName = uniqid('doc_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], __DIR__ . '/../uploads/' . $safeName)) {
                $fichier = $safeName;
            } else {
                $errors[] = 'Erreur lors du téléchargement du fichier.';
            }
        }
    } else {
        $errors[] = 'Veuillez sélectionner un fichier.';
    }

    if (empty($errors)) {
        getPDO()->prepare(
            "INSERT INTO formations (titre, description, prix, fichier, type, actif) VALUES (?,?,?,?,?,?)"
        )->execute([$titre, $description, (float)$prix, $fichier, $type, $actif]);
        header('Location: formations.php?msg=added'); exit;
    }
}

layout_start('Ajouter une formation', 'upload');
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
        <label for="titre">Titre du document <span style="color:var(--danger)">*</span></label>
        <input type="text" id="titre" name="titre" placeholder="Ex : Formation Marketing Digital"
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="prix">Prix (FCFA) <span style="color:var(--danger)">*</span></label>
        <input type="number" id="prix" name="prix" placeholder="Ex : 5000" min="0" step="100"
               value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>" required>
      </div>
    </div>

    <div class="grid-2">
      <div class="form-group">
        <label for="type">Type de document</label>
        <select id="type" name="type">
          <option value="pdf"       <?= ($_POST['type'] ?? '') === 'pdf'       ? 'selected' : '' ?>>PDF</option>
          <option value="fascicule" <?= ($_POST['type'] ?? '') === 'fascicule' ? 'selected' : '' ?>>Fascicule</option>
          <option value="video"     <?= ($_POST['type'] ?? '') === 'video'     ? 'selected' : '' ?>>Video (ZIP)</option>
          <option value="autre"     <?= ($_POST['type'] ?? '') === 'autre'     ? 'selected' : '' ?>>Autre</option>
        </select>
      </div>
      <div class="form-group" style="display:flex; align-items:flex-end;">
        <label class="checkbox-wrap w-full">
          <input type="checkbox" name="actif" value="1"
                 <?= !isset($_POST['titre']) || isset($_POST['actif']) ? 'checked' : '' ?>>
          <span>Rendre active immédiatement</span>
        </label>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4"
                placeholder="Décrivez le contenu de cette formation..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>Fichier <span style="color:var(--danger)">*</span> <span class="text-muted text-small">(PDF, ZIP, DOCX, EPUB — max 50 Mo)</span></label>
      <label class="upload-zone" for="fichier-input">
        <div class="upload-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
        <div class="upload-title">Cliquez ou glissez votre fichier ici</div>
        <div class="upload-sub">PDF, ZIP, DOCX ou EPUB</div>
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
        Enregistrer la formation
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
