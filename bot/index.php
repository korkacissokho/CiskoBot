<?php
/**
 * Webhook entrant SMS — Bot Cissokho
 *
 * Configurer dans votre plateforme SMS l'URL de ce fichier :
 *   https://votre-domaine.com/bot/index.php
 *
 * Format attendu (POST JSON) :
 *   { "from": "22677000000", "text": "Bonjour" }
 *
 * Machine d'états :
 *   debut              → affiche la liste des formations
 *   choix_formation    → attend un numéro (1, 2, 3…)
 *   confirmer_paiement → attend "PAY" après les instructions de paiement
 *   paiement_soumis    → attend validation admin
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/api.php';

header('Content-Type: application/json');

// ── Logger ────────────────────────────────────────────────────
define('LOG_FILE', __DIR__ . '/bot.log');
define('LOG_MAX',  200);  // lignes max conservées

function botLog(string $level, string $msg): void {
    $line = '[' . date('d/m H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    // Garder seulement les N dernières lignes
    $lines = file(LOG_FILE);
    if (count($lines) > LOG_MAX) {
        file_put_contents(LOG_FILE, implode('', array_slice($lines, -LOG_MAX)));
    }
}

// ── Lecture du payload ────────────────────────────────────────
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true) ?? [];

$from = trim($payload['from'] ?? $_POST['from'] ?? $_GET['from'] ?? '');
$text = trim($payload['text'] ?? $_POST['text'] ?? $_GET['text'] ?? '');

botLog('IN', "from=+{$from} text=\"" . substr($text, 0, 80) . "\"");

if (!$from || !$text) {
    http_response_code(400);
    botLog('ERR', 'Payload invalide — from ou text manquant');
    echo json_encode(['error' => 'Champs from et text requis']);
    exit;
}

$pdo        = getPDO();
$waveNumber = $_ENV['WAVE_NUMBER'] ?? 'Non configuré';

// sendSMS : envoie via l'API WhatsApp et log le résultat
function sendSMS(string $to, string $message): void {
    $ok = sendMessage($to, $message);
    botLog($ok ? 'OUT' : 'FAIL', "to=+{$to} | " . ($ok ? 'OK' : 'ECHEC API') . " | \"" . substr($message, 0, 60) . "\"");
}

function getSession(PDO $pdo, string $tel): array {
    $stmt = $pdo->prepare("SELECT * FROM sessions_bot WHERE telephone = ?");
    $stmt->execute([$tel]);
    return $stmt->fetch() ?: ['telephone' => $tel, 'etat' => 'debut', 'formation_id' => null];
}

function setSession(PDO $pdo, string $tel, string $etat, ?int $formId = null): void {
    $pdo->prepare(
        "INSERT INTO sessions_bot (telephone, etat, formation_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE etat = VALUES(etat), formation_id = VALUES(formation_id)"
    )->execute([$tel, $etat, $formId]);
}

function getOrCreateUser(PDO $pdo, string $tel): int {
    $s = $pdo->prepare("SELECT id FROM utilisateurs WHERE telephone = ?");
    $s->execute([$tel]);
    $row = $s->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO utilisateurs (telephone) VALUES (?)")->execute([$tel]);
    return (int) $pdo->lastInsertId();
}

function getFormations(PDO $pdo): array {
    return $pdo->query("SELECT id, titre, description, prix FROM formations WHERE actif = 1 ORDER BY id")->fetchAll();
}

function buildListeMessage(array $formations): string {
    $numeros = ['1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟'];
    $msg  = "🎓 *Cissokho Formations*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= "Voici nos formations disponibles :\n\n";
    foreach ($formations as $i => $f) {
        $prix  = number_format((float)$f['prix'], 0, ',', ' ');
        $num   = $numeros[$i] ?? ($i + 1) . '.';
        $msg .= $num . " *" . $f['titre'] . "*\n";
        $msg .= "   💰 " . $prix . " FCFA\n\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👉 Répondez avec le *numéro* de votre choix";
    return $msg;
}


// ── Normalisation du texte entrant ───────────────────────────
$norm = strtoupper(trim(preg_replace('/\s+/', ' ', $text)));

// Mots-clés globaux qui réinitialisent la conversation
$isStart = in_array($norm, ['MENU', 'LISTE', 'FORMATIONS', 'BONJOUR', 'SALUT',
                             'AIDE', 'HELP', 'START', 'HI', 'HELLO', '0', 'DEBUT',
                             'RECOMMENCER', 'ACCUEIL']);

// Mots-clés de confirmation de paiement
$isPaid = str_starts_with($norm, 'PAY') || str_starts_with($norm, 'ENVO')
       || in_array($norm, ['OUI', 'OK', 'FAIT', 'DONE', 'PAYE', 'PAID', 'JAI PAYE']);

// ── Lecture de la session ─────────────────────────────────────
$session = getSession($pdo, $from);
$etat    = $session['etat'];

// ── Mot-clé global → réinitialiser ───────────────────────────
if ($isStart) {
    $formations = getFormations($pdo);
    if (empty($formations)) {
        sendSMS($from,
            "😔 *Aucune formation disponible*\n\n"
            . "Nos formations arrivent bientôt.\n"
            . "Revenez nous voir très prochainement !"
        );
    } else {
        sendSMS($from, buildListeMessage($formations));
        setSession($pdo, $from, 'choix_formation');
    }
    echo json_encode(['ok' => true, 'etat' => 'choix_formation']);
    exit;
}

// ── Machine d'états ───────────────────────────────────────────
switch ($etat) {

    // ── État initial : afficher la liste ─────────────────────
    case 'debut':
    default:
        $formations = getFormations($pdo);
        if (empty($formations)) {
            sendSMS($from,
                "😔 *Aucune formation disponible*\n\n"
                . "Nos formations arrivent bientôt.\n"
                . "Revenez nous voir très prochainement !"
            );
        } else {
            sendSMS($from, buildListeMessage($formations));
            setSession($pdo, $from, 'choix_formation');
        }
        break;

    // ── Sélection d'une formation ─────────────────────────────
    case 'choix_formation':
        $formations = getFormations($pdo);

        if (ctype_digit($norm) && (int)$norm >= 1) {
            $index = (int)$norm - 1;

            if (!isset($formations[$index])) {
                sendSMS($from,
                    "❌ *Numéro invalide*\n\n"
                    . "Choisissez un numéro entre *1* et *" . count($formations) . "*.\n\n"
                    . "Tapez *MENU* pour revoir la liste."
                );
                break;
            }

            $formation = $formations[$index];
            $userId    = getOrCreateUser($pdo, $from);
            $prix      = number_format((float)$formation['prix'], 0, ',', ' ');

            $msg  = "📚 *" . $formation['titre'] . "*\n";
            if (!empty($formation['description'])) {
                $msg .= "\n" . $formation['description'] . "\n";
            }
            $msg .= "\n💰 Prix : *" . $prix . " FCFA*\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "💳 *Comment payer par Wave ?*\n\n";
            $msg .= "1. Ouvrez l'application *Wave*\n";
            $msg .= "2. Envoyez *" . $prix . " FCFA* au :\n\n";
            $msg .= "   📱 *" . $waveNumber . "*\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "Une fois le virement fait, répondez :\n\n";
            $msg .= "   👉 *PAY*\n\n";
            $msg .= "Ou *ANNULER* pour choisir une autre formation.";

            sendSMS($from, $msg);
            setSession($pdo, $from, 'confirmer_paiement', (int)$formation['id']);

        } else {
            // Texte non reconnu → réafficher la liste
            sendSMS($from,
                "🤔 Je n'ai pas compris votre choix.\n\n"
                . buildListeMessage($formations)
            );
            setSession($pdo, $from, 'choix_formation');
        }
        break;

    // ── Attente de confirmation "PAY" ─────────────────────────
    case 'confirmer_paiement':
        if ($isPaid && $session['formation_id']) {
            $formId = (int)$session['formation_id'];
            $userId = getOrCreateUser($pdo, $from);

            $fStmt = $pdo->prepare("SELECT titre, prix FROM formations WHERE id = ?");
            $fStmt->execute([$formId]);
            $formation = $fStmt->fetch();

            if (!$formation) {
                sendSMS($from,
                    "❌ Formation introuvable.\n\n"
                    . "Tapez *MENU* pour recommencer."
                );
                setSession($pdo, $from, 'debut');
                break;
            }

            $pdo->prepare(
                "INSERT INTO commandes (formation_id, utilisateur_id, telephone, montant, statut)
                 VALUES (?, ?, ?, ?, 'en_attente')"
            )->execute([$formId, $userId, $from, $formation['prix']]);

            sendSMS($from,
                "🎉 *Merci pour votre paiement !*\n\n"
                . "📋 *" . $formation['titre'] . "*\n\n"
                . "Notre équipe vérifie votre virement Wave.\n"
                . "⏱ Délai habituel : *moins de 24h*\n\n"
                . "Dès confirmation, vous recevrez votre document directement ici.\n\n"
                . "━━━━━━━━━━━━━━━━━━━━━\n"
                . "Tapez *MENU* pour voir d'autres formations."
            );
            setSession($pdo, $from, 'paiement_soumis', $formId);

        } elseif (in_array($norm, ['ANNULER', 'NON', 'CANCEL', 'RETOUR', 'STOP'])) {
            $formations = getFormations($pdo);
            sendSMS($from,
                "↩️ *Commande annulée*\n\n"
                . buildListeMessage($formations)
            );
            setSession($pdo, $from, 'choix_formation');

        } else {
            // Rappel des instructions
            if ($session['formation_id']) {
                $fStmt = $pdo->prepare("SELECT titre, prix FROM formations WHERE id = ?");
                $fStmt->execute([$session['formation_id']]);
                $f    = $fStmt->fetch();
                $prix = number_format((float)($f['prix'] ?? 0), 0, ',', ' ');

                sendSMS($from,
                    "⏳ *En attente de votre paiement*\n\n"
                    . "📚 *" . ($f['titre'] ?? 'Formation') . "*\n"
                    . "💰 Montant : *" . $prix . " FCFA*\n\n"
                    . "━━━━━━━━━━━━━━━━━━━━━\n"
                    . "📱 Wave : *" . $waveNumber . "*\n\n"
                    . "Une fois le virement fait, répondez *PAY*.\n"
                    . "Ou *ANNULER* pour choisir une autre formation."
                );
            }
        }
        break;

    // ── Paiement soumis, attente validation ───────────────────
    case 'paiement_soumis':
        sendSMS($from,
            "🔍 *Vérification en cours*\n\n"
            . "Notre équipe traite votre paiement.\n"
            . "Vous recevrez votre document dès confirmation.\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━\n"
            . "Tapez *MENU* pour voir d'autres formations."
        );
        break;
}

http_response_code(200);
echo json_encode(['ok' => true, 'etat' => $etat]);
