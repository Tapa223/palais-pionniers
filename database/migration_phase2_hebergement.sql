-- =====================================================================
--  MIGRATION PHASE 2 — Hébergement en nuitées, nouveaux espaces,
--  services annexes, réductions comptable
--  À exécuter une seule fois, après migration_admin_dg.sql
--  Généré le 26/07/2026
-- =====================================================================

-- 1. Espaces : mode de réservation (créneau vs séjour)
ALTER TABLE `espaces`
  ADD COLUMN `mode_reservation` ENUM('creneau','sejour') NOT NULL DEFAULT 'creneau'
  COMMENT 'creneau = date + heure début/fin ; sejour = arrivée/départ en nuitées'
  AFTER `equipements`;

-- 2. Tarifs : inventaire de quantité, flag bail, unités supplémentaires
ALTER TABLE `tarifs`
  MODIFY COLUMN `unite` ENUM('heure','demi-journee','jour','mois','match','nuitée','événement','seance','personne_jour','personne_mois','personne_an','activite','support')
  NOT NULL DEFAULT 'jour',
  ADD COLUMN `quantite_disponible` INT UNSIGNED DEFAULT NULL
  COMMENT 'Nombre d''unités identiques disponibles — NULL = unité unique' AFTER `unite`,
  ADD COLUMN `est_bail` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Location longue durée négociée directement — jamais réservable en ligne' AFTER `quantite_disponible`;

-- 3. Réservations : arrivée/départ en nuitées + option petit-déjeuner
ALTER TABLE `reservations`
  MODIFY COLUMN `heure_debut` TIME DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  MODIFY COLUMN `heure_fin`   TIME DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  ADD COLUMN `date_depart` DATE DEFAULT NULL COMMENT 'Date de départ — mode séjour uniquement' AFTER `date_resa`,
  ADD COLUMN `petit_dejeuner` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Option petit-déjeuner (hébergement)' AFTER `date_depart`;

-- 4. Paiements : traçabilité des réductions accordées par le comptable
ALTER TABLE `paiements`
  ADD COLUMN `montant_reference` DECIMAL(12,2) DEFAULT NULL
  COMMENT 'Montant attendu selon le tarif, avant réduction' AFTER `montant`,
  ADD COLUMN `motif_reduction` TEXT DEFAULT NULL
  COMMENT 'Raison de la réduction accordée, si montant < montant_reference' AFTER `montant_reference`;

