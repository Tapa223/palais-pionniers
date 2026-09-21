-- =====================================================================
--  MIGRATION — Notion de groupe de bâtiment (bail groupé)
--  Permet de proposer, lors d'une demande de bail, de prendre aussi les
--  autres espaces du même bâtiment (ex: les 3 salles Daba Modibo Keita).
--  À exécuter une seule fois.
--  Généré le 05/08/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `groupe_batiment` VARCHAR(150) DEFAULT NULL
  COMMENT 'Nom du bâtiment si cet espace fait partie d''un ensemble (ex: "Daba Modibo Keita")'
  AFTER `resiliation_note`;

UPDATE `espaces` SET `groupe_batiment` = 'Daba Modibo Keita'
WHERE `slug` IN ('salle-de-gym-daba-modibo-keita','salle-de-taekwondo-daba-modibo-keita','salle-de-karate-daba-modibo-keita');

--
-- Table `demande_bail_espaces`
-- Espaces supplémentaires demandés en bail dans une même demande groupée
-- (en plus de l'espace principal déjà stocké sur demandes_bail.espace_id)
--
CREATE TABLE IF NOT EXISTS `demande_bail_espaces` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demande_id` INT UNSIGNED NOT NULL,
  `espace_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dbe_demande` (`demande_id`),
  KEY `fk_dbe_espace` (`espace_id`),
  CONSTRAINT `fk_dbe_demande` FOREIGN KEY (`demande_id`) REFERENCES `demandes_bail` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dbe_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Espaces supplémentaires inclus dans une demande de bail groupée';
