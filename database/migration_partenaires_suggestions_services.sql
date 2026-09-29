-- =====================================================================
-- Partenaires, suggestions anonymes, cycle des demandes de services
-- ---------------------------------------------------------------------
-- À exécuter manuellement dans phpMyAdmin (MariaDB 10.4+).
-- Idempotent : peut être relancé sans effet (IF NOT EXISTS).
-- Aucune donnée existante n'est supprimée.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. PARTENAIRES
--    Un partenaire (CICB, ministère, institution…) est une fiche
--    « partenaires ». Un ou plusieurs comptes clients existants y sont
--    associés (users.partenaire_id) : ils réservent par le circuit
--    normal. Chaque réservation garde le partenaire du moment
--    (reservations.partenaire_id) pour les statistiques.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partenaires` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(200) NOT NULL,
  `type`        VARCHAR(100) DEFAULT NULL COMMENT 'Institution, ministère, entreprise, association…',
  `contact_nom` VARCHAR(200) DEFAULT NULL,
  `telephone`   VARCHAR(30)  DEFAULT NULL,
  `email`       VARCHAR(190) DEFAULT NULL,
  `notes`       TEXT         DEFAULT NULL,
  `actif`       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = désactivé (les réservations passées restent attribuées)',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_partenaires_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Organismes et institutions partenaires du Palais';

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `partenaire_id` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Compte associé à un partenaire (NULL = client classique)' AFTER `role`,
  ADD INDEX IF NOT EXISTS `idx_users_partenaire` (`partenaire_id`),
  ADD CONSTRAINT `fk_users_partenaire`
    FOREIGN KEY IF NOT EXISTS (`partenaire_id`) REFERENCES `partenaires` (`id`) ON DELETE SET NULL;

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `partenaire_id` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Partenaire au moment de la réservation (NULL = réservation classique)' AFTER `user_id`,
  ADD INDEX IF NOT EXISTS `idx_reservations_partenaire` (`partenaire_id`),
  ADD CONSTRAINT `fk_reservations_partenaire`
    FOREIGN KEY IF NOT EXISTS (`partenaire_id`) REFERENCES `partenaires` (`id`) ON DELETE SET NULL;


-- ---------------------------------------------------------------------
-- 2. SUGGESTIONS ANONYMES
--    Aucune donnée d'identification : ni nom, ni e-mail, ni téléphone,
--    ni compte, ni adresse IP, ni navigateur. Seuls le texte, la date
--    et le statut de suivi sont conservés (+ l'administrateur qui a
--    changé le statut, pour la traçabilité interne).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suggestions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contenu`            TEXT NOT NULL,
  `statut`             ENUM('nouvelle','lue','traitee') NOT NULL DEFAULT 'nouvelle',
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut_modifie_par` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Administrateur (jamais l auteur de la suggestion)',
  `statut_modifie_le`  DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suggestions_statut` (`statut`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Boîte à suggestions anonyme (aucune donnée personnelle)';


-- ---------------------------------------------------------------------
-- 3. DEMANDES DE SERVICES (dont lavage automobile)
--    Cycle : en_attente → en_cours → realisee
--            en_attente → refusee ; en_cours → annulee
--    « traitee » est conservé dans l'ENUM (compatibilité) et les
--    demandes déjà traitées passent en « realisee ».
-- ---------------------------------------------------------------------
ALTER TABLE `demandes_services`
  MODIFY `statut` ENUM('en_attente','en_cours','realisee','refusee','annulee','traitee') NOT NULL DEFAULT 'en_attente',
  ADD COLUMN IF NOT EXISTS `pris_en_charge_par` INT UNSIGNED NULL DEFAULT NULL AFTER `statut`,
  ADD COLUMN IF NOT EXISTS `date_prise_en_charge` DATETIME NULL DEFAULT NULL AFTER `pris_en_charge_par`;

UPDATE `demandes_services` SET `statut` = 'realisee' WHERE `statut` = 'traitee';
