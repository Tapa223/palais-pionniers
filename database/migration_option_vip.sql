-- =====================================================================
--  MIGRATION — Option VIP en supplément (au lieu d'un tarif séparé)
--  À exécuter une seule fois.
--  Généré le 30/07/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `option_vip` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Propose un supplément VIP (espace/accueil réservé aux personnalités) au moment de la réservation'
  AFTER `option_petit_dejeuner`,
  ADD COLUMN IF NOT EXISTS `prix_vip` DECIMAL(12,2) DEFAULT NULL
  COMMENT 'Montant du supplément VIP, si option_vip actif' AFTER `option_vip`;

UPDATE `espaces` SET `option_vip` = 1, `prix_vip` = 150000.00 WHERE `slug` = 'salle-seydou-badian';

DELETE FROM `tarifs` WHERE `espace_id` = (SELECT id FROM (SELECT id FROM espaces WHERE slug = 'salle-seydou-badian') t)
  AND `libelle` LIKE '%VVIP%';

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `vip` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Option accueil VIP (supplément, espaces qui le proposent)' AFTER `petit_dejeuner`;
