-- =====================================================================
--  MIGRATION — Introduction du rôle admin_dg (Admin DG Palais)
--  À exécuter une seule fois sur une base déjà en production.
--  Généré le 25/07/2026
-- =====================================================================

-- 1. Ajoute 'admin_dg' aux valeurs possibles du rôle
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('user','superadmin','admin_dg','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable')
  NOT NULL DEFAULT 'user';

-- 2. Reclasse le compte "Direction Générale" existant (id=1) en admin_dg
--    et libère l'adresse superadmin@... pour le vrai compte technique
UPDATE `users`
   SET `email` = 'dg@palaisdespionniers.ml', `role` = 'admin_dg'
 WHERE `id` = 1 AND `email` = 'superadmin@palaisdespionniers.ml';

-- 3. Crée le compte superadmin technique séparé
--    Mot de passe par défaut : ChangeMoi@2026 (à changer à la première connexion)
INSERT INTO `users` (`nom_complet`, `email`, `telephone`, `password_hash`, `role`, `actif`)
SELECT 'Administration Système', 'superadmin@palaisdespionniers.ml', NULL,
       '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin', 1
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'superadmin@palaisdespionniers.ml');
