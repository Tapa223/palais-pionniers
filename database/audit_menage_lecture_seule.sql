-- =====================================================================
-- AUDIT MÉNAGE / DONNÉES ORPHELINES — LECTURE SEULE
-- ---------------------------------------------------------------------
-- À exécuter dans phpMyAdmin (onglet SQL) pour obtenir des compteurs.
-- Ce fichier ne contient que des SELECT : il ne modifie ni ne supprime rien.
-- Suppose les migrations partenaires/suggestions/services et rôle partenaire
-- appliquées.
-- =====================================================================
SELECT 'role partenaire sans fiche' k, COUNT(*) FROM users WHERE role='partenaire' AND partenaire_id IS NULL
UNION ALL SELECT 'compte non partenaire avec partenaire_id', COUNT(*) FROM users WHERE role<>'partenaire' AND partenaire_id IS NOT NULL
UNION ALL SELECT 'partenaire sans compte', COUNT(*) FROM partenaires p WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.partenaire_id=p.id)
UNION ALL SELECT 'comptes bloqués (actif=0)', COUNT(*) FROM users WHERE actif=0
UNION ALL SELECT 'demandes_services statut traitee (ancien)', COUNT(*) FROM demandes_services WHERE statut='traitee'
UNION ALL SELECT 'demandes sur service inactif', COUNT(*) FROM demandes_services d JOIN services_annexes s ON s.id=d.service_id WHERE s.actif=0
UNION ALL SELECT 'réquisitions en_attente_choix > 30 j', COUNT(*) FROM requisitions_ministerielles WHERE statut='en_attente_choix' AND date_declenchee < NOW()-INTERVAL 30 DAY
UNION ALL SELECT 'réservations validées sans montant_initial', COUNT(*) FROM reservations WHERE statut='validee' AND montant_initial IS NULL
UNION ALL SELECT 'réservations sans tarif (tarif_id NULL)', COUNT(*) FROM reservations WHERE tarif_id IS NULL
UNION ALL SELECT 'réservations en_attente passées (date < aujourd hui)', COUNT(*) FROM reservations WHERE statut='en_attente' AND date_resa < CURDATE()
UNION ALL SELECT 'paiements sur réservation non validée', COUNT(*) FROM paiements p JOIN reservations r ON r.id=p.reservation_id WHERE r.statut IN ('en_attente','refusee')
UNION ALL SELECT 'statut_paiement=paye sans paiement', COUNT(*) FROM reservations r WHERE r.statut_paiement='paye' AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.reservation_id=r.id)
UNION ALL SELECT 'statut_paiement=non_paye avec paiement', COUNT(*) FROM reservations r WHERE r.statut_paiement='non_paye' AND EXISTS (SELECT 1 FROM paiements p WHERE p.reservation_id=r.id)
UNION ALL SELECT 'notifications lues', COUNT(*) FROM notifications WHERE lu=1
UNION ALL SELECT 'notifications lues > 90 j', COUNT(*) FROM notifications WHERE lu=1 AND created_at < NOW()-INTERVAL 90 DAY
UNION ALL SELECT 'notifications vers compte inexistant', COUNT(*) FROM notifications n WHERE n.destinataire_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id=n.destinataire_id)
UNION ALL SELECT 'tarifs jamais utilisés', COUNT(*) FROM tarifs t WHERE NOT EXISTS (SELECT 1 FROM reservations r WHERE r.tarif_id=t.id)
UNION ALL SELECT 'espaces indisponibles', COUNT(*) FROM espaces WHERE disponible=0
UNION ALL SELECT 'espaces sans réservation ni bail', COUNT(*) FROM espaces e WHERE NOT EXISTS (SELECT 1 FROM reservations r WHERE r.espace_id=e.id) AND NOT EXISTS (SELECT 1 FROM demandes_bail d WHERE d.espace_id=e.id) AND NOT EXISTS (SELECT 1 FROM bail_paiements b WHERE b.espace_id=e.id)
UNION ALL SELECT 'activity_log (lignes)', COUNT(*) FROM activity_log
UNION ALL SELECT 'activity_log utilisateur supprimé', COUNT(*) FROM activity_log l WHERE l.user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id=l.user_id)
UNION ALL SELECT 'messages de contact', COUNT(*) FROM messages
UNION ALL SELECT 'suggestions traitées', COUNT(*) FROM suggestions WHERE statut='traitee';
