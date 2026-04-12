<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/env.php';
require_once '../config/api.php';

$apiBase  = rtrim($_ENV['WHATSAPP_API_URL'] ?? 'http://localhost:3000', '/');
$token    = $_ENV['API_TOKEN'] ?? '';

// Récupérer le QR via l'API Node.js
function fetchQrData(string $base, string $token): array {
    $ch = curl_init($base . '/api/v1/qr');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'connected' => false, 'qr' => null, 'error' => $err];
    return json_decode($resp, true) ?? ['ok' => false, 'connected' => false, 'qr' => null];
}

$data      = fetchQrData($apiBase, $token);
$connected = $data['connected'] ?? false;
$qr        = $data['qr']        ?? null;
$phone     = $data['phone']      ?? null;
$apiDown   = !($data['ok'] ?? false) || isset($data['error']);

layout_start('WhatsApp', 'whatsapp');
?>

<div style="max-width: 560px; margin: 0 auto;">

  <!-- Statut de connexion -->
  <div class="card" style="text-align:center; padding: 28px 24px;">

    <?php if ($apiDown): ?>
      <!-- API Node.js non démarrée -->
      <div style="width:72px;height:72px;background:#fee2e2;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <h2 style="font-size:1.15rem;font-weight:700;color:var(--text);margin-bottom:8px;">API non démarrée</h2>
      <p class="text-muted" style="font-size:0.875rem;line-height:1.6;">
        Le serveur WhatsApp n'est pas en cours d'exécution.<br>
        Lancez-le avec la commande ci-dessous, puis rafraîchissez.
      </p>
      <div style="background:#0f172a;color:#e2e8f0;border-radius:10px;padding:14px 18px;margin:18px 0;text-align:left;font-family:monospace;font-size:0.85rem;">
        <span style="color:#94a3b8">$</span> npm start
      </div>
      <a href="whatsapp.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Rafraîchir
      </a>

    <?php elseif ($connected): ?>
      <!-- Connecté -->
      <div style="width:72px;height:72px;background:#d1fae5;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2 style="font-size:1.15rem;font-weight:700;color:var(--text);margin-bottom:8px;">WhatsApp connecté</h2>
      <?php if ($phone): ?>
      <p class="text-muted" style="font-size:0.875rem;">
        Numéro actif :
        <strong style="color:var(--text);">+<?= htmlspecialchars($phone) ?></strong>
      </p>
      <?php endif; ?>

      <div style="display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
        <a href="whatsapp.php" class="btn btn-outline">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Rafraîchir le statut
        </a>
        <a href="?deconnecter=1" class="btn btn-danger"
           onclick="return confirm('Déconnecter WhatsApp ? Vous devrez rescanner le QR.')">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Déconnecter
        </a>
      </div>

    <?php elseif ($qr): ?>
      <!-- QR disponible -->
      <div style="margin-bottom:18px;">
        <h2 style="font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:6px;">Scanner le QR code</h2>
        <p class="text-muted" style="font-size:0.85rem;line-height:1.5;">
          Ouvrez WhatsApp sur votre téléphone<br>
          <strong>Appareils liés</strong> → <strong>Lier un appareil</strong>
        </p>
      </div>

      <!-- QR Image -->
      <div id="qr-wrap" style="display:inline-block;padding:14px;background:#fff;border:3px solid #25D366;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);position:relative;">
        <img id="qr-img" src="<?= htmlspecialchars($qr) ?>"
             style="width:240px;height:240px;display:block;border-radius:6px;" alt="QR WhatsApp">
        <div id="qr-overlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,0.92);border-radius:13px;display:none;align-items:center;justify-content:center;flex-direction:column;gap:10px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          <span style="font-size:0.82rem;font-weight:600;color:#dc2626;">QR expiré</span>
        </div>
      </div>

      <!-- Timer -->
      <div style="margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:10px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span class="text-muted text-small">Expire dans <strong id="countdown" style="color:var(--text)">60</strong>s</span>
        </div>
        <!-- Barre de progression -->
        <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden;width:100%;max-width:260px;margin:0 auto;">
          <div id="progress-bar" style="height:100%;background:#25D366;width:100%;transition:width 1s linear;border-radius:4px;"></div>
        </div>
      </div>

      <div style="margin-top:16px;">
        <button onclick="refreshQr()" class="btn btn-outline btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Nouveau QR
        </button>
      </div>

    <?php else: ?>
      <!-- QR en cours de génération -->
      <div style="width:72px;height:72px;background:#dbeafe;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <h2 style="font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:8px;">Génération du QR en cours…</h2>
      <p class="text-muted" style="font-size:0.875rem;">Patientez quelques secondes.</p>
      <a href="whatsapp.php" class="btn btn-primary" style="margin-top:16px;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Rafraîchir
      </a>
    <?php endif; ?>

  </div>

  <!-- Guide rapide -->
  <?php if (!$connected && !$apiDown): ?>
  <div class="card" style="margin-top:0;">
    <div class="card-title" style="margin-bottom:14px;">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      Comment scanner
    </div>
    <ol style="padding-left:20px;line-height:2;font-size:0.875rem;color:var(--text);">
      <li>Ouvrez <strong>WhatsApp</strong> sur votre téléphone</li>
      <li>Appuyez sur le menu <strong>⋮</strong> (Android) ou <strong>Réglages</strong> (iPhone)</li>
      <li>Sélectionnez <strong>Appareils liés</strong></li>
      <li>Appuyez sur <strong>Lier un appareil</strong></li>
      <li>Pointez la caméra sur le QR ci-dessus</li>
    </ol>
  </div>
  <?php endif; ?>

</div>

<?php if ($qr): ?>
<script>
const DURATION = 60
let remaining  = DURATION

const countdown    = document.getElementById('countdown')
const progressBar  = document.getElementById('progress-bar')
const qrOverlay    = document.getElementById('qr-overlay')

const timer = setInterval(() => {
  remaining--
  if (countdown) countdown.textContent = remaining
  if (progressBar) progressBar.style.width = (remaining / DURATION * 100) + '%'

  // Rouge dans les 10 dernières secondes
  if (remaining <= 10 && progressBar) progressBar.style.background = '#dc2626'

  if (remaining <= 0) {
    clearInterval(timer)
    if (qrOverlay) {
      qrOverlay.style.display = 'flex'
    }
  }
}, 1000)

// Rafraîchir automatiquement quand le QR expire
function refreshQr() {
  window.location.reload()
}

// Polling : vérifier si connecté toutes les 3s
const pollInterval = setInterval(async () => {
  try {
    const r    = await fetch('<?= $apiBase ?>/api/v1/qr', {
      headers: { 'Authorization': 'Bearer <?= htmlspecialchars($token) ?>' }
    })
    const data = await r.json()
    if (data.connected) {
      clearInterval(pollInterval)
      clearInterval(timer)
      window.location.reload()
    }
  } catch {}
}, 3000)
</script>
<?php endif; ?>

<?php
// Déconnexion
if (isset($_GET['deconnecter'])) {
    $ch = curl_init($apiBase . '/api/v1/logout');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '{}',
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
    echo '<script>setTimeout(() => location.href = "whatsapp.php", 1500)</script>';
}

layout_end();
?>
