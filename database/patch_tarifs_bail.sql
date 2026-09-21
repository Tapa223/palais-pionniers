-- =====================================================================
--  CORRECTIF — Garantit la présence de tous les tarifs "Location en bail"
--  Script sûr à exécuter à tout moment, autant de fois que nécessaire :
--  chaque ligne n'est ajoutée que si elle n'existe pas déjà (aucun doublon,
--  aucune donnée existante écrasée).
--  Généré le 29/07/2026
-- =====================================================================

-- Terrain de Maracana — bail 500 000 F CFA/mois
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail', 500000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'terrain-de-maracana'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);

-- Terrain de Basketball — bail 300 000 F CFA/mois
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail', 300000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'terrain-de-basketball'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);

-- Terrain de Handball — bail 300 000 F CFA/mois
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail', 300000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'terrain-de-handball'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);

-- Piscine — bail 750 000 F CFA/mois
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail', 750000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'piscine'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1 AND t.libelle = 'Location en bail');

-- Salle Daba Modibo Keita — 3 lignes bail distinctes
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location salle de gymnastique (PPP)', 500000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'salle-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.libelle = 'Location salle de gymnastique (PPP)');

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Karaté/Taekwondo — location en bail', 500000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'salle-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.libelle = 'Karaté/Taekwondo — location en bail');

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Salle multifonction', 200000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'salle-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.libelle = 'Salle multifonction');

-- Résidence Ely — Cantine (bail 200 000 F CFA/mois)
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location de la cantine (bail)', 200000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'cantine-residence-ely'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);

-- Résidence Ely — Réfectoire (bail 500 000 F CFA/mois)
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail', 500000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'refectoire-residence-ely'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);

-- Résidence Ely — bail de la résidence entière (10 000 000 F CFA/mois)
INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`)
SELECT e.id, 'Location en bail de la résidence entière', 10000000.00, 'mois', NULL, 1
FROM `espaces` e
WHERE e.slug = 'residence-ely-abdoulaye-diallo'
  AND NOT EXISTS (SELECT 1 FROM `tarifs` t WHERE t.espace_id = e.id AND t.est_bail = 1);
