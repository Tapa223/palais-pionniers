-- =====================================================================
--  MIGRATION — Date de naissance pour les jeunes engagés
--  (la limite d'âge 10-35 ans a été retirée du formulaire)
--  À exécuter une seule fois.
--  Généré le 13/08/2026
-- =====================================================================

ALTER TABLE `jeunes_engages`
  ADD COLUMN IF NOT EXISTS `date_naissance` DATE DEFAULT NULL
  AFTER `age`;
