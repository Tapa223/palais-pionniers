-- =====================================================================
--  MIGRATION — Retirer le faux statut "en bail" (données de test)
--  Ces 4 espaces proposent une option de location en bail (tarif est_bail=1)
--  mais n'étaient pas réellement loués — ils redeviennent "Bail possible"
--  au lieu de "En bail" tant qu'un vrai gestionnaire n'est pas renseigné.
--  À exécuter une seule fois.
--  Généré le 05/08/2026
-- =====================================================================

UPDATE `espaces`
SET `gerant_externe` = NULL, `gerant_nom` = NULL, `gerant_prenom` = NULL,
    `gerant_email` = NULL, `gerant_contact` = NULL, `gerant_user_id` = NULL
WHERE `slug` IN ('terrain-de-maracana','terrain-de-basketball','salle-de-gym-daba-modibo-keita','piscine');
