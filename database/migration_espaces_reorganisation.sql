-- =====================================================================
--  MIGRATION — Suppression Cantine/Réfectoire, scission de la Salle Daba
--  Modibo Keita en 3 espaces distincts (Gym/Taekwondo/Karaté),
--  correction de catégorie pour Adama Samassekou
--  À exécuter une seule fois.
--  Généré le 29/07/2026
-- =====================================================================

-- 1. Suppression complète de Cantine et Réfectoire (et de toute réservation
--    qui y ferait référence — ces deux espaces n'existent plus).
DELETE r FROM reservations r
  JOIN espaces e ON e.id = r.espace_id
  WHERE e.slug IN ('cantine','refectoire');
DELETE FROM espaces WHERE slug IN ('cantine','refectoire');

-- 2. Renommage : Salle Daba Modibo Keita devient la salle de Gym uniquement
UPDATE espaces SET nom = 'Salle de Gym Daba Modibo KEITA', slug = 'salle-de-gym-daba-modibo-keita',
  description = 'Salle de gymnastique du complexe Daba Modibo Keita, avec une salle multifonction pouvant accueillir d''autres disciplines et événements.'
WHERE slug = 'salle-daba-modibo-keita';

-- Retire les tarifs karaté/taekwondo de cet espace (ils vont sur les 2 nouvelles salles)
DELETE FROM tarifs WHERE espace_id = (SELECT id FROM espaces WHERE slug = 'salle-de-gym-daba-modibo-keita')
  AND libelle LIKE '%Karaté%';

-- 3. Nouvelles salles : Taekwondo et Karaté
INSERT INTO espaces (nom, slug, categorie_id, capacite, description, mode_reservation, disponible)
SELECT 'Salle de Taekwondo Daba Modibo KEITA', 'salle-de-taekwondo-daba-modibo-keita', categorie_id, 'Variable',
       'Salle dédiée à la pratique du taekwondo au sein du complexe Daba Modibo Keita.', 'creneau', 1
FROM espaces WHERE slug = 'salle-de-gym-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM espaces WHERE slug = 'salle-de-taekwondo-daba-modibo-keita');

INSERT INTO espaces (nom, slug, categorie_id, capacite, description, mode_reservation, disponible)
SELECT 'Salle de Karaté Daba Modibo KEITA', 'salle-de-karate-daba-modibo-keita', categorie_id, 'Variable',
       'Salle dédiée à la pratique du karaté au sein du complexe Daba Modibo Keita.', 'creneau', 1
FROM espaces WHERE slug = 'salle-de-gym-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM espaces WHERE slug = 'salle-de-karate-daba-modibo-keita');

-- Tarifs pour chacune (mêmes montants que l'ancienne offre combinée)
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Location salle (PPP)', 250000.00, 'mois', NULL, 1 FROM espaces WHERE slug = 'salle-de-taekwondo-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM tarifs WHERE espace_id = espaces.id);
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0 FROM espaces WHERE slug = 'salle-de-taekwondo-daba-modibo-keita';
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Mensualité', 15000.00, 'personne_mois', NULL, 0 FROM espaces WHERE slug = 'salle-de-taekwondo-daba-modibo-keita';
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Location en bail', 500000.00, 'mois', NULL, 1 FROM espaces WHERE slug = 'salle-de-taekwondo-daba-modibo-keita';

INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Location salle (PPP)', 250000.00, 'mois', NULL, 1 FROM espaces WHERE slug = 'salle-de-karate-daba-modibo-keita'
  AND NOT EXISTS (SELECT 1 FROM tarifs WHERE espace_id = espaces.id);
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0 FROM espaces WHERE slug = 'salle-de-karate-daba-modibo-keita';
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Mensualité', 15000.00, 'personne_mois', NULL, 0 FROM espaces WHERE slug = 'salle-de-karate-daba-modibo-keita';
INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail)
SELECT id, 'Location en bail', 500000.00, 'mois', NULL, 1 FROM espaces WHERE slug = 'salle-de-karate-daba-modibo-keita';

-- 4. Corriger la catégorie d'Adama Samassekou → Salle de conférence (id 2)
UPDATE espaces SET categorie_id = 2 WHERE slug = 'salle-adama-samassekou';

-- 5. Nettoyer la liste des espaces en bail (référence à l'ancien slug + retirer cantine/refectoire supprimés)
UPDATE espaces SET gerant_externe = NULL, gerant_nom = NULL, gerant_prenom = NULL, gerant_email = NULL, gerant_contact = NULL
  WHERE slug IN ('cantine','refectoire');
