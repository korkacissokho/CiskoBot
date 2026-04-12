<?php
/**
 * Valider ou rejeter un paiement
 *
 * - Valider  : envoie le document PDF directement via WhatsApp
 *              (fallback : envoie un lien de téléchargement sécurisé)
 * - Rejeter  : envoie un SMS de rejet et libère la session du client
 */

require_once '_auth.php';
require_once '../config/db.php';
require_once '../config/env.php';
require_once '../config/api.php';   // sendMessage(), sendDocument()

$action     = $_GET['action']      ?? '';
$commandeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$commandeId || !in_array($action, ['valider', 'rejeter'])) {
    header('Location: commandes.php'); exit;
}

$pdo = getPDO();

// Récupérer la commande + infos formation + fichier
$stmt = $pdo->prepare("
    SELECT c.*, f.titre, f.fichier, f.type AS f_type
    FROM commandes c
    JOIN formations f ON f.id = c.formation_id
    WHERE c.id = ? AND c.statut = 'en_attente'
");
$stmt->execute([$commandeId]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: commandes.php?err=not_found'); exit;
}

$appUrl   = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
$filePath = realpath(__DIR__ . '/../uploads/' . $commande['fichier']);

// ── VALIDER ──────────────────────────────────────────────────
if ($action === 'valider') {

    $docEnvoye  = false;
    $lienEnvoye = false;
    $lien       = '';

    // 1) Essayer d'envoyer le document directement via WhatsApp
    if ($filePath && file_exists($filePath)) {
        $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $commande['titre']) . '.' . $ext;
        $caption  = "Voici votre document de : " . $commande['titre'] . "\nMerci pour votre confiance !";

        $docEnvoye = sendDocument(
            $commande['telephone'],
            $filePath,
            $filename,
            $caption
        );
    }

    // 2) Fallback : générer un lien de téléchargement sécurisé (48h)
    if (!$docEnvoye) {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime('+48 hours'))->format('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO tokens_telechargement (commande_id, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$commandeId, $token, $expiresAt]);

        $lien = $appUrl . '/telecharger.php?token=' . $token;

        $msg  = "✅ *Paiement confirmé !*\n\n";
        $msg .= "📚 *" . $commande['titre'] . "*\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Votre document est prêt à télécharger.\n";
        $msg .= "Appuyez sur le lien ci-dessous :\n\n";
        $msg .= $lien . "\n\n";
        $msg .= "_Si le lien ne s'ouvre pas, copiez-le et collez-le dans votre navigateur (Chrome, Firefox…)_\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "⏳ Lien valable *48 heures*\n";
        $msg .= "Merci pour votre confiance chez Cissokho !";

        $lienEnvoye = sendMessage($commande['telephone'], $msg);
    } else {
        // Confirmer par message après l'envoi du document
        sendMessage(
            $commande['telephone'],
            "✅ *Paiement confirmé !*\n\n"
            . "📚 *" . $commande['titre'] . "*\n\n"
            . "Votre document vous a été envoyé directement.\n"
            . "Merci pour votre confiance chez Cissokho ! 🙏"
        );
        $lienEnvoye = true;
    }

    // Mettre à jour la commande
    $pdo->prepare(
        "UPDATE commandes SET statut = 'paye', lien_envoye = ? WHERE id = ?"
    )->execute([($docEnvoye || $lienEnvoye) ? 1 : 0, $commandeId]);

    // Réinitialiser la session bot (le client peut racheter autre chose)
    $pdo->prepare(
        "UPDATE sessions_bot SET etat = 'debut', formation_id = NULL WHERE telephone = ?"
    )->execute([$commande['telephone']]);

    $redirect = 'commandes.php?msg=valide';
    if ($lien) $redirect .= '&lien=' . urlencode($lien);
    if (!$docEnvoye && !$lienEnvoye) $redirect .= '&warn=sms_failed';

    header('Location: ' . $redirect);
    exit;
}

// ── REJETER ───────────────────────────────────────────────────
if ($action === 'rejeter') {
    $msg  = "❌ *Paiement non confirmé*\n\n";
    $msg .= "📚 *" . $commande['titre'] . "*\n\n";
    $msg .= "Nous n'avons pas pu valider votre paiement.\n";
    $msg .= "Vérifiez que le montant envoyé est correct.\n\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Tapez *MENU* pour recommencer ou nous contacter.";

    sendMessage($commande['telephone'], $msg);

    $pdo->prepare("UPDATE commandes SET statut = 'annule' WHERE id = ?")->execute([$commandeId]);
    $pdo->prepare("UPDATE sessions_bot SET etat = 'debut', formation_id = NULL WHERE telephone = ?")
        ->execute([$commande['telephone']]);

    header('Location: commandes.php?msg=rejete');
    exit;
}
