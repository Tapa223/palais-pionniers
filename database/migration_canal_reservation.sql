-- =====================================================================
--  MIGRATION — Canal de réservation (en ligne / guichet)
--  À exécuter une seule fois.
--  Généré le 31/07/2026
-- =====================================================================

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `canal` ENUM('en_ligne','guichet') NOT NULL DEFAULT 'en_ligne'
  COMMENT 'Réservation faite en ligne par le client, ou saisie directement au guichet par un admin'
  AFTER `vip`;

-- Les réservations déjà existantes créées via le guichet sont difficiles à
-- distinguer rétroactivement avec certitude (colonne absente jusqu'ici) —
-- elles restent donc classées "en_ligne" par défaut. Seules les nouvelles
-- réservations faites au guichet à partir de maintenant seront comptées
-- correctement dans la carte du tableau de bord.
