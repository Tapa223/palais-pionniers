-- =====================================================================
-- Palais des Pionniers — Nettoyage des données de test
-- ---------------------------------------------------------------------
-- À exécuter UNE SEULE FOIS dans phpMyAdmin (onglet SQL), sur la base
-- palais_pionniers, avant la mise en service.
-- Faites une sauvegarde (Exporter) avant de lancer ce script.
--
-- SUPPRIMÉ (données de test) :
--   - comptes clients (rôle « user ») et comptes partenaires (rôle « partenaire ») ;
--   - fiches partenaires ;
--   - réservations, paiements, acomptes, réductions, remboursements ;
--   - réquisitions et leurs opérations ;
--   - loyers de baux encaissés, demandes de bail, demandes de services ;
--   - notifications, journal d'activité, observations ;
--   - messages de contact, suggestions, inscriptions « S'engager ».
--   Les compteurs (numéros RESA, REQ, reçus…) repartent de 1.
--
-- CONSERVÉ (contenu réel du site) :
--   - comptes d'administration : Direction, Ministre, Admin Espaces,
--     Admin Activités, Admin Messages, Comptable ;
--   - espaces, photos, tarifs, catégories ;
--   - activités et leurs galeries, sections et liens ;
--   - formations et leurs galeries ;
--   - personnalités et biographies, Direction, personnel ;
--   - services annexes (catalogue), FAQ ;
--   - présentation des baux configurés sur les espaces (gestionnaire,
--     texte de bail) : seul le lien vers un compte client supprimé est retiré.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Circuit financier et réservations
TRUNCATE TABLE remboursements;
TRUNCATE TABLE operations_requisition;
TRUNCATE TABLE requisitions_ministerielles;
TRUNCATE TABLE reductions_accordees;
TRUNCATE TABLE paiements;
TRUNCATE TABLE reservations;

-- 2) Baux et services
TRUNCATE TABLE bail_paiements;
TRUNCATE TABLE demande_bail_espaces;
TRUNCATE TABLE demandes_bail;
TRUNCATE TABLE demandes_services;

-- 3) Échanges, suivi et traçabilité
TRUNCATE TABLE notifications;
TRUNCATE TABLE activity_log;
TRUNCATE TABLE observations;
TRUNCATE TABLE messages;
TRUNCATE TABLE suggestions;
TRUNCATE TABLE jeunes_engages;

-- 4) Comptes clients et partenaires (les administrateurs sont conservés)
DELETE FROM users WHERE role IN ('user', 'partenaire');
TRUNCATE TABLE partenaires;

-- 5) Espaces en bail : retirer le lien vers un compte client supprimé
UPDATE espaces
SET gerant_user_id = NULL,
    resiliation_demandee = 0,
    resiliation_demandee_le = NULL,
    resiliation_note = NULL
WHERE gerant_user_id IS NOT NULL
  AND gerant_user_id NOT IN (SELECT id FROM users);

SET FOREIGN_KEY_CHECKS = 1;

-- 6) Contrôle : comptes restants par rôle (doit n'afficher que des rôles d'administration)
SELECT role, COUNT(*) AS comptes FROM users GROUP BY role;
