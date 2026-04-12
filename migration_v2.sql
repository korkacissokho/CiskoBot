-- Migration v2 — Bot SMS complet
-- A exécuter après setup.sql
USE cissokho;

-- Clients (utilisateurs du bot)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telephone  VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sessions de conversation (état de la machine)
CREATE TABLE IF NOT EXISTS sessions_bot (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telephone    VARCHAR(30) NOT NULL UNIQUE,
    etat         VARCHAR(50) NOT NULL DEFAULT 'debut',
    formation_id INT UNSIGNED DEFAULT NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL
);

-- Tokens sécurisés de téléchargement (48h de validité)
CREATE TABLE IF NOT EXISTS tokens_telechargement (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    expires_at  DATETIME     NOT NULL,
    utilise     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE
);

-- Ajouter utilisateur_id et lien_envoye à commandes
ALTER TABLE commandes
    ADD COLUMN IF NOT EXISTS utilisateur_id INT UNSIGNED DEFAULT NULL AFTER formation_id,
    ADD COLUMN IF NOT EXISTS lien_envoye    TINYINT(1)   NOT NULL DEFAULT 0 AFTER statut;

-- Lien FK si pas encore fait
ALTER TABLE commandes
    ADD CONSTRAINT fk_commandes_utilisateur
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL;
