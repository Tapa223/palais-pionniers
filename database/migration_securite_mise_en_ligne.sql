-- Sécurité avant mise en ligne. À exécuter une fois dans phpMyAdmin, sur la base palais_pionniers.
-- Limitation des tentatives de connexion (5 échecs par compte ou 20 par adresse IP en 15 minutes).

CREATE TABLE IF NOT EXISTS tentatives_connexion (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  tente_le DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tentatives_email (email, tente_le),
  KEY idx_tentatives_ip (ip, tente_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages de contact rattachés au compte qui les a envoyés (et non plus à l'adresse e-mail saisie).
ALTER TABLE messages ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL DEFAULT NULL AFTER id;
ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_messages_user (user_id);

-- Rattachement des messages déjà reçus aux comptes clients de même adresse e-mail
UPDATE messages m
JOIN users u ON u.email = m.email AND u.role IN ('user', 'partenaire')
SET m.user_id = u.id
WHERE m.user_id IS NULL;
