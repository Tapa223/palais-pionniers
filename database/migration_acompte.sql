-- =====================================================================
--  MIGRATION — Acompte / paiement en plusieurs versements
--  À exécuter une seule fois.
--  Généré le 10/08/2026
-- =====================================================================

ALTER TABLE `reservations`
  MODIFY COLUMN `statut_paiement` ENUM('non_paye','attente_paiement','partiellement_paye','paye') NOT NULL DEFAULT 'non_paye';
