-- =====================================================================
--  MIGRATION — Table direction (Ministre/DG/DGA éditables depuis l'admin)
--  À exécuter une seule fois.
--  Généré le 30/07/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `direction` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key`  ENUM('ministre','dg','dga') NOT NULL,
  `nom`       VARCHAR(200) NOT NULL,
  `titre`     VARCHAR(255) DEFAULT NULL,
  `citation`  VARCHAR(500) DEFAULT NULL COMMENT 'Phrase de mise en avant',
  `texte`     TEXT DEFAULT NULL COMMENT 'Paragraphe complet',
  `photo`     VARCHAR(255) DEFAULT NULL COMMENT 'Si vide, un avatar avec initiales est affiché',
  `ordre`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Ministre, DG, DGA — contenu affiché sur accueil et à propos';

INSERT IGNORE INTO `direction` (`role_key`, `nom`, `titre`, `citation`, `texte`, `photo`, `ordre`) VALUES
('ministre', 'M. Abdoul Kassim Ibrahim Fomba', 'Ministre de la Jeunesse et des Sports, chargé de l''Instruction Civique et de la Construction Citoyenne',
 'Le Palais est le levier stratégique de notre politique de construction citoyenne.',
 'Sous l''impulsion du Gouvernement, nous avons confié au Palais des Pionniers la mission noble de transformer notre jeunesse en un rempart inébranlable contre l''incivisme. C''est ici que s''enseigne l''amour sacré de la patrie.',
 'minis.jpeg', 1),
('dg', 'Mr Sidi Dicko', 'Directeur Général du Palais des Pionniers',
 'Nous transformons la vision nationale en actes concrets pour la patrie.',
 'En notre qualité d''EPST, nous œuvrons quotidiennement à produire de la compétence citoyenne. Le Palais est une véritable usine à bâtisseurs de nation, garantissant que chaque parcours soit une pierre solide à l''édifice du Mali souverain.',
 'dg.jpeg', 2),
('dga', 'Nouhoum Chérif HAÏDARA', 'Directeur Général Adjoint du Palais des Pionniers',
 'Une jeunesse formée, c''est une nation qui avance.',
 'Jeune, rigoureusement formé et pleinement dévoué à la mission du Palais, le Directeur Général Adjoint incarne cette nouvelle génération de cadres maliens qui allient exigence académique et engagement de terrain, au service quotidien de la jeunesse pionnière.',
 NULL, 3);
