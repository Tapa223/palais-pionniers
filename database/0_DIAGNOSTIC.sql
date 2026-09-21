-- =====================================================================
--  DIAGNOSTIC — Quelles migrations sont déjà appliquées sur ta base ?
--  Colle le résultat de cette requête pour savoir exactement où tu en es.
--  Généré le 29/07/2026
-- =====================================================================

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role' AND COLUMN_TYPE LIKE '%admin_dg%') AS `1_admin_dg_role`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='espaces' AND COLUMN_NAME='mode_reservation') AS `2_hebergement_mode_reservation`,
  (SELECT COUNT(*) FROM information_schema.TABLES  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='services_annexes') AS `2b_hebergement_services_annexes`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='quantite') AS `3_quantite_petitdej`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='date_validation') AS `4_expiration_notifs`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='statut' AND COLUMN_TYPE LIKE '%expiree%') AS `5_statut_expiree`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='espaces' AND COLUMN_NAME='gerant_externe') AS `6_gerant_externe`,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='tarifs' AND COLUMN_NAME='gerant_nom') AS `7_gerant_tarif`;

-- Lecture du résultat : 1 = migration déjà appliquée, 0 = pas encore appliquée.
-- Exécute uniquement les fichiers dont le chiffre correspondant est à 0,
-- dans l'ordre numérique (1 → 7).
