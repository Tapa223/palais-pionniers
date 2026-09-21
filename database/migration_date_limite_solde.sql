-- =====================================================================
--  MIGRATION — Date limite pour régler le solde d'un acompte
--  À exécuter une seule fois.
--  Généré le 11/08/2026
-- =====================================================================

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `date_limite_solde` DATE DEFAULT NULL
  COMMENT 'Date limite pour régler le solde d''un acompte'
  AFTER `statut_paiement`;
