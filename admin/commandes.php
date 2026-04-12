<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';

$pdo = getPDO();

$msg  = $_GET['msg']  ?? '';
$lien = $_GET['lien'] ?? '';
$err  = $_GET['err']  ?? '';

$commandes = $pdo->query("
    SELECT c.*, f.titre, f.fichier,
           (SELECT COUNT(*) FROM tokens_telechargement t WHERE t.commande_id = c.id) AS nb_tokens
    FROM commandes c
    JOIN formations f ON f.id = c.formation_id
    ORDER BY
        CASE c.statut WHEN 'en_attente' THEN 0 WHEN 'paye' THEN 1 ELSE 2 END,
        c.created_at DESC
")->fetchAll();

$enAttente = array_filter($commandes, fn($c) => $c['statut'] === 'en_attente');
$nbAttente = count($enAttente);

layout_start('Commandes', 'commandes');
?>

<?php if ($msg === 'valide'): ?>
<div class="alert alert-success">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  Paiement validé. Le lien de téléchargement a été envoyé par SMS.
  <?php if ($lien): ?>
  <a href="<?= htmlspecialchars($lien) ?>" target="_blank"
     style="margin-left:12px; color:var(--success); font-weight:600; text-decoration:underline;">
    Voir le lien
  </a>
  <?php endif; ?>
</div>
<?php elseif ($msg === 'rejete'): ?>
<div class="alert alert-danger">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  Paiement rejeté. Le client a été notifié par SMS.
</div>
<?php elseif ($err === 'not_found'): ?>
<div class="alert alert-warning">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  Commande introuvable ou déjà traitée.
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h2>Commandes</h2>
    <p>
      <?= count($commandes) ?> au total
      <?php if ($nbAttente > 0): ?>
        — <span style="color:var(--warning); font-weight:600;"><?= $nbAttente ?> en attente de validation</span>
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if ($nbAttente > 0): ?>
<!-- Bloc "À valider" mis en avant -->
<div class="card" style="border-left:4px solid var(--warning); padding:0; margin-bottom:24px;">
  <div class="card-header" style="padding:16px 22px 12px; background:#fffbeb; border-radius:10px 10px 0 0;">
    <div class="card-title" style="color:var(--warning);">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Paiements en attente de validation (<?= $nbAttente ?>)
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Formation</th>
        <th>Téléphone client</th>
        <th>Montant</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($enAttente as $c): ?>
      <tr style="background:#fffdf0;">
        <td class="font-semibold"><?= htmlspecialchars($c['titre']) ?></td>
        <td>
          <strong><?= htmlspecialchars($c['telephone']) ?></strong>
        </td>
        <td><strong><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</strong></td>
        <td class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
        <td>
          <div class="flex gap-2">
            <a href="valider_paiement.php?action=valider&id=<?= $c['id'] ?>"
               class="btn btn-success btn-sm"
               onclick="return confirm('Valider ce paiement et envoyer le lien au client ?')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Valider et envoyer
            </a>
            <a href="valider_paiement.php?action=rejeter&id=<?= $c['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Rejeter ce paiement ? Le client sera notifié.')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Rejeter
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Toutes les commandes -->
<div class="card p-0">
  <div class="card-header" style="padding:16px 22px 12px;">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      Historique complet
    </div>
  </div>

  <?php if (empty($commandes)): ?>
    <p class="text-muted" style="padding:24px; font-size:0.875rem;">Aucune commande pour l'instant.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Formation</th>
        <th>Téléphone</th>
        <th>Montant</th>
        <th>Statut</th>
        <th>Lien envoyé</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($commandes as $c): ?>
      <tr>
        <td class="text-muted text-small"><?= $c['id'] ?></td>
        <td class="font-semibold"><?= htmlspecialchars($c['titre']) ?></td>
        <td class="text-muted"><?= htmlspecialchars($c['telephone']) ?></td>
        <td><strong><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</strong></td>
        <td>
          <?php if ($c['statut'] === 'paye'): ?>
            <span class="badge badge-success">
              <span class="status-dot green"></span>Payé
            </span>
          <?php elseif ($c['statut'] === 'annule'): ?>
            <span class="badge badge-danger">
              <span class="status-dot red"></span>Annulé
            </span>
          <?php else: ?>
            <span class="badge badge-warning">
              <span class="status-dot orange"></span>En attente
            </span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($c['lien_envoye']): ?>
            <span class="badge badge-success">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Envoyé
            </span>
          <?php elseif ($c['statut'] === 'paye'): ?>
            <!-- Paiement validé mais SMS échoué → renvoyer -->
            <a href="valider_paiement.php?action=valider&id=<?= $c['id'] ?>"
               class="btn btn-outline btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Renvoyer
            </a>
          <?php else: ?>
            <span class="text-muted text-small">—</span>
          <?php endif; ?>
        </td>
        <td class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
        <td>
          <?php if ($c['statut'] === 'en_attente'): ?>
          <div class="flex gap-2">
            <a href="valider_paiement.php?action=valider&id=<?= $c['id'] ?>"
               class="btn btn-success btn-sm"
               onclick="return confirm('Valider ce paiement ?')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </a>
            <a href="valider_paiement.php?action=rejeter&id=<?= $c['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Rejeter ce paiement ?')">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </a>
          </div>
          <?php else: ?>
            <span class="text-muted text-small">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php layout_end(); ?>
