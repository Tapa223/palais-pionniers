-- =====================================================================
--  MIGRATION — Priorité ministérielle (réquisition d'un espace réservé)
--  À exécuter une seule fois.
--  Généré le 09/08/2026
-- =====================================================================

ALTER TABLE `reservations`
  MODIFY COLUMN `statut` ENUM('en_attente','validee','refusee','annulee','expiree','requisitionnee') NOT NULL DEFAULT 'en_attente';

CREATE TABLE IF NOT EXISTS `requisitions_ministerielles` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id`   INT UNSIGNED NOT NULL,
  `motif`            TEXT NOT NULL,
  `declenche_par`    INT UNSIGNED NOT NULL,
  `date_declenchee`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `choix_client`     ENUM('remboursement','nouvelle_date','autre_espace') DEFAULT NULL,
  `details_choix`    TEXT DEFAULT NULL COMMENT 'Précisions du client selon son choix (date souhaitée, espace souhaité...)',
  `date_choix`       TIMESTAMP NULL DEFAULT NULL,
  `statut`           ENUM('en_attente_choix','traite') NOT NULL DEFAULT 'en_attente_choix',
  `traite_par`       INT UNSIGNED DEFAULT NULL,
  `date_traitement`  TIMESTAMP NULL DEFAULT NULL,
  `note_traitement`  TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_requisition_reservation` (`reservation_id`),
  CONSTRAINT `fk_requisition_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Réquisitions ministérielles/institutionnelles d''un espace déjà réservé';
