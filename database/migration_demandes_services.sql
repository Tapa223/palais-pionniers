-- =====================================================================
--  MIGRATION — Demandes de services tracées (réservées aux comptes connectés)
--  À exécuter une seule fois.
--  Généré le 06/08/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `demandes_services` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `service_id`      INT UNSIGNED NOT NULL,
  `message`         TEXT DEFAULT NULL,
  `statut`          ENUM('en_attente','traitee') NOT NULL DEFAULT 'en_attente',
  `traite_par`      INT UNSIGNED DEFAULT NULL,
  `date_traitement` TIMESTAMP NULL DEFAULT NULL,
  `note_traitement` TEXT DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_demande_service_user` (`user_id`),
  KEY `fk_demande_service_service` (`service_id`),
  CONSTRAINT `fk_demande_service_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_demande_service_service` FOREIGN KEY (`service_id`) REFERENCES `services_annexes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Demandes tracées de services annexes, réservées aux comptes connectés';
