-- =====================================================================
--  MIGRATION — Espaces gérés en bail par un tiers
--  À exécuter une seule fois, après les migrations précédentes.
--  Généré le 29/07/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `gerant_externe` VARCHAR(255) DEFAULT NULL
  COMMENT 'Si renseigné, cet espace est loué/géré par un tiers — la réservation en ligne est désactivée et ce texte (nom du gestionnaire) est affiché à la place'
  AFTER `option_petit_dejeuner`;

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `gerant_contact` VARCHAR(100) DEFAULT NULL
  COMMENT 'Téléphone/contact du gestionnaire externe de cet espace' AFTER `gerant_externe`;

-- Marque les 4 espaces concernés — texte générique par défaut, à
-- personnaliser depuis Admin > Espaces avec le vrai nom/contact.
UPDATE `espaces` SET `gerant_externe` = 'Cet espace est géré par un tiers. Contactez l''administration du Palais pour obtenir les coordonnées du gestionnaire.'
WHERE `slug` IN ('terrain-de-maracana','terrain-de-basketball','salle-daba-modibo-keita','piscine')
  AND (`gerant_externe` IS NULL OR `gerant_externe` = '');
