-- Nettoyage prudent des donnees de test - Palais des Pionniers
-- Source analysee : palais_pionnierslast.sql
-- Branche source : feature/comptabilite-paiements
-- Export source : 02/10/2026 01:26 (MariaDB 10.4.32)
--
-- IMPORTANT :
-- 1. Exportez d'abord la base palais_pionniers depuis phpMyAdmin.
-- 2. Ce script ne supprime aucune table et ne modifie aucune structure.
-- 3. Il conserve tous les comptes administrateurs (IDs 1 a 7), tous les
--    contenus editoriaux, espaces, photos, formations, tarifs et partenaires.
-- 4. Il cible les reservations de test associees au compte client ID 8
--    (Tapa COULIBALY), dont les motifs contiennent manifestement des chaines
--    de test et qui ont servi aux essais de reservations/requisitions/remboursements.
-- 5. Les reservations du partenaire CICB (ID 26) et de Sitan COULIBALY (ID 31)
--    sont conservees. Les comptes utilisateurs ne sont pas supprimes.
-- 6. Les journaux et notifications sont purges car ils retracent les essais.
-- 7. Les inscriptions jeunes_engages ne sont pas supprimees automatiquement :
--    elles contiennent des donnees personnelles et doivent etre verifiees
--    manuellement avant toute suppression.
--
-- Le script utilise les cles etrangeres existantes et les supprime dans l'ordre
-- enfant -> parent. Il ne desactive pas FOREIGN_KEY_CHECKS.

USE `palais_pionniers`;

START TRANSACTION;

-- Les essais de reservation du compte ID 8 sont les IDs explicites ci-dessous.
-- Les IDs 26 et 31 sont volontairement exclus.
CREATE TEMPORARY TABLE `tmp_reservations_test` (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=MEMORY;

INSERT INTO `tmp_reservations_test` (`id`) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
(11),(13),(14),(15),(16),(17),(18),(19),(20),
(21),(22),(23),(24),(25),(27),(28),(29),(30);

-- Garde-fou : ne traiter que les reservations appartenant au compte utilisateur 8.
DELETE rr
FROM `remboursements` rr
INNER JOIN `tmp_reservations_test` t ON t.id = rr.reservation_id
INNER JOIN `reservations` r ON r.id = rr.reservation_id
WHERE r.user_id = 8;

DELETE op
FROM `operations_requisition` op
INNER JOIN `tmp_reservations_test` t ON t.id = op.reservation_id
INNER JOIN `reservations` r ON r.id = op.reservation_id
WHERE r.user_id = 8;

DELETE red
FROM `reductions_accordees` red
INNER JOIN `tmp_reservations_test` t ON t.id = red.reservation_id
INNER JOIN `reservations` r ON r.id = red.reservation_id
WHERE r.user_id = 8;

DELETE req
FROM `requisitions_ministerielles` req
INNER JOIN `tmp_reservations_test` t ON t.id = req.reservation_id
INNER JOIN `reservations` r ON r.id = req.reservation_id
WHERE r.user_id = 8;

DELETE p
FROM `paiements` p
INNER JOIN `tmp_reservations_test` t ON t.id = p.reservation_id
INNER JOIN `reservations` r ON r.id = p.reservation_id
WHERE r.user_id = 8;

DELETE r
FROM `reservations` r
INNER JOIN `tmp_reservations_test` t ON t.id = r.id
WHERE r.user_id = 8;

-- Purge des historiques d'essais et notifications internes.
DELETE FROM `activity_log`;
DELETE FROM `notifications`;

DROP TEMPORARY TABLE `tmp_reservations_test`;

-- Verifications avant validation :
SELECT COUNT(*) AS reservations_restantes_compte_8
FROM `reservations`
WHERE `user_id` = 8;

SELECT COUNT(*) AS reservations_partenaires_conservees
FROM `reservations`
WHERE `id` IN (26,31);

SELECT COUNT(*) AS comptes_administrateurs_conserves
FROM `users`
WHERE `id` BETWEEN 1 AND 7;

-- Si les resultats sont conformes, validez la transaction dans phpMyAdmin.
-- Sinon, executez ROLLBACK au lieu de COMMIT.
-- COMMIT;
-- ROLLBACK;
