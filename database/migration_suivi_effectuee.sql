-- =====================================================================
-- Palais des Pionniers — Suivi des réservations : case « Effectuée »
-- À exécuter une fois dans phpMyAdmin (onglet SQL), base palais_pionniers.
-- Ajoute la date et l'auteur de la confirmation « réservation effectuée ».
-- Ré-exécutable sans erreur.
-- =====================================================================

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `effectuee_le` DATETIME NULL DEFAULT NULL
      COMMENT 'Date à laquelle la réservation a été confirmée comme effectuée',
  ADD COLUMN IF NOT EXISTS `effectuee_par` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'Administrateur ayant coché « Effectuée » (vide = antérieure à la mise en place du suivi)',
  ADD INDEX IF NOT EXISTS `idx_resa_effectuee` (`effectuee_le`);

-- Historique : les réservations payées déjà terminées au moment de l'installation
-- sont considérées comme effectuées (sinon elles rempliraient la liste « À confirmer »).
-- Cette mise à jour ne s'applique qu'à la toute première exécution : dès qu'une
-- réservation a été cochée depuis l'administration, elle ne fait plus rien.
UPDATE `reservations` r
JOIN `espaces` e ON e.id = r.espace_id
SET r.effectuee_le = CASE WHEN e.mode_reservation = 'sejour'
                          THEN TIMESTAMP(COALESCE(r.date_depart, r.date_resa), '12:00:00')
                          ELSE TIMESTAMP(r.date_resa, COALESCE(r.heure_fin, '23:59:59')) END
WHERE r.effectuee_le IS NULL
  AND r.statut = 'validee'
  AND r.statut_paiement IN ('paye', 'partiellement_paye')
  AND (CASE WHEN e.mode_reservation = 'sejour'
            THEN TIMESTAMP(COALESCE(r.date_depart, r.date_resa), '12:00:00')
            ELSE TIMESTAMP(r.date_resa, COALESCE(r.heure_fin, '23:59:59')) END) <= NOW()
  AND (SELECT t.n FROM (SELECT COUNT(*) AS n FROM `reservations` WHERE `effectuee_le` IS NOT NULL) AS t) = 0;
