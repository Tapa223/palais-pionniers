-- =====================================================================
--  MIGRATION — Quantité de chambres, petit-déjeuner par espace,
--  statut "expirée" (annulation automatique 48h)
--  À exécuter une seule fois, après les migrations précédentes.
--  Généré le 27/07/2026
-- =====================================================================

-- 1. Statut "expiree" (au cas où pas déjà présent)
ALTER TABLE `reservations`
  MODIFY COLUMN `statut` ENUM('en_attente','validee','refusee','annulee','expiree') NOT NULL DEFAULT 'en_attente'
  COMMENT 'expiree = annulée automatiquement (48h sans paiement), distincte d''un refus manuel';

-- 2. Quantité d'unités réservées (ex: 3 chambres du même type en une seule demande)
ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `quantite` INT UNSIGNED NOT NULL DEFAULT 1
  COMMENT 'Nombre d''unités réservées' AFTER `petit_dejeuner`;

-- 3. Option petit-déjeuner par espace (certains hébergements ne le proposent pas)
ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `option_petit_dejeuner` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Propose un supplément petit-déjeuner au moment de la réservation' AFTER `mode_reservation`;

-- 4. Seule la Résidence Ely Abdoulaye Diallo propose le petit-déjeuner (pas le Necker)
UPDATE `espaces` SET `option_petit_dejeuner` = 1 WHERE `slug` = 'residence-ely-abdoulaye-diallo';
UPDATE `espaces` SET `option_petit_dejeuner` = 0 WHERE `slug` = 'centre-tidiani-coulibaly-dit-necker';
