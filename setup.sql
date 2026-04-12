-- Base de données : cissokho
CREATE DATABASE IF NOT EXISTS cissokho CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cissokho;

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Mot de passe par défaut : admin123  (à changer après première connexion)
INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$12$eImiTXuWVxfM37uY4JANjQ==eImiTXuWVxfM37uY4JANjQe');
-- Ce hash sera regénéré par init_admin.php au premier lancement

-- Table des formations
CREATE TABLE IF NOT EXISTS formations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre       VARCHAR(200)   NOT NULL,
    description TEXT,
    prix        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    fichier     VARCHAR(255)   NOT NULL,
    type        ENUM('pdf','fascicule','video','autre') NOT NULL DEFAULT 'pdf',
    actif       TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des commandes / ventes
CREATE TABLE IF NOT EXISTS commandes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formation_id  INT UNSIGNED NOT NULL,
    telephone     VARCHAR(30)  NOT NULL,
    montant       DECIMAL(10,2) NOT NULL,
    statut        ENUM('en_attente','paye','annule') NOT NULL DEFAULT 'en_attente',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);
