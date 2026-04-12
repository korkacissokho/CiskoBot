<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';
require_once '../config/api.php';

$pdo = getPDO();

$nbFormations = $pdo->query("SELECT COUNT(*) FROM formations")->fetchColumn();
$nbActives    = $pdo->query("SELECT COUNT(*) FROM formations WHERE actif = 1")->fetchColumn();
$nbCommandes  = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$totalRevenu  = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM commandes WHERE statut = 'paye'")->fetchColumn();
$nbAttente    = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'")->fetchColumn();
$waConnected  = isWaConnected();

$recentes = $pdo->query(
    "SELECT f.titre, c.telephone, c.montant, c.statut, c.created_at
     FROM commandes c
     JOIN formations f ON f.id = c.formation_id
     ORDER BY c.created_at DESC LIMIT 5"
)->fetchAll();

layout_start('Tableau de bord', 'dashboard');
?>

<!-- Statut WhatsApp API -->
<div class="alert <?= $waConnected ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom:20px;">
  <?php if ($waConnected): ?>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    WhatsApp API connectée et opérationnelle.
  <?php else: ?>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    WhatsApp API non connectée.
    <a href="http://localhost:3000/qr" target="_blank"
       style="margin-left:10px; font-weight:600; text-decoration:underline;">
      Scanner le QR code
    </a>
    &nbsp;|&nbsp;
    <span style="font-size:0.78rem;">Démarrez d'abord : <code>cd whatsapp-api && node index.js</code></span>
  <?php endif; ?>
  <?php if ($nbAttente > 0): ?>
    <span style="margin-left:auto; background:rgba(0,0,0,.1); padding:2px 10px; border-radius:20px; font-size:0.78rem;">
      <?= $nbAttente ?> paiement(s) en attente
    </span>
  <?php endif; ?>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon-wrap blue">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
    </div>
    <div class="stat-body">
      <div class="stat-label">Total formations</div>
      <div class="stat-value"><?= $nbFormations ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrap green">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="stat-body">
      <div class="stat-label">Formations actives</div>
      <div class="stat-value"><?= $nbActives ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrap orange">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    </div>
    <div class="stat-body">
      <div class="stat-label">Commandes totales</div>
      <div class="stat-value"><?= $nbCommandes ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon-wrap purple">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="stat-body">
      <div class="stat-label">Revenus (FCFA)</div>
      <div class="stat-value"><?= number_format((float)$totalRevenu, 0, ',', ' ') ?></div>
    </div>
  </div>
</div>

<div class="flex gap-2 mb-6">
  <a href="upload.php" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Ajouter une formation
  </a>
  <a href="send_bot.php" class="btn btn-success">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    Envoyer via Bot SMS
  </a>
  <a href="formations.php" class="btn btn-outline">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    Voir les formations
  </a>
</div>

<div class="card p-0">
  <div class="card-header" style="padding: 18px 22px 14px;">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      Dernières commandes
    </div>
    <a href="commandes.php" class="btn btn-ghost btn-sm">Voir tout</a>
  </div>

  <?php if (empty($recentes)): ?>
    <p class="text-muted" style="padding: 24px 22px; font-size:0.875rem;">Aucune commande pour l'instant.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Formation</th>
        <th>Téléphone</th>
        <th>Montant</th>
        <th>Statut</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recentes as $c): ?>
      <tr>
        <td class="font-semibold"><?= htmlspecialchars($c['titre']) ?></td>
        <td class="text-muted"><?= htmlspecialchars($c['telephone']) ?></td>
        <td><strong><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</strong></td>
        <td>
          <?php if ($c['statut'] === 'paye'): ?>
            <span class="badge badge-success">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              Payé
            </span>
          <?php elseif ($c['statut'] === 'annule'): ?>
            <span class="badge badge-danger">Annulé</span>
          <?php else: ?>
            <span class="badge badge-info">En attente</span>
          <?php endif; ?>
        </td>
        <td class="text-muted text-small"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php layout_end(); ?>
