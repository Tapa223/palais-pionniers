-- =====================================================================
-- Observations : réquisitions et remboursements
-- ---------------------------------------------------------------------
-- Ajoute deux types d'objets pouvant recevoir une observation :
--   - requisition   : dossier de réquisition (REQ-X)
--   - remboursement : remboursement / trop-perçu (bon BR-…)
-- Les observations existantes (reservation, paiement, activite, espace)
-- ne sont pas modifiées. Rétrocompatible, idempotent (MODIFY peut être
-- relancé sans effet). À exécuter manuellement dans phpMyAdmin.
-- MariaDB 10.4+
-- =====================================================================

ALTER TABLE `observations`
  MODIFY `cible_type` ENUM('reservation','paiement','activite','espace','requisition','remboursement') NOT NULL;
