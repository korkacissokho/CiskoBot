<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';

$pdo = getPDO();

if (isset($_GET['toggle']) && ctype_digit($_GET['toggle'])) {
    $pdo->prepare("UPDATE formations SET actif = 1 - actif WHERE id = ?")->execute([$_GET['toggle']]);
    header('Location: formations.php?msg=updated'); exit;
}

$msg        = $_GET['msg'] ?? '';
$formations = $pdo->query("SELECT * FROM formations ORDER BY created_at DESC")->fetchAll();

layout_start('Formations', 'formations');
?>

<?php if ($msg === 'added'):   ?>
<div class="alert alert-success">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  Formation ajoutée avec succès.
</div>
<?php elseif ($msg === 'updated'): ?>
<div class="alert alert-success">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  Formation mise à jour.
</div>
<?php elseif ($msg === 'deleted'): ?>
<div class="alert alert-danger">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
  Formation supprimée.
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2>Toutes les formations</h2>
    <p><?= count($formations) ?> formation(s) au total</p>
  </div>
  <a href="upload.php" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Ajouter
  </a>
</div>

<div class="card p-0">
  <?php if (empty($formations)): ?>
    <p class="text-muted" style="padding:24px; font-size:0.875rem;">
      Aucune formation.
      <a href="upload.php" style="color:var(--primary); text-decoration:none; font-weight:500;">Ajouter la première</a>.
    </p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Titre</th>
        <th>Type</th>
        <th>Prix (FCFA)</th>
        <th>Statut</th>
        <th>Ajouté le</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($formations as $f): ?>
      <tr>
        <td class="text-muted text-small"><?= $f['id'] ?></td>
        <td>
          <span class="font-semibold"><?= htmlspecialchars($f['titre']) ?></span>
          <?php if ($f['description']): ?>
          <br><span class="text-muted text-small"><?= htmlspecialchars(mb_substr($f['description'], 0, 55)) ?>…</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-info"><?= strtoupper(htmlspecialchars($f['type'])) ?></span></td>
        <td><strong><?= number_format((float)$f['prix'], 0, ',', ' ') ?></strong></td>
        <td>
          <?php if ($f['actif']): ?>
            <span class="badge badge-success">
              <span class="status-dot green"></span>
              Actif
            </span>
          <?php else: ?>
            <span class="badge badge-danger">
              <span class="status-dot red"></span>
              Inactif
            </span>
          <?php endif; ?>
        </td>
        <td class="text-muted text-small"><?= date('d/m/Y', strtotime($f['created_at'])) ?></td>
        <td>
          <div class="flex gap-2">
            <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-outline btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Modifier
            </a>
            <a href="formations.php?toggle=<?= $f['id'] ?>" class="btn btn-outline btn-sm"
               onclick="return confirm('Changer le statut ?')">
              <?php if ($f['actif']): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="16" cy="12" r="3"/></svg>
              <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="8" cy="12" r="3"/></svg>
              <?php endif; ?>
            </a>
            <a href="send_bot.php?formation_id=<?= $f['id'] ?>" class="btn btn-success btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </a>
            <a href="delete.php?id=<?= $f['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Supprimer cette formation définitivement ?')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php layout_end(); ?>
