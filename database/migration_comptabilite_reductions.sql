-- =====================================================================
--  MIGRATION — Comptabilité : montant initial figé + réductions accordées
--  À exécuter une seule fois (idempotente : peut être relancée sans effet).
--  Généré le 29/09/2026
--
--  1. reservations.montant_initial
--     Tarif normal de la réservation AVANT toute réduction, figé lors de la
--     validation (ou de la saisie au guichet). NULL pour les anciennes
--     réservations : le montant est alors recalculé à partir du tarif,
--     exactement comme avant, puis figé lors de la première opération
--     comptable (paiement ou réduction). Aucune donnée existante n'est
--     modifiée par cette migration.
--
--  2. reductions_accordees
--     Réduction décidée au guichet, indépendante des paiements.
--     statut :
--       appliquee      → diminue le montant net dû ;
--       non_appliquee  → accordée mais non utilisée (le client a réglé le
--                         plein tarif) : aucun effet financier ;
--       annulee        → retirée par l'administration : aucun effet financier.
--     Une seule réduction « appliquee » par réservation, garantie par la
--     colonne générée reservation_appliquee et son index UNIQUE.
--
--  Les anciennes réductions (paiements.motif_reduction) restent en place
--  et consultables : elles ne sont ni déplacées ni modifiées.
-- =====================================================================

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `montant_initial` DECIMAL(12,2) UNSIGNED DEFAULT NULL
  COMMENT 'Tarif normal avant toute réduction, figé à la validation (NULL = anciennes réservations, recalculé depuis le tarif)'
  AFTER `quantite`;

CREATE TABLE IF NOT EXISTS `reductions_accordees` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id`        INT UNSIGNED NOT NULL,
  `montant_reduction`     DECIMAL(12,2) UNSIGNED NOT NULL COMMENT 'Montant de la réduction en F CFA',
  `pourcentage`           DECIMAL(5,2) UNSIGNED DEFAULT NULL COMMENT 'Pourcentage saisi, à titre indicatif',
  `motif`                 TEXT DEFAULT NULL,
  `autorise_par`          VARCHAR(150) DEFAULT NULL COMMENT 'Autorité ayant accordé la réduction (DG, Ministre...), si différente du comptable',
  `reference_accord`      VARCHAR(150) DEFAULT NULL COMMENT 'Référence de la note / lettre d''accord',
  `statut`                ENUM('appliquee','non_appliquee','annulee') NOT NULL DEFAULT 'appliquee',
  `motif_statut`          TEXT DEFAULT NULL COMMENT 'Raison du passage en non_appliquee / annulee',
  `reportee_de`           INT UNSIGNED DEFAULT NULL COMMENT 'Réduction d''origine reportée (réquisition → nouvelle réservation)',
  `saisi_par`             INT UNSIGNED NOT NULL,
  `statut_modifie_par`    INT UNSIGNED DEFAULT NULL,
  `statut_modifie_le`     DATETIME DEFAULT NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reservation_appliquee` INT UNSIGNED AS (IF(`statut` = 'appliquee', `reservation_id`, NULL)) STORED
                          COMMENT 'Technique : garantit une seule réduction appliquée par réservation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reduction_appliquee` (`reservation_appliquee`),
  KEY `idx_reduction_reservation` (`reservation_id`, `statut`),
  KEY `idx_reduction_saisi_par` (`saisi_par`),
  KEY `idx_reduction_reportee_de` (`reportee_de`),
  CONSTRAINT `fk_reduction_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  CONSTRAINT `fk_reduction_saisi_par` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_reduction_statut_par` FOREIGN KEY (`statut_modifie_par`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_reduction_reportee_de` FOREIGN KEY (`reportee_de`) REFERENCES `reductions_accordees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Réductions accordées au guichet (distinctes des paiements)';

-- =====================================================================
--  Origine des réductions (ajout du 29/09/2026 — déjà exécuté en local)
--  commerciale  : réduction accordée au guichet (comportement actuel)
--  requisition  : prise en charge suite à réquisition (maintien du tarif)
--  Les réductions existantes prennent la valeur « commerciale ».
--  La contrainte « une seule réduction appliquée par réservation » est conservée.
-- =====================================================================

ALTER TABLE `reductions_accordees`
  ADD COLUMN IF NOT EXISTS `origine` ENUM('commerciale','requisition') NOT NULL DEFAULT 'commerciale'
    COMMENT 'commerciale = réduction accordée au guichet ; requisition = prise en charge du surcoût imposé par une réquisition'
    AFTER `statut`;

ALTER TABLE `reductions_accordees`
  ADD INDEX IF NOT EXISTS `idx_reduction_origine` (`origine`, `statut`);