-- 5. Services annexes (informationnels, contact direct, non réservables)
CREATE TABLE IF NOT EXISTS `services_annexes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `montant`     DECIMAL(12,2) NOT NULL,
  `unite`       VARCHAR(50) NOT NULL DEFAULT 'mois',
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Prestations annexes informationnelles (contact direct, non réservables)';

INSERT INTO `services_annexes` (`nom`, `description`, `montant`, `unite`) VALUES
('Support publicitaire au sein du Palais des Pionniers',
 'Affichage publicitaire dans les espaces du Palais — visibilité auprès du public fréquentant les activités sportives et culturelles.',
 100000.00, 'mois'),
('Espace lavage auto et moto',
 'Service de lavage automobile et deux-roues disponible au sein du Palais des Pionniers.',
 100000.00, 'mois');

-- 6. Mise à jour du Centre Tidiani Coulibaly (existant) → mode séjour + inventaire réel
UPDATE `espaces` SET
  `nom` = 'Centre d''Accueil Tidiani COULIBALY dit Necker',
  `slug` = 'centre-tidiani-coulibaly-dit-necker',
  `capacite` = '27 chambres',
  `description` = 'Hébergement économique du Palais, pensé pour l''accueil de groupes, stagiaires et délégations en séjour à Bamako. 27 chambres réparties en deux formules : chambres ventilées, et chambres avec douche intérieure.',
  `mode_reservation` = 'sejour'
WHERE `slug` = 'centre-tidiani-coulibaly';

-- Corrige les tarifs existants du Necker (mêmes lignes, quantités + prix réels ajoutés)
UPDATE `tarifs` t JOIN `espaces` e ON e.id = t.espace_id
   SET t.libelle = 'Chambre ventilée sans douche', t.quantite_disponible = 20
 WHERE e.slug = 'centre-tidiani-coulibaly-dit-necker' AND t.libelle LIKE '%ventilée sans douche%';

UPDATE `tarifs` t JOIN `espaces` e ON e.id = t.espace_id
   SET t.libelle = 'Chambre avec douche intérieure', t.montant = 10000.00, t.quantite_disponible = 7
 WHERE e.slug = 'centre-tidiani-coulibaly-dit-necker' AND t.libelle LIKE '%avec douche intérieure%';

UPDATE `tarifs` t JOIN `espaces` e ON e.id = t.espace_id
   SET t.est_bail = 1
 WHERE e.slug = 'centre-tidiani-coulibaly-dit-necker' AND t.libelle LIKE '%bail%';

-- 7. Nouveaux espaces (Résidence Ely + annexes, nouvelles salles, terrain Handball)
INSERT INTO `espaces` (`nom`, `slug`, `categorie_id`, `capacite`, `description`, `mode_reservation`, `disponible`) VALUES
('Résidence Ely Abdoulaye DIALLO', 'residence-ely-abdoulaye-diallo', 4, '26 chambres',
 'Résidence haut de gamme du Palais : 26 chambres climatisées, entièrement équipées (téléviseur, douche intérieure), avec option petit-déjeuner. Un cadre confortable pour séjours officiels, missions ou délégations.',
 'sejour', 1),
('Salle de Séminaire — Résidence Ely', 'salle-de-seminaire-residence-ely', 2, 'Séminaires & réunions',
 'Salle de travail au sein de la Résidence Ely Abdoulaye Diallo, adaptée aux séminaires, formations et réunions de délégation en séjour sur place.',
 'creneau', 1),
('Cantine — Résidence Ely', 'cantine-residence-ely', 6, 'Location longue durée',
 'Espace cantine de la Résidence Ely Abdoulaye Diallo, proposé en location longue durée pour la restauration des résidents et du personnel.',
 'creneau', 1),
('Réfectoire — Résidence Ely', 'refectoire-residence-ely', 6, 'Salle de restauration',
 'Réfectoire de la Résidence Ely Abdoulaye Diallo, pour les repas de groupes en séjour — disponible à la journée ou en location longue durée.',
 'creneau', 1),
('Salles de Classe — Résidence Ely', 'salles-de-classe-residence-ely', 4, '6 salles',
 'Six salles de classe identiques au sein de la Résidence Ely Abdoulaye Diallo, louables à l''unité pour des formations, ateliers ou sessions pédagogiques.',
 'creneau', 1),
('Cour Événementielle — Résidence Ely', 'cour-evenementielle-residence-ely', 5, 'Événementiel',
 'Cour extérieure de la Résidence Ely Abdoulaye Diallo, pour l''organisation d''événements en plein air : cérémonies, réceptions, activités de groupe.',
 'creneau', 1),
('Salle Adama SAMASSEKOU', 'salle-adama-samassekou', 1, '200 places',
 'Amphithéâtre de taille moyenne, idéal pour les conférences, assemblées générales et cérémonies de moyenne envergure.',
 'creneau', 1),
('Salle de conférence Pr Assétou Founé SAMAKE MIGAN', 'salle-pr-assetou-founé-samake-migan', 2, '80 places',
 'Salle de conférence à taille humaine, adaptée aux réunions de travail, formations et ateliers nécessitant un cadre plus intimiste qu''un grand amphithéâtre.',
 'creneau', 1),
('Terrain de Handball', 'terrain-de-handball', 3, 'Équipes de club',
 'Terrain de handball extérieur, distinct du terrain de basketball, disponible pour l''entraînement de club et les matchs de gala.',
 'creneau', 1);

-- 8. Tarifs des nouveaux espaces
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Chambre climatisée (douche intérieure, TV)', 25000.00, 'nuitée', 26, 0 FROM `espaces` WHERE slug = 'residence-ely-abdoulaye-diallo';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en bail de la résidence entière', 10000000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'residence-ely-abdoulaye-diallo';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location journée', 100000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'salle-de-seminaire-residence-ely';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location de la cantine (bail)', 200000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'cantine-residence-ely';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location journée', 50000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'refectoire-residence-ely';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en bail', 500000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'refectoire-residence-ely';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location salle de classe', 25000.00, 'jour', 6, 0 FROM `espaces` WHERE slug = 'salles-de-classe-residence-ely';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Événement dans la cour', 200000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'cour-evenementielle-residence-ely';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location journée', 150000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'salle-adama-samassekou';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location journée', 150000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'salle-pr-assetou-founé-samake-migan';

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Séance d''entraînement', 10000.00, 'heure', NULL, 0 FROM `espaces` WHERE slug = 'terrain-de-handball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Club (abonnement mensuel)', 30000.00, 'mois', NULL, 0 FROM `espaces` WHERE slug = 'terrain-de-handball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Match de Gala', 50000.00, 'match', NULL, 0 FROM `espaces` WHERE slug = 'terrain-de-handball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en bail', 300000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'terrain-de-handball';

-- 9. Tarifs manquants de la Salle Daba Modibo Keita (déjà existante)
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location salle de gymnastique (PPP)', 500000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location salle de Karaté/Taekwondo (PPP)', 250000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Karaté/Taekwondo — inscription', 50000.00, 'personne_an', NULL, 0 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Karaté/Taekwondo — mensualité', 15000.00, 'personne_mois', NULL, 0 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Karaté/Taekwondo — location en bail', 500000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Salle multifonction', 200000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'salle-daba-modibo-keita';

-- 9bis. Corrections Terrain de Basketball (espace déjà existant, tarifs erronés au premier seed)
DELETE t FROM `tarifs` t JOIN `espaces` e ON e.id = t.espace_id WHERE e.slug = 'terrain-de-basketball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location terrain de basket (mensuel)', 150000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'terrain-de-basketball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Séance d''entraînement', 10000.00, 'seance', NULL, 0 FROM `espaces` WHERE slug = 'terrain-de-basketball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Match de Gala', 50000.00, 'match', NULL, 0 FROM `espaces` WHERE slug = 'terrain-de-basketball';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en bail', 300000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'terrain-de-basketball';

-- 9ter. Corrections Piscine (espace déjà existant, grille tarifaire incomplète au premier seed)
DELETE t FROM `tarifs` t JOIN `espaces` e ON e.id = t.espace_id WHERE e.slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en PPP', 750000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location pour dîner ou déjeuner', 200000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Fédération Aquatique du Mali', 50000.00, 'activite', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Redevance support publicitaire', 25000.00, 'support', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Natation enfant', 1500.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Natation adulte', 3000.00, 'jour', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Inscription natation (mensualité)', 10000.00, 'personne_mois', NULL, 0 FROM `espaces` WHERE slug = 'piscine';
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT id, 'Location en bail', 750000.00, 'mois', NULL, 1 FROM `espaces` WHERE slug = 'piscine';

-- 10. Descriptions pour les espaces existants qui n'en avaient pas encore
UPDATE `espaces` SET description = 'Grand amphithéâtre du Palais, taillé pour les cérémonies officielles, conférences de grande envergure et rassemblements institutionnels. Dispose d''un espace VVIP séparé pour les personnalités et délégations.' WHERE slug = 'salle-seydou-badian' AND (description IS NULL OR description = '');
UPDATE `espaces` SET description = 'Deuxième grand amphithéâtre du Palais, adapté aux mêmes usages que la Salle Seydou Badian : conférences, assemblées, cérémonies et grands événements institutionnels ou culturels.' WHERE slug = 'salle-sory-ibrahim-koita-dit-bomba' AND (description IS NULL OR description = '');
UPDATE `espaces` SET description = 'Terrain de football extérieur du Palais, ouvert aux séances d''entraînement individuelles ou de club, ainsi qu''aux matchs de gala et compétitions amicales.' WHERE slug = 'terrain-de-maracana' AND (description IS NULL OR description = '');
UPDATE `espaces` SET description = 'Piscine du Palais, ouverte aux cours de natation (enfants et adultes), aux entrées journalières, et à la location pour événements privés ou dînatoires.' WHERE slug = 'piscine' AND (description IS NULL OR description = '');
UPDATE `espaces` SET description = 'Terrain de basketball extérieur, disponible pour les séances d''entraînement de club et les matchs de gala.' WHERE slug = 'terrain-de-basketball' AND (description IS NULL OR description = '');
UPDATE `espaces` SET description = 'Complexe sportif polyvalent dédié aux arts martiaux et à la gymnastique, avec une salle multifonction pouvant accueillir d''autres disciplines et événements.' WHERE slug = 'salle-daba-modibo-keita' AND (description IS NULL OR description = '');
