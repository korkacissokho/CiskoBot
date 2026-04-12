<?php
require_once '_auth.php';
require_once '_layout.php';
require_once '../config/db.php';

// Charger les clés API
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $env[$key] = $val;
    }
}
$apiKey   = $env['API_KEY']   ?? '';
$apiToken = $env['API_TOKEN'] ?? '';

$pdo        = getPDO();
$formations = $pdo->query("SELECT id, titre, prix FROM formations WHERE actif = 1 ORDER BY titre")->fetchAll();
$preselect  = filter_input(INPUT_GET, 'formation_id', FILTER_VALIDATE_INT) ?: '';

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formation_id = filter_input(INPUT_POST, 'formation_id', FILTER_VALIDATE_INT);
    $telephone    = trim($_POST['telephone'] ?? '');
    $messageType  = $_POST['message_type'] ?? 'info';

    if (!$formation_id) $errors[] = 'Sélectionnez une formation.';
    if (!$telephone)    $errors[] = 'Le numéro de téléphone est obligatoire.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch();

        if (!$formation) {
            $errors[] = 'Formation introuvable.';
        } else {
            $prix = number_format((float)$formation['prix'], 0, ',', ' ');

            $messages = [
                'info'   => "Formation : {$formation['titre']}\nPrix : {$prix} FCFA\nRepondez OUI pour commander.",
                'promo'  => "PROMOTION : {$formation['titre']}\nSeulement {$prix} FCFA !\nOffre limitee — Repondez OUI.",
                'rappel' => "Rappel : {$formation['titre']}\nPrix : {$prix} FCFA.\nRepondez OUI pour finaliser votre commande.",
                'custom' => trim($_POST['custom_message'] ?? ''),
            ];

            $texte = $messages[$messageType] ?? $messages['info'];

            if ($messageType === 'custom' && !$texte) {
                $errors[] = 'Veuillez saisir un message personnalisé.';
            } else {
                $payload = json_encode([
                    'apiKey' => $apiKey,
                    'to'     => $telephone,
                    'text'   => $texte,
                ]);

                $ch = curl_init('https://cursusbooster.com/api/v1/message');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $apiToken,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT        => 15,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);

                $result = [
                    'ok'        => !$curlErr && ($httpCode === 200 || $httpCode === 201),
                    'httpCode'  => $httpCode,
                    'curlErr'   => $curlErr,
                    'response'  => json_decode($response, true),
                    'telephone' => $telephone,
                    'formation' => $formation['titre'],
                    'message'   => $texte,
                ];

                if ($result['ok'] && $messageType !== 'custom') {
                    $pdo->prepare(
                        "INSERT INTO commandes (formation_id, telephone, montant, statut) VALUES (?,?,?,'en_attente')"
                    )->execute([$formation_id, $telephone, $formation['prix']]);
                }
            }
        }
    }
}

layout_start('Envoyer via Bot SMS', 'bot');
?>

<?php if ($result): ?>
  <?php if ($result['ok']): ?>
  <div class="alert alert-success">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    Message envoyé à <strong><?= htmlspecialchars($result['telephone']) ?></strong>
    pour la formation <strong><?= htmlspecialchars($result['formation']) ?></strong>.
  </div>
  <?php else: ?>
  <div class="alert alert-danger">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Echec de l'envoi (HTTP <?= $result['httpCode'] ?>).
    <?= $result['curlErr'] ? htmlspecialchars($result['curlErr']) : '' ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= htmlspecialchars($e) ?>
</div>
<?php endforeach; ?>

<div class="card">
  <form method="POST" id="bot-form">

    <div class="grid-2">
      <div class="form-group">
        <label for="formation_id">Formation <span style="color:var(--danger)">*</span></label>
        <select name="formation_id" id="formation_id" required>
          <option value="">Sélectionner une formation…</option>
          <?php foreach ($formations as $fm): ?>
          <option value="<?= $fm['id'] ?>"
            <?= (($_POST['formation_id'] ?? $preselect) == $fm['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($fm['titre']) ?> — <?= number_format((float)$fm['prix'], 0, ',', ' ') ?> FCFA
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="telephone">Numéro de téléphone <span style="color:var(--danger)">*</span></label>
        <div class="input-wrap">
          <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.55a16 16 0 0 0 5.54 5.54l.96-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <input type="text" id="telephone" name="telephone"
                 placeholder="Ex : 22677000000"
                 value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
        </div>
        <p class="form-hint">Format international sans le + (ex : 22677000000)</p>
      </div>
    </div>

    <div class="form-group">
      <label>Type de message</label>
      <div class="radio-cards">
        <?php
        $types = [
            'info'   => ['label' => 'Information',   'desc' => 'Présente la formation avec son prix',
              'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'],
            'promo'  => ['label' => 'Promotion',     'desc' => 'Ton promotionnel urgent',
              'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'],
            'rappel' => ['label' => 'Rappel',        'desc' => 'Relance un prospect',
              'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
            'custom' => ['label' => 'Personnalisé',  'desc' => 'Votre propre message',
              'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'],
        ];
        $currentType = $_POST['message_type'] ?? 'info';
        foreach ($types as $val => $t):
        ?>
        <label class="radio-card <?= $currentType === $val ? 'selected' : '' ?>" onclick="selectRadio(this, '<?= $val ?>')">
          <input type="radio" name="message_type" value="<?= $val ?>" <?= $currentType === $val ? 'checked' : '' ?> onchange="toggleCustom()">
          <div class="radio-card-label">
            <div class="radio-card-icon"><?= $t['icon'] ?></div>
            <div class="radio-card-text">
              <strong><?= $t['label'] ?></strong>
              <small><?= $t['desc'] ?></small>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group" id="custom-wrap" style="display:<?= ($currentType === 'custom') ? 'block' : 'none' ?>">
      <label for="custom_message">Message personnalisé</label>
      <textarea id="custom_message" name="custom_message" rows="5"
                placeholder="Saisissez votre message ici..."><?= htmlspecialchars($_POST['custom_message'] ?? '') ?></textarea>
      <p class="form-hint">Maximum recommandé : 160 caractères pour un SMS simple.</p>
    </div>

    <div class="flex gap-2 justify-between mt-3">
      <a href="formations.php" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour
      </a>
      <button type="submit" class="btn btn-success">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Envoyer le message
      </button>
    </div>

  </form>
</div>

<?php if ($result && $result['ok']): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Message envoyé
    </div>
  </div>
  <pre style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:16px; white-space:pre-wrap; font-family:'Inter',sans-serif; font-size:0.875rem; color:var(--text);"><?= htmlspecialchars($result['message']) ?></pre>
</div>
<?php endif; ?>

<script>
function toggleCustom() {
  const val = document.querySelector('input[name="message_type"]:checked')?.value;
  document.getElementById('custom-wrap').style.display = val === 'custom' ? 'block' : 'none';
}

function selectRadio(label, val) {
  document.querySelectorAll('.radio-card').forEach(c => c.classList.remove('selected'));
  label.classList.add('selected');
  label.querySelector('input[type=radio]').checked = true;
  toggleCustom();
}
</script>

<?php layout_end(); ?>
