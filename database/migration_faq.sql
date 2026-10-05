-- =====================================================================
-- Palais des Pionniers — FAQ publique
-- À exécuter une fois dans phpMyAdmin (onglet SQL), base palais_pionniers.
-- Crée la table `faq` et y ajoute des premières questions/réponses,
-- modifiables ensuite depuis Administration > FAQ.
-- Ré-exécutable : la table n'est créée qu'une fois et aucune question n'est dupliquée.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS faq (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question   VARCHAR(255) NOT NULL,
  reponse    TEXT         NOT NULL,
  categorie  VARCHAR(100) DEFAULT NULL,
  ordre      INT          NOT NULL DEFAULT 0,
  actif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faq_actif_ordre (actif, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO faq (question, reponse, categorie, ordre)
SELECT * FROM (
  SELECT 'Comment réserver un espace du Palais ?' AS question,
         'Créez un compte (bouton « Réserver » en haut du site), puis ouvrez la fiche de l''espace souhaité et cliquez sur « Réserver cet espace ». Choisissez le tarif, la date et les horaires (ou les dates de séjour pour un hébergement), indiquez le motif et votre téléphone, puis envoyez votre demande. Elle est examinée par l''administration, en principe sous 24 heures.' AS reponse,
         'Réservations' AS categorie, 10 AS ordre
  UNION ALL SELECT 'Faut-il un compte pour réserver ?',
         'Oui. La consultation du site est libre, mais la réservation en ligne nécessite un compte client. Sa création est gratuite et ne prend qu''une minute.',
         'Réservations', 20
  UNION ALL SELECT 'Comment savoir si un espace est disponible ?',
         'Sur la fiche de l''espace, le bouton « Voir les disponibilités » affiche le calendrier du mois. Dans le formulaire de réservation, le choix d''une date affiche en détail les plages occupées et les créneaux encore libres.',
         'Réservations', 30
  UNION ALL SELECT 'Comment et où payer ma réservation ?',
         'Le paiement se fait au guichet du Palais, en espèces, par Orange Money ou par Moov Money, après la validation de votre demande. Présentez-vous avec la référence de votre réservation (RESA-…) ; votre bon de réservation est téléchargeable dans « Mon compte ».',
         'Paiements', 40
  UNION ALL SELECT 'De combien de temps est-ce que je dispose pour payer ?',
         'Vous disposez de 48 heures après la validation de votre demande pour effectuer un premier paiement (reportées au lundi si l''échéance tombe un week-end). Sans paiement dans ce délai, la réservation est annulée automatiquement.',
         'Paiements', 50
  UNION ALL SELECT 'Puis-je payer en plusieurs fois ?',
         'Oui. Vous pouvez verser un acompte au guichet ; le solde doit être réglé au plus tard 24 heures avant le début de votre réservation.',
         'Paiements', 60
  UNION ALL SELECT 'Pourquoi le créneau que j''avais demandé n''est-il plus disponible ?',
         'Plusieurs demandes peuvent être validées sur un même créneau : la salle revient à la première personne qui paie. Réglez donc rapidement votre réservation une fois qu''elle est validée. En cas d''annulation, une notification vous indique les créneaux encore libres ce jour-là.',
         'Paiements', 70
  UNION ALL SELECT 'Comment annuler ou modifier ma réservation ?',
         'Si vous n''avez pas encore payé, il suffit de ne pas régler : la réservation est annulée automatiquement à l''échéance, sans frais. Pour changer de date, d''horaire ou d''espace, ou si vous avez déjà payé, contactez le Palais (page Contact ou guichet) en indiquant votre référence de réservation.',
         'Annulation et réquisition', 80
  UNION ALL SELECT 'Qu''est-ce qu''une réquisition ?',
         'Exceptionnellement, un espace peut être repris pour un besoin institutionnel prioritaire (activité du Ministère, du Gouvernement ou urgence nationale), même après validation. Vous êtes alors prévenu et choisissez dans votre espace : annulation, remboursement si vous avez déjà payé, nouvelle date ou autre espace.',
         'Annulation et réquisition', 90
  UNION ALL SELECT 'Où trouver mon bon de réservation et ma facture ?',
         'Dans « Mon compte », onglet « Réservations ». Le bon de réservation est disponible dès la validation ; la facture d''acompte ou la facture définitive apparaît après chaque paiement enregistré.',
         'Mon compte', 100
  UNION ALL SELECT 'Comment louer un espace sur une longue durée ?',
         'Les associations, clubs ou entreprises peuvent demander un bail (mensuel, trimestriel, semestriel ou annuel) depuis la fiche d''un espace, avec le bouton « Demander à prendre en bail ». L''administration étudie la demande et vous recontacte pour les modalités.',
         'Bail et services', 110
  UNION ALL SELECT 'Quels sont les horaires et les coordonnées du Palais ?',
         'Le Palais des Pionniers est situé à Magnambougou / Dianéguéla, à Bamako. Il est ouvert du lundi au samedi, de 8 h à 18 h. Vous pouvez nous écrire depuis la page Contact.',
         'Informations pratiques', 120
) AS nouvelles
WHERE NOT EXISTS (SELECT 1 FROM faq f WHERE f.question = nouvelles.question);
