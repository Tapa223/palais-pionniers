-- =====================================================================
--  MIGRATION — Fusion du rôle "Admin DG" dans "Superadmin" (= Direction)
--  Superadmin représente désormais la Direction : tous les pouvoirs,
--  sauf l'encaissement (réservé au comptable, déjà bloqué ailleurs).
--  À exécuter une seule fois.
--  Généré le 01/08/2026
-- =====================================================================

-- 1. Élargir temporairement l'ENUM le temps de migrer les comptes existants
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('user','superadmin','admin_dg','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable')
  NOT NULL DEFAULT 'user';

-- 2. Migrer tous les comptes admin_dg existants vers superadmin
UPDATE `users` SET `role` = 'superadmin' WHERE `role` = 'admin_dg';

-- 3. Retirer définitivement admin_dg de l'ENUM (plus aucune ligne ne l'utilise)
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('user','superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable')
  NOT NULL DEFAULT 'user';
