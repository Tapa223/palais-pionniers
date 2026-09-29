-- =====================================================================
-- MIGRATION — Rôle « partenaire » (compte utilisateur dédié)
-- ---------------------------------------------------------------------
-- À exécuter manuellement dans phpMyAdmin (MariaDB 10.4+), onglet SQL.
--
-- CONDITIONS PRÉALABLES
--   1. migration_partenaires_suggestions_services.sql déjà exécutée
--      (la colonne users.partenaire_id doit exister).
--   2. Aucun compte ne doit avoir un rôle absent de la liste ci-dessous.
--      Vérification (doit renvoyer 0) :
--        SELECT COUNT(*) FROM users
--        WHERE role NOT IN ('user','superadmin','ministre','admin_espaces',
--                           'admin_activites','admin_messages','admin_comptable',
--                           'partenaire');
--
-- Relançable : la 1re instruction redéfinit la même liste (sans effet si
-- 'partenaire' est déjà présent), la 2e ne trouve plus aucune ligne.
-- Aucune donnée supprimée.
-- =====================================================================

-- 1. Liste des rôles : les 7 rôles existants + 'partenaire' (aucun rôle retiré)
ALTER TABLE `users`
  MODIFY `role` ENUM('user','superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable','partenaire')
  NOT NULL DEFAULT 'user';

-- 2. Les comptes clients déjà associés à un partenaire deviennent « partenaire ».
--    Seuls les comptes 'user' sont concernés : aucun compte d'administration modifié.
UPDATE `users`
SET `role` = 'partenaire'
WHERE `role` = 'user'
  AND `partenaire_id` IS NOT NULL;
