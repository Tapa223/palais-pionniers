-- =====================================================================
--  CORRECTIF — Anciennes notifications de réquisition avec un lien obsolète
--  À exécuter une seule fois, dans phpMyAdmin par exemple.
--  Généré le 12/08/2026
-- =====================================================================

-- Corrige toutes les notifications de type "requisition_choix" (le comptable
-- est notifié quand le client fait son choix) pour qu'elles pointent vers
-- la vraie page dédiée, même celles créées avant la correction du code.
UPDATE `notifications`
SET `lien` = 'requisitions.php'
WHERE `type` = 'requisition_choix';
