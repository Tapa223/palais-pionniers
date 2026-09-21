-- =====================================================================
--  MIGRATION — Renommage des espaces annexes de la Résidence Ely,
--  ajout de la Salle Informatique, Cantine/Réfectoire en bail
--  À exécuter une seule fois, après les migrations précédentes.
--  Généré le 29/07/2026
-- =====================================================================

-- 1. Retire le suffixe "— Résidence Ely" (il ne concerne que la résidence
--    elle-même, pas ses annexes) — basé sur l'ancien slug pour être sûr
--    de cibler la bonne ligne même si le nom a déjà été modifié à la main.
UPDATE `espaces` SET `nom` = 'Salle de Séminaire', `slug` = 'salle-de-seminaire'
  WHERE `slug` = 'salle-de-seminaire-residence-ely';
UPDATE `espaces` SET `nom` = 'Cantine', `slug` = 'cantine'
  WHERE `slug` = 'cantine-residence-ely';
UPDATE `espaces` SET `nom` = 'Réfectoire', `slug` = 'refectoire'
  WHERE `slug` = 'refectoire-residence-ely';
UPDATE `espaces` SET `nom` = 'Salles de Classe', `slug` = 'salles-de-classe'
  WHERE `slug` = 'salles-de-classe-residence-ely';
UPDATE `espaces` SET `nom` = 'Cour Événementielle', `slug` = 'cour-evenementielle'
  WHERE `slug` = 'cour-evenementielle-residence-ely';

-- 2. Cantine et Réfectoire rejoignent la liste des espaces déjà en bail
UPDATE `espaces` SET `gerant_externe` = 'Cet espace est géré par un tiers. Contactez l''administration du Palais pour obtenir les coordonnées du gestionnaire.'
WHERE `slug` IN ('cantine','refectoire') AND (`gerant_externe` IS NULL OR `gerant_externe` = '');

-- 3. Nouvel espace : Salle Informatique
INSERT INTO `espaces` (`nom`, `slug`, `categorie_id`, `capacite`, `description`, `mode_reservation`, `disponible`)
SELECT 'Salle Informatique', 'salle-informatique', 2, 'Postes informatiques',
       'Salle équipée de postes informatiques, dédiée aux formations numériques, à l''initiation à l''outil informatique et aux ateliers de bureautique.',
       'creneau', 1
WHERE NOT EXISTS (SELECT 1 FROM `espaces` WHERE `slug` = 'salle-informatique');

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location journée', 50000.00, 'jour', NULL, 0
FROM `espaces` WHERE `slug` = 'salle-informatique'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` WHERE espace_id = espaces.id);
