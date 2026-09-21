-- =====================================================================
--  MIGRATION PHASE 3b — Statut "expiree" dédié pour les réservations
--  auto-annulées après 48h sans paiement (distinct d'un refus ou d'une
--  annulation manuelle).
--  À exécuter une fois, sur une base qui a déjà `date_validation`
--  (déjà présente si tu es repartie de l'installation fraîche récente).
--  Généré le 27/07/2026
-- =====================================================================

ALTER TABLE `reservations`
  MODIFY COLUMN `statut` ENUM('en_attente','validee','refusee','annulee','expiree') NOT NULL DEFAULT 'en_attente'
  COMMENT 'expiree = auto-annulée après 48h sans paiement (distinct d''un refus ou d''une annulation manuelle)';
