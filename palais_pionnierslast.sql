-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 02 oct. 2026 à 01:26
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `palais_pionniers`
--

-- --------------------------------------------------------

--
-- Structure de la table `activites`
--

CREATE TABLE `activites` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icone` varchar(100) DEFAULT NULL,
  `image_principale` varchar(255) DEFAULT NULL,
  `sous_titre` varchar(300) DEFAULT NULL,
  `missions` text DEFAULT NULL COMMENT 'Contenu structuré (JSON) de la section missions',
  `chiffres_cles` text DEFAULT NULL COMMENT 'Contenu structuré (JSON) des statistiques affichées',
  `couleur` varchar(7) DEFAULT NULL COMMENT 'Couleur d''accent choisie pour cette activité (ex: #E61E2A)',
  `citation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fiches des activités proposées par le Palais';

--
-- Déchargement des données de la table `activites`
--

INSERT INTO `activites` (`id`, `nom`, `slug`, `description`, `icone`, `image_principale`, `sous_titre`, `missions`, `chiffres_cles`, `couleur`, `citation`, `created_at`) VALUES
(3, 'Ecole de la citoyenneté', 'ecole-de-la-citoyennet', 'Le programme « À l\'École de la Citoyenneté » est une initiative présidentielle et ministérielle majeure pilotée par le Ministère de la Jeunesse et des Sports, chargé de l\'Instruction civique et de la Construction citoyenne du Mali. Conçu dans le cadre de la refondation nationale, ce projet d\'envergure vise à mobiliser, éduquer et responsabiliser la jeunesse malienne afin d\'en faire le moteur du sursaut patriotique et du développement socio-économique du pays.\r\nAu cœur de ce programme se trouve la volonté politique de réaffirmer les valeurs cardinales de la société malienne, telles que le Danbé (la dignité et l\'honneur) et le Maaya (l\'humanisme et le sens de la communauté).\r\n À travers des sessions de formation intensives organisées au niveau national et dans les régions, le programme rassemble des jeunes venus de tous les horizons, y compris des déplacés internes, des membres de la diaspora et des délégations des pays frères de la Confédération de l\'Alliance des États du Sahel (AES). Durant leur séjour, les auditeurs participent à des modules rigoureux axés sur le civisme, le respect des symboles de la République, l\'histoire du Mali et la culture de la paix.\r\nL\'originalité de l\'École de la Citoyenneté repose sur son approche interactive et son ouverture sur la gouvernance de l\'État. \r\nLe programme intègre des panels ministériels interactifs, des espaces d\'échange direct où plusieurs membres du gouvernement viennent exposer les priorités de leurs départements respectifs et répondre sans tabou aux questions de la jeunesse sur l\'emploi, la sécurité, l\'éducation ou l\'économie. En parallèle à cette immersion institutionnelle, le programme met un point d\'honneur à l\'autonomisation des participants en leur offrant des formations pratiques dans des métiers porteurs, comme la saponification et l\'informatique. ', NULL, 'hero-ecole-de-la-citoyennet-6a7e7ed3279e1.jpeg', 'Forger le maliden koura', NULL, '1000 jeunes formés', NULL, NULL, '2026-08-14 02:34:27');

-- --------------------------------------------------------

--
-- Structure de la table `activite_galerie`
--

CREATE TABLE `activite_galerie` (
  `id` int(10) UNSIGNED NOT NULL,
  `activite_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `categorie` varchar(100) DEFAULT NULL,
  `legende` varchar(255) DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Photos complémentaires des activités';

--
-- Déchargement des données de la table `activite_galerie`
--

INSERT INTO `activite_galerie` (`id`, `activite_id`, `section_id`, `image_path`, `categorie`, `legende`, `ordre`) VALUES
(29, 3, NULL, 'gal-6a7e8690b1dde.jpeg', NULL, NULL, 0),
(30, 3, NULL, 'gal-6a7e8690b1dde.jpeg', NULL, NULL, 1),
(31, 3, NULL, 'gal-6a7e8690b2a77.jpeg', NULL, NULL, 2),
(32, 3, NULL, 'gal-6a7e8690b2a77.jpeg', NULL, NULL, 3),
(33, 3, NULL, 'gal-6a7e8690b32dc.jpeg', NULL, NULL, 4),
(34, 3, NULL, 'gal-6a7e8690b32dc.jpeg', NULL, NULL, 5),
(35, 3, NULL, 'gal-6a7e93f39f479.jpg', NULL, NULL, 6),
(36, 3, NULL, 'gal-6a7e93f3a0f78.jpg', NULL, NULL, 7),
(37, 3, NULL, 'gal-6a7e93f3a2a4b.jpeg', NULL, NULL, 8),
(38, 3, NULL, 'gal-6a7e93f3a41cd.jpeg', NULL, NULL, 9);

-- --------------------------------------------------------

--
-- Structure de la table `activite_liens`
--

CREATE TABLE `activite_liens` (
  `id` int(10) UNSIGNED NOT NULL,
  `activite_id` int(10) UNSIGNED NOT NULL,
  `titre` varchar(150) NOT NULL,
  `url` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Liens externes complémentaires pour une activité';

-- --------------------------------------------------------

--
-- Structure de la table `activite_sections`
--

CREATE TABLE `activite_sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `activite_id` int(10) UNSIGNED NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `titre` varchar(200) DEFAULT NULL,
  `texte` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Sections de contenu libres et facultatives pour une fiche activité';

--
-- Déchargement des données de la table `activite_sections`
--

INSERT INTO `activite_sections` (`id`, `activite_id`, `ordre`, `titre`, `texte`) VALUES
(3, 3, 0, 'Sortie - Rencontre', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `user_nom` varchar(150) NOT NULL,
  `action` varchar(100) NOT NULL,
  `module` enum('espaces','activites','reservations','messages','users') NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Journal d''audit des actions d''administration';

--
-- Déchargement des données de la table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `user_nom`, `action`, `module`, `details`, `created_at`) VALUES
(1, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #1 validée', '2026-07-27 16:00:17'),
(2, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000001 — 400 000 FCFA pour réservation #1 (especes)', '2026-07-27 16:19:47'),
(3, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Cour Événementielle', '2026-07-27 16:20:51'),
(4, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salles de Classe', '2026-07-27 16:21:11'),
(5, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #2 validée', '2026-07-27 16:49:28'),
(6, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000002 — 90 000 FCFA pour réservation #2 (especes)', '2026-07-27 16:51:49'),
(7, 7, 'Service Comptable', 'export_paiements', 'reservations', '2 ligne(s) exportée(s) (paiements)', '2026-07-27 16:51:58'),
(8, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #3 validée', '2026-07-27 23:36:00'),
(9, 3, 'Cabinet du Ministre', 'observation_ajoutee', 'messages', 'Observation sur paiement #2', '2026-07-28 21:16:36'),
(10, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #4 validée', '2026-07-28 23:00:15'),
(11, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000003 — 50 000 FCFA pour réservation #4 (orange_money)', '2026-07-28 23:06:56'),
(12, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000004 — 400 000 FCFA pour réservation #3 (especes)', '2026-07-28 23:23:18'),
(13, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #5 validée', '2026-07-29 08:00:10'),
(14, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000005 — 20 000 FCFA pour réservation #5 (especes)', '2026-07-29 08:01:18'),
(15, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Handball', '2026-07-29 12:42:16'),
(16, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Handball', '2026-07-29 12:44:55'),
(17, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Basketball', '2026-07-29 13:24:20'),
(18, 5, 'Administration Activités', 'personnalite_creee', 'activites', 'Nouvelle personnalité : Samaké Migan', '2026-07-29 13:29:42'),
(19, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Handball', '2026-07-29 13:38:11'),
(20, 5, 'Administration Activités', 'formation_creee', 'activites', 'Nouvelle formation : Savonnerie', '2026-07-30 00:32:04'),
(21, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #6 validée', '2026-07-30 02:25:52'),
(22, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement REC-2026-000006 — 400 000 FCFA pour réservation #6 (cheque)', '2026-07-30 02:26:32'),
(23, 2, 'Direction Générale', 'direction_modifiee', 'activites', 'Fiche Directeur Général Adjoint modifiée', '2026-07-30 13:21:25'),
(24, 2, 'Direction Générale', 'direction_modifiee', 'activites', 'Fiche Directeur Général Adjoint modifiée', '2026-07-30 13:22:31'),
(25, 2, 'Direction Générale', 'direction_modifiee', 'activites', 'Fiche Directeur Général Adjoint modifiée', '2026-07-30 13:22:39'),
(26, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #7 validée', '2026-07-30 14:14:10'),
(27, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #8 validée', '2026-07-30 14:14:11'),
(28, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-007/DGPP-C — 400 000 FCFA pour réservation #8 (especes)', '2026-07-30 14:16:41'),
(29, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-008/DGPP-C — 12 240 000 FCFA pour réservation #7 (especes)', '2026-07-30 14:17:21'),
(30, 7, 'Service Comptable', 'export_paiements', 'reservations', '8 ligne(s) exportée(s) (paiements)', '2026-07-30 14:17:45'),
(31, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #9 validée', '2026-07-30 22:18:13'),
(32, 7, 'Service Comptable', 'observation_repondue', 'messages', 'Réponse à l\'observation #1', '2026-07-30 22:35:09'),
(33, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #10 validée', '2026-07-31 11:37:43'),
(34, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-009/DGPP-C — 150 000 FCFA pour réservation #9 (especes)', '2026-07-31 11:47:07'),
(35, 7, 'Service Comptable', 'export_paiements', 'reservations', '9 ligne(s) exportée(s) (paiements)', '2026-07-31 11:50:47'),
(36, 4, 'Administration Espaces', 'message_repondu', 'messages', 'Réponse envoyée au message #2 (site)', '2026-07-31 21:08:59'),
(37, 7, 'Service Comptable', 'export_paiements', 'reservations', '4 ligne(s) exportée(s) (paiements)', '2026-07-31 21:41:13'),
(38, 7, 'Service Comptable', 'resa_guichet', 'reservations', 'Réservation guichet #12 créée pour user #12', '2026-08-01 00:27:03'),
(39, 7, 'Service Comptable', 'reduction_accordee', 'reservations', 'Réduction accordée sur le paiement 26-010/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', '2026-08-01 00:27:57'),
(40, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-010/DGPP-C — 200 000 FCFA pour réservation #12 (especes)', '2026-08-01 00:27:57'),
(41, 7, 'Service Comptable', 'reduction_accordee', 'reservations', 'Réduction accordée sur le paiement 26-011/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', '2026-08-01 00:28:08'),
(42, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-011/DGPP-C — 200 000 FCFA pour réservation #12 (especes)', '2026-08-01 00:28:08'),
(43, 3, 'Cabinet du Ministre', 'reservation_expiree', 'reservations', 'Réservation #10 annulée automatiquement (délai 48h dépassé)', '2026-08-03 11:13:33'),
(44, 5, 'Administration Activités', 'personnel_ajoute', 'activites', 'Nouveau membre du personnel : Dicko', '2026-08-03 11:22:37'),
(45, 5, 'Administration Activités', 'personnel_ajoute', 'activites', 'Nouveau membre du personnel : Haïdara', '2026-08-03 11:23:35'),
(46, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Samaké Migan', '2026-08-03 11:25:23'),
(47, 5, 'Administration Activités', 'personnalite_creee', 'activites', 'Nouvelle personnalité : Diarra', '2026-08-03 11:28:15'),
(48, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Samaké Migan', '2026-08-03 11:41:08'),
(49, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-08-03 11:41:47'),
(50, 5, 'Administration Activités', 'personnel_modifie', 'activites', 'Membre du personnel modifié : Dicko', '2026-08-03 14:45:08'),
(51, 5, 'Administration Activités', 'personnel_modifie', 'activites', 'Membre du personnel modifié : Haïdara', '2026-08-03 14:45:21'),
(52, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Samaké Migan', '2026-08-03 14:45:40'),
(53, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-08-03 14:45:54'),
(54, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Informatique Oumou Diarra Dite Dièma', '2026-08-03 15:03:49'),
(55, 7, 'Service Comptable', 'demande_bail_traitee', 'espaces', 'Demande de bail #1 acceptée', '2026-08-09 23:24:35'),
(56, 5, 'Administration Activités', 'demande_service', 'espaces', 'Demande de service « Espace lavage auto et moto » par Administration Activités', '2026-08-10 13:07:36'),
(57, 8, 'Tapa COULIBALY', 'demande_service', 'espaces', 'Demande de service « Espace lavage auto et moto » par Tapa COULIBALY', '2026-08-10 13:07:59'),
(58, 8, 'Tapa COULIBALY', 'demande_service', 'espaces', 'Demande de service « Espace lavage auto et moto » par Tapa COULIBALY', '2026-08-10 13:09:45'),
(59, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #9 réquisitionnée — motif : Activité de l\'UNICEF', '2026-08-10 13:14:22'),
(60, 4, 'Administration Espaces', 'demande_service_traitee', 'espaces', 'Demande de service #3 marquée traitée', '2026-08-10 13:49:09'),
(61, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #11 validée', '2026-08-10 14:11:53'),
(62, 1, 'Administration Système', 'direction_modifiee', 'activites', 'Fiche Directeur Général Adjoint modifiée', '2026-08-10 14:32:17'),
(63, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « autre_espace » pour la réquisition #1', '2026-08-10 14:52:54'),
(64, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : L\'École de la Citoyenneté', '2026-08-10 14:58:42'),
(65, 1, 'Administration Système', 'personnel_supprime', 'activites', 'Membre du personnel #1 supprimé', '2026-08-10 15:53:45'),
(66, 1, 'Administration Système', 'personnel_modifie', 'activites', 'Membre du personnel modifié : Haïdara', '2026-08-10 15:54:02'),
(67, 1, 'Administration Système', 'personnel_modifie', 'activites', 'Membre du personnel modifié : Haïdara', '2026-08-10 15:54:23'),
(68, 1, 'Administration Système', 'user_bloque', 'users', 'Compte désactivé (ex-suppression) : Seydou COULIBALY (user)', '2026-08-10 15:55:05'),
(69, 1, 'Administration Système', 'user_debloque', 'users', 'Compte «Seydou COULIBALY» débloqué', '2026-08-10 15:55:22'),
(70, 1, 'Administration Système', 'user_bloque', 'users', 'Compte «Tapa COULIBALY» bloqué', '2026-08-10 15:55:46'),
(71, 1, 'Administration Système', 'user_debloque', 'users', 'Compte «Tapa COULIBALY» débloqué', '2026-08-10 15:56:56'),
(72, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #11 réquisitionnée — motif : SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS', '2026-08-10 16:06:18'),
(73, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #2', '2026-08-10 16:06:58'),
(74, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #13 validée', '2026-08-10 16:12:08'),
(75, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #13 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', '2026-08-10 23:37:31'),
(76, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #3', '2026-08-10 23:37:58'),
(77, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #14 validée', '2026-08-11 00:15:50'),
(79, 5, 'Administration Activités', 'formation_creee', 'activites', 'Nouvelle formation : Coupe - Couture', '2026-08-12 18:10:18'),
(80, 5, 'Administration Activités', 'formation_creee', 'activites', 'Nouvelle formation : Informatique', '2026-08-12 18:10:30'),
(81, 7, 'Service Comptable', 'requisition_traitee', 'reservations', 'Réquisition #3 marquée traitée', '2026-08-12 21:24:29'),
(82, 5, 'Administration Activités', 'reservation_expiree', 'reservations', 'Réservation #14 annulée automatiquement (délai 48h dépassé)', '2026-08-13 12:02:22'),
(83, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : L\'École de la Citoyenneté', '2026-08-13 12:03:50'),
(84, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : L\'École de la Citoyenneté', '2026-08-13 12:25:52'),
(85, 5, 'Administration Activités', 'activite_supprimee', 'activites', 'Activité ID 1 supprimée', '2026-08-14 02:34:21'),
(86, 5, 'Administration Activités', 'activite_creee', 'activites', 'Nouvelle activité créée : ec', '2026-08-14 02:34:27'),
(87, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-08-14 02:34:59'),
(88, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-08-14 03:08:00'),
(89, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-08-14 03:10:41'),
(91, 5, 'Administration Activités', 'activite_creee', 'activites', 'Nouvelle activité créée : Campagne de reboisement du ministère de l\'environnement (id 5)', '2026-08-14 03:24:35'),
(92, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Campagne de reboisement du ministère de l\'environnement', '2026-08-14 03:34:53'),
(93, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-08-14 04:05:07'),
(94, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #15 validée', '2026-08-31 01:43:42'),
(95, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-012/DGPP-C — 200 000 FCFA pour réservation #15 (especes)', '2026-08-31 01:46:47'),
(96, 5, 'Administration Activités', 'reservation_expiree', 'reservations', 'Réservation #15 annulée automatiquement (délai 48h dépassé)', '2026-09-22 20:52:18'),
(97, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-09-22 20:55:09'),
(98, 5, 'Administration Activités', 'activite_modifiee', 'activites', 'Activité modifiée : Ecole de la citoyenneté', '2026-09-22 20:56:17'),
(99, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #16 validée', '2026-09-26 22:06:56'),
(100, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #16 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-26 22:07:52'),
(101, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #4 — opération #1', '2026-09-26 22:08:26'),
(102, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #17 validée', '2026-09-28 00:35:59'),
(103, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #17 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-28 00:36:08'),
(104, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #5 — opération #2', '2026-09-28 00:36:53'),
(105, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #18 validée', '2026-09-28 14:49:01'),
(106, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #18 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', '2026-09-28 14:49:09'),
(107, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « autre_espace » pour la réquisition #6 — opération #3', '2026-09-28 14:50:50'),
(108, 7, 'Service Comptable', 'requisition_traitee', 'reservations', 'Réquisition #6 traitée par l’utilisateur #7', '2026-09-28 15:51:52'),
(109, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #19 validée', '2026-09-28 16:07:13'),
(110, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #19 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-28 16:07:22'),
(111, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #7 — opération #4', '2026-09-28 16:08:44'),
(112, 7, 'Service Comptable', 'requisition_traitee', 'reservations', 'Réquisition #7 traitée par l’utilisateur #7', '2026-09-28 16:09:23'),
(113, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #20 validée', '2026-09-28 18:17:25'),
(114, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-013/DGPP-C — 150 000 FCFA pour réservation #20 (especes)', '2026-09-28 18:18:11'),
(115, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #20 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-28 18:18:23'),
(116, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #8 — opération #5', '2026-09-28 18:19:40'),
(117, 7, 'Service Comptable', 'requisition_prise_en_charge', 'reservations', 'Réquisition #8 prise en charge par l’utilisateur #7', '2026-09-28 20:24:45'),
(118, 7, 'Service Comptable', 'requisition_remboursement', 'reservations', 'Remboursement de la réquisition #8 effectué pour 150 000 FCFA par l’utilisateur #7', '2026-09-28 20:25:30'),
(119, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #5', '2026-09-28 20:43:03'),
(120, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #21 validée', '2026-09-29 07:44:04'),
(121, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-014/DGPP-C — 50 000 FCFA pour réservation #21 (especes, complet) — payé 50 000 / 50 000 FCFA', '2026-09-29 07:46:59'),
(122, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #21 réquisitionnée — motif : Cet espace a été réquisitionné en raison d\'une urgence nationale.', '2026-09-29 07:48:21'),
(123, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #9 — opération #6', '2026-09-29 07:58:30'),
(124, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Seydou BADIAN', '2026-09-29 08:38:59'),
(125, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #9', '2026-09-29 08:42:18'),
(126, 7, 'Service Comptable', 'demande_service_traitee', 'espaces', 'Demande de service #2 marquée traitée', '2026-09-29 08:44:24'),
(127, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #12 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', '2026-09-29 13:21:42'),
(128, 8, 'Tapa COULIBALY', 'requisition_nouvelle_reservation', 'reservations', 'Client #8 — réquisition #9 : nouvelle réservation #22 créée (en attente)', '2026-09-29 15:41:51'),
(129, 7, 'Service Comptable', 'requisition_paiement_transfere', 'reservations', 'Paiements transférés vers la réservation #22 : 50 000 FCFA — montant net dû : 50 000 FCFA. (réquisition #9, réservation d\'origine #21)', '2026-09-29 15:42:10'),
(130, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #22 validée (réquisition #9)', '2026-09-29 15:42:10'),
(131, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #22 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-29 15:42:17'),
(132, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #11 — opération #7', '2026-09-29 15:46:24'),
(133, 8, 'Tapa COULIBALY', 'requisition_nouvelle_reservation', 'reservations', 'Client #8 — réquisition #11 : nouvelle réservation #23 créée (en attente)', '2026-09-29 15:46:52'),
(134, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #24 validée', '2026-09-29 15:47:44'),
(135, 7, 'Service Comptable', 'requisition_paiement_transfere', 'reservations', 'Paiements transférés vers la réservation #23 : 50 000 FCFA — montant net dû : 50 000 FCFA. (réquisition #11, réservation d\'origine #22)', '2026-09-29 15:47:47'),
(136, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #23 validée (réquisition #11)', '2026-09-29 15:47:47'),
(137, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #11', '2026-09-29 15:47:59'),
(138, 7, 'Service Comptable', 'requisition_traitee', '', 'Réquisition #11 traitée', '2026-09-29 15:48:21'),
(139, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-015/DGPP-C — 150 000 FCFA pour réservation #24 (especes, complet) — payé 150 000 / 150 000 FCFA', '2026-09-29 15:50:01'),
(140, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #24 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-29 15:50:15'),
(141, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #12 — opération #8', '2026-09-29 15:50:33'),
(142, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #12', '2026-09-29 15:51:09'),
(143, 7, 'Service Comptable', 'remboursement_requisition', '', 'Remboursement effectué pour la réquisition #12', '2026-09-29 15:51:37'),
(144, 7, 'Service Comptable', 'export_paiements', 'reservations', '2 ligne(s) exportée(s) (paiements)', '2026-09-29 15:56:41'),
(145, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #4', '2026-09-29 16:43:05'),
(146, 8, 'Tapa COULIBALY', 'requisition_nouvelle_reservation', 'reservations', 'Client #8 — réquisition #5 : nouvelle réservation #25 créée (en attente)', '2026-09-29 17:38:27'),
(147, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #25 validée (réquisition #5)', '2026-09-29 19:28:13'),
(148, 1, 'Administration Système', 'partenaire_cree', 'users', 'Partenaire #1 créé : CICB', '2026-09-29 20:21:33'),
(149, 1, 'Administration Système', 'partenaire_modifie', 'users', 'Partenaire #1 modifié : CICB', '2026-09-29 20:22:38'),
(150, 1, 'Administration Système', 'partenaire_compte_cree', 'users', 'Compte partenaire #13 (cicb@gmail.com) créé pour le partenaire #1', '2026-09-29 23:07:33'),
(151, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #26 validée', '2026-09-30 01:04:52'),
(152, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-016/DGPP-C — 75 000 FCFA pour réservation #25 (especes, acompte_50) — payé 75 000 / 150 000 FCFA', '2026-09-30 01:05:16'),
(153, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-017/DGPP-C — 400 000 FCFA pour réservation #26 (especes, complet) — payé 400 000 / 400 000 FCFA', '2026-09-30 01:05:50'),
(154, 13, 'CICB', 'profil_modifie', 'users', 'Client #13 a mis à jour son profil', '2026-09-30 01:25:34'),
(155, 1, 'Administration Système', 'personnel_modifie', 'activites', 'Membre du personnel modifié : Haïdara', '2026-09-30 11:27:13'),
(156, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KEÏTA', '2026-09-30 11:49:35'),
(157, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KEÏTA', '2026-09-30 11:50:28'),
(158, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KEÏTA', '2026-09-30 11:50:49'),
(159, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-09-30 11:52:50'),
(160, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-09-30 11:53:37'),
(161, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-09-30 11:53:53'),
(162, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Samassékou', '2026-09-30 11:54:12'),
(163, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KOUYATÉ', '2026-09-30 11:54:24'),
(164, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : COULIBALY', '2026-09-30 11:55:27'),
(165, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Samaké Migan', '2026-09-30 11:55:40'),
(166, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KOÏTA', '2026-09-30 11:55:53'),
(167, 4, 'Administration Espaces', 'reservation_validee', 'reservations', 'Réservation #27 validée', '2026-09-30 12:07:30'),
(168, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-018/DGPP-C — 400 000 FCFA pour réservation #27 (especes, complet) — payé 400 000 / 400 000 FCFA', '2026-09-30 12:11:51'),
(169, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #27 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-30 12:15:34'),
(170, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « nouvelle_date » pour la réquisition #13 — opération #9', '2026-09-30 12:16:58'),
(171, 8, 'Tapa COULIBALY', 'requisition_nouvelle_reservation', 'reservations', 'Client #8 — réquisition #13 : nouvelle réservation #28 créée (en attente)', '2026-09-30 12:17:48'),
(172, 7, 'Service Comptable', 'requisition_traitement_commence', 'reservations', 'Traitement commencé pour la réquisition #13', '2026-09-30 12:18:13'),
(173, 7, 'Service Comptable', 'requisition_paiement_transfere', 'reservations', 'Paiements transférés vers la réservation #28 : 400 000 FCFA — montant net dû : 400 000 FCFA. (réquisition #13, réservation d\'origine #27)', '2026-09-30 12:18:38'),
(174, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #28 validée (réquisition #13)', '2026-09-30 12:18:38'),
(175, 4, 'Administration Espaces', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 1', '2026-09-30 12:34:13'),
(176, 4, 'Administration Espaces', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 1', '2026-09-30 12:34:17'),
(177, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Seydou BADIAN', '2026-09-30 12:35:11'),
(178, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '6 photo(s) ajoutée(s) à l\'espace ID 1', '2026-09-30 12:35:11'),
(179, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Coupe - Couture', '2026-09-30 12:41:25'),
(180, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Savonnerie', '2026-09-30 12:41:44'),
(181, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Karaté Daba Modibo KEITA', '2026-09-30 13:06:36'),
(182, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 19', '2026-09-30 13:06:36'),
(183, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Basketball', '2026-09-30 13:07:46'),
(184, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '3 photo(s) ajoutée(s) à l\'espace ID 6', '2026-09-30 13:07:46'),
(185, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Maracana', '2026-09-30 13:08:29'),
(186, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 3', '2026-09-30 13:08:29'),
(187, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Basketball', '2026-09-30 13:09:37'),
(188, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '1 photo(s) ajoutée(s) à l\'espace ID 6', '2026-09-30 13:09:38'),
(189, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Adama SAMASSEKOU', '2026-09-30 13:10:50'),
(190, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '4 photo(s) ajoutée(s) à l\'espace ID 14', '2026-09-30 13:10:50'),
(191, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Taekwondo Daba Modibo KEITA', '2026-09-30 13:12:15'),
(192, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '4 photo(s) ajoutée(s) à l\'espace ID 18', '2026-09-30 13:12:16'),
(193, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Gym Daba Modibo KEITA', '2026-09-30 13:13:04'),
(194, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 7', '2026-09-30 13:13:04'),
(195, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Informatique Oumou Diarra Dite Dièma', '2026-09-30 13:14:37'),
(196, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '5 photo(s) ajoutée(s) à l\'espace ID 17', '2026-09-30 13:14:37'),
(197, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #29 validée', '2026-09-30 13:26:05'),
(198, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-019/DGPP-C — 400 000 FCFA pour réservation #29 (especes, complet) — payé 400 000 / 400 000 FCFA', '2026-09-30 13:32:23'),
(199, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #29 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-30 13:32:59'),
(200, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #14 — opération #10', '2026-09-30 13:33:27'),
(201, 7, 'Service Comptable', 'requisition_traitement_commence', 'reservations', 'Traitement commencé pour la réquisition #14', '2026-09-30 13:33:54'),
(202, 7, 'Service Comptable', 'remboursement_requisition', 'reservations', 'Remboursement effectué pour la réquisition #14', '2026-09-30 13:34:23'),
(203, 7, 'Service Comptable', 'export_remboursements', 'reservations', '3 ligne(s) exportée(s) (remboursements)', '2026-09-30 13:34:40'),
(204, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #30 validée', '2026-09-30 13:53:02'),
(205, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-020/DGPP-C — 150 000 FCFA pour réservation #30 (especes, complet) — payé 150 000 / 150 000 FCFA', '2026-09-30 13:54:44'),
(206, 7, 'Service Comptable', 'reservation_requisitionnee', 'reservations', 'Réservation #30 réquisitionnée — motif : Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', '2026-09-30 13:56:05'),
(207, 8, 'Tapa COULIBALY', 'requisition_choix', 'reservations', 'Client #8 a choisi « remboursement » pour la réquisition #15 — opération #11', '2026-09-30 13:56:39'),
(208, 7, 'Service Comptable', 'requisition_traitement_commence', 'reservations', 'Traitement commencé pour la réquisition #15', '2026-09-30 13:57:10'),
(209, 7, 'Service Comptable', 'remboursement_requisition', 'reservations', 'Remboursement effectué pour la réquisition #15', '2026-09-30 13:57:39'),
(210, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Adama SAMASSEKOU', '2026-09-30 14:03:38'),
(211, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle de conférence Pr Assétou Founé SAMAKE MIGAN', '2026-09-30 14:39:29'),
(212, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '1 photo(s) ajoutée(s) à l\'espace ID 15', '2026-09-30 14:39:29'),
(213, 1, 'Administration Système', 'bail_termine', 'espaces', 'Bail terminé pour : Terrain de Handball', '2026-09-30 14:39:49'),
(214, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Handball', '2026-09-30 14:40:30'),
(215, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '1 photo(s) ajoutée(s) à l\'espace ID 16', '2026-09-30 14:40:30'),
(216, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Cour Événementielle', '2026-09-30 14:43:07'),
(217, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '4 photo(s) ajoutée(s) à l\'espace ID 13', '2026-09-30 14:43:07'),
(218, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salles de Classe', '2026-09-30 14:45:27'),
(219, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '7 photo(s) ajoutée(s) à l\'espace ID 12', '2026-09-30 14:45:27'),
(220, 1, 'Administration Système', 'espace_supprime', 'espaces', 'Espace ID 9 supprimé', '2026-09-30 14:45:58'),
(221, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Résidence Ely Abdoulaye DIALLO', '2026-09-30 14:48:59'),
(222, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '7 photo(s) ajoutée(s) à l\'espace ID 8', '2026-09-30 14:48:59'),
(223, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 16', '2026-09-30 14:53:44'),
(224, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Terrain de Handball', '2026-09-30 14:54:31'),
(225, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '3 photo(s) ajoutée(s) à l\'espace ID 16', '2026-09-30 14:54:31'),
(226, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle Sory Ibrahim KOITA dit Bomba', '2026-09-30 14:57:39'),
(227, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '5 photo(s) ajoutée(s) à l\'espace ID 2', '2026-09-30 14:57:39'),
(228, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Piscine', '2026-09-30 15:00:19'),
(229, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 5', '2026-09-30 15:00:19'),
(230, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Centre d\'Accueil Tidiani COULIBALY dit Necker', '2026-09-30 15:03:53'),
(231, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '10 photo(s) ajoutée(s) à l\'espace ID 4', '2026-09-30 15:03:53'),
(232, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 2', '2026-09-30 15:04:32'),
(233, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 2', '2026-09-30 15:04:37'),
(234, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 2', '2026-09-30 15:04:42'),
(235, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Piscine', '2026-09-30 15:14:04'),
(236, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 5', '2026-09-30 15:14:04'),
(237, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 19', '2026-09-30 15:18:57'),
(238, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 19', '2026-09-30 15:19:00'),
(239, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Karaté Daba Modibo KEITA', '2026-09-30 15:19:29'),
(240, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 19', '2026-09-30 15:19:30'),
(241, 1, 'Administration Système', 'image_supprimee', 'espaces', 'Image supprimée de l\'espace ID 18', '2026-09-30 15:20:44'),
(242, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Taekwondo Daba Modibo KEITA', '2026-09-30 15:20:49'),
(243, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Gym Daba Modibo KEITA', '2026-09-30 15:21:03'),
(244, 1, 'Administration Système', 'espace_modifie', 'espaces', 'Espace modifié : Salle de Gym Daba Modibo KEITA', '2026-09-30 15:21:32'),
(245, 1, 'Administration Système', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 7', '2026-09-30 15:21:32'),
(246, 5, 'Administration Activités', 'activite_supprimee', 'activites', 'Activité ID 5 supprimée', '2026-09-30 15:22:54'),
(247, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Savonnerie', '2026-09-30 15:25:59'),
(248, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Coupe - Couture', '2026-09-30 15:26:26'),
(249, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Informatique', '2026-09-30 15:28:25'),
(250, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:30:07'),
(251, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '2 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:30:32'),
(252, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:32:03'),
(253, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:42:44'),
(254, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '3 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:42:58'),
(255, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:43:15'),
(256, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '2 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:43:44'),
(257, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:43:53'),
(258, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '1 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:44:23'),
(259, 5, 'Administration Activités', 'formation_modifiee', 'activites', 'Formation modifiée : Informatique', '2026-09-30 15:46:43'),
(260, 5, 'Administration Activités', 'formation_galerie_ajoutee', 'activites', '3 photo(s) ajoutée(s) à la galerie d\'une formation', '2026-09-30 15:47:44'),
(261, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Salle Sory Ibrahim KOITA dit Bomba', '2026-09-30 15:52:11'),
(262, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 2', '2026-09-30 15:52:11'),
(263, 4, 'Administration Espaces', 'espace_modifie', 'espaces', 'Espace modifié : Résidence Ely Abdoulaye DIALLO', '2026-09-30 16:12:59'),
(264, 4, 'Administration Espaces', 'photos_ajoutees', 'espaces', '2 photo(s) ajoutée(s) à l\'espace ID 8', '2026-09-30 16:12:59'),
(265, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-09-30 16:15:29'),
(266, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : Diarra', '2026-09-30 16:15:44'),
(267, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KOÏTA', '2026-09-30 16:16:00'),
(268, 5, 'Administration Activités', 'personnalite_modifiee', 'activites', 'Personnalité modifiée : KOUYATÉ', '2026-09-30 16:16:13'),
(269, 7, 'Service Comptable', 'reservation_validee', 'reservations', 'Réservation #31 validée', '2026-09-30 20:08:00'),
(270, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-021/DGPP-C — 75 000 FCFA pour réservation #25 (especes, complet) — payé 150 000 / 150 000 FCFA', '2026-09-30 20:09:07'),
(271, 7, 'Service Comptable', 'paiement_enregistre', 'reservations', 'Paiement 26-022/DGPP-C — 400 000 FCFA pour réservation #31 (especes, complet) — payé 400 000 / 400 000 FCFA', '2026-09-30 20:09:47');

-- --------------------------------------------------------

--
-- Structure de la table `bail_paiements`
--

CREATE TABLE `bail_paiements` (
  `id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `periode_debut` date NOT NULL COMMENT 'Premier jour de la période couverte par ce paiement',
  `duree_mois` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Durée couverte en mois (1=mensuel, 3=trimestriel, 6=semestriel, 12=annuel)',
  `montant` decimal(12,2) NOT NULL COMMENT 'Loyer réellement encaissé pour cette période, en F CFA',
  `montant_reference` decimal(12,2) DEFAULT NULL COMMENT 'Loyer attendu selon le tarif, avant réduction',
  `motif_reduction` text DEFAULT NULL COMMENT 'Raison de la réduction accordée, si montant < montant_reference',
  `mode` enum('especes','orange_money','moov_money','virement','cheque') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `enregistre_par` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Suivi des loyers des espaces en bail, selon la périodicité de chaque contrat';

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Catégories de classement des espaces';

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `slug`) VALUES
(1, 'Amphithéâtre', 'amphitheatre'),
(2, 'Salle de conférence', 'conference'),
(3, 'Sport', 'sport'),
(4, 'Hébergement', 'hebergement'),
(5, 'Loisir', 'loisir'),
(6, 'Restauration', 'restauration');

-- --------------------------------------------------------

--
-- Structure de la table `demandes_bail`
--

CREATE TABLE `demandes_bail` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `telephone` varchar(30) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `usage_prevu` text DEFAULT NULL,
  `duree_souhaitee` enum('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'mensuel',
  `date_debut_souhaitee` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `statut` enum('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `canal` enum('en_ligne','guichet') NOT NULL DEFAULT 'en_ligne',
  `enregistre_par` int(10) UNSIGNED DEFAULT NULL COMMENT 'Admin qui a saisi la demande, si prise au guichet',
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `client_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Compte client créé/lié automatiquement à l''acceptation',
  `date_traitement` timestamp NULL DEFAULT NULL,
  `note_traitement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Demandes de prise en bail d''un espace, en ligne ou au guichet';

--
-- Déchargement des données de la table `demandes_bail`
--

INSERT INTO `demandes_bail` (`id`, `nom`, `prenom`, `telephone`, `email`, `espace_id`, `usage_prevu`, `duree_souhaitee`, `date_debut_souhaitee`, `message`, `statut`, `canal`, `enregistre_par`, `traite_par`, `client_user_id`, `date_traitement`, `note_traitement`, `created_at`) VALUES
(1, 'Coulibaly', 'Tapa', '+22390921120', 'ladytapa@gmail.com', 3, 'nbbbbbbbbbbbbbbbfz', 'mensuel', '2026-08-17', NULL, 'acceptee', 'en_ligne', NULL, 7, 8, '2026-08-09 23:24:35', NULL, '2026-08-09 22:34:57'),
(2, 'COULIBALY', 'Tapa', '+22390921120', 'ladytapa@gmail.com', 3, 'Organiser des seance d\'entrainement', 'mensuel', NULL, NULL, 'en_attente', 'en_ligne', NULL, NULL, NULL, NULL, NULL, '2026-09-25 00:11:02');

-- --------------------------------------------------------

--
-- Structure de la table `demandes_services`
--

CREATE TABLE `demandes_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `statut` enum('en_attente','en_cours','realisee','refusee','annulee','traitee') NOT NULL DEFAULT 'en_attente',
  `pris_en_charge_par` int(10) UNSIGNED DEFAULT NULL,
  `date_prise_en_charge` datetime DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `note_traitement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Demandes tracées de services annexes, réservées aux comptes connectés';

--
-- Déchargement des données de la table `demandes_services`
--

INSERT INTO `demandes_services` (`id`, `user_id`, `service_id`, `message`, `statut`, `pris_en_charge_par`, `date_prise_en_charge`, `traite_par`, `date_traitement`, `note_traitement`, `created_at`) VALUES
(1, 5, 2, NULL, 'en_attente', NULL, NULL, NULL, NULL, NULL, '2026-08-10 13:07:36'),
(2, 8, 2, NULL, 'realisee', NULL, NULL, 7, '2026-09-29 08:44:24', NULL, '2026-08-10 13:07:59'),
(3, 8, 2, NULL, 'realisee', NULL, NULL, 4, '2026-08-10 13:49:09', NULL, '2026-08-10 13:09:45');

-- --------------------------------------------------------

--
-- Structure de la table `demande_bail_espaces`
--

CREATE TABLE `demande_bail_espaces` (
  `id` int(10) UNSIGNED NOT NULL,
  `demande_id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Espaces supplémentaires inclus dans une demande de bail groupée';

-- --------------------------------------------------------

--
-- Structure de la table `direction`
--

CREATE TABLE `direction` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_key` enum('ministre','dg','dga') NOT NULL,
  `nom` varchar(200) NOT NULL,
  `titre` varchar(255) DEFAULT NULL,
  `citation` varchar(500) DEFAULT NULL COMMENT 'Phrase de mise en avant',
  `texte` text DEFAULT NULL COMMENT 'Paragraphe complet',
  `photo` varchar(255) DEFAULT NULL COMMENT 'Si vide, un avatar avec initiales est affiché',
  `ordre` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Ministre, DG, DGA — contenu affiché sur accueil et à propos';

--
-- Déchargement des données de la table `direction`
--

INSERT INTO `direction` (`id`, `role_key`, `nom`, `titre`, `citation`, `texte`, `photo`, `ordre`) VALUES
(1, 'ministre', 'M. Abdoul Kassim Ibrahim Fomba', 'Ministre de la Jeunesse et des Sports, chargé de l\'Instruction Civique et de la Construction Citoyenne', 'Le Palais est le levier stratégique de notre politique de construction citoyenne.', 'Sous l\'impulsion du Gouvernement, nous avons confié au Palais des Pionniers la mission noble de transformer notre jeunesse en un rempart inébranlable contre l\'incivisme. C\'est ici que s\'enseigne l\'amour sacré de la patrie.', 'minis.jpeg', 1),
(2, 'dg', 'Mr Sidi Dicko', 'Directeur Général du Palais des Pionniers', 'Nous transformons la vision nationale en actes concrets pour la patrie.', 'En notre qualité d\'EPST, nous œuvrons quotidiennement à produire de la compétence citoyenne. Le Palais est une véritable usine à bâtisseurs de nation, garantissant que chaque parcours soit une pierre solide à l\'édifice du Mali souverain.', 'dg.jpeg', 2),
(3, 'dga', 'Nouhoum Chérif HAÏDARA', 'Directeur Général Adjoint du Palais des Pionniers', 'Une jeunesse formée, c\'est une nation qui avance.', 'Jeune, rigoureusement formé et pleinement dévoué à la mission du Palais, le Directeur Général Adjoint incarne cette nouvelle génération de cadres maliens qui allient exigence académique et engagement de terrain, au service quotidien de la jeunesse pionnière.', 'direction-dga-6a79e0f1a31b3.jpg', 3);

-- --------------------------------------------------------

--
-- Structure de la table `espaces`
--

CREATE TABLE `espaces` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `categorie_id` int(10) UNSIGNED NOT NULL,
  `capacite` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `equipements` text DEFAULT NULL,
  `mode_reservation` enum('creneau','sejour') NOT NULL DEFAULT 'creneau' COMMENT 'creneau = date + heure début/fin (salles, terrains) ; sejour = arrivée/départ en nuitées (hébergement)',
  `option_petit_dejeuner` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Propose un supplément petit-déjeuner au moment de la réservation',
  `option_vip` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Propose un supplément VIP (espace/accueil réservé aux personnalités) au moment de la réservation',
  `prix_vip` decimal(12,2) DEFAULT NULL COMMENT 'Montant du supplément VIP, si option_vip actif',
  `gerant_externe` varchar(255) DEFAULT NULL COMMENT 'Si renseigné, cet espace est loué/géré par un tiers — la réservation en ligne est désactivée et ce texte est affiché à la place',
  `gerant_nom` varchar(100) DEFAULT NULL COMMENT 'Nom du gestionnaire externe',
  `gerant_prenom` varchar(100) DEFAULT NULL COMMENT 'Prénom du gestionnaire externe',
  `gerant_email` varchar(150) DEFAULT NULL COMMENT 'Email du gestionnaire externe',
  `type_bail` enum('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'mensuel' COMMENT 'Périodicité du contrat de bail — détermine la fréquence attendue des paiements',
  `gerant_contact` varchar(100) DEFAULT NULL COMMENT 'Téléphone/contact du gestionnaire externe de cet espace',
  `gerant_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Compte client lié au bail, pour l''onglet "Mes Baux"',
  `resiliation_demandee` tinyint(1) NOT NULL DEFAULT 0,
  `resiliation_demandee_le` timestamp NULL DEFAULT NULL,
  `resiliation_note` text DEFAULT NULL COMMENT 'Motif donné par le client à la demande de résiliation',
  `groupe_batiment` varchar(150) DEFAULT NULL COMMENT 'Nom du bâtiment si cet espace fait partie d''un ensemble (ex: "Daba Modibo Keita")',
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Espaces réservables (salles, terrains, hébergement, etc.)';

--
-- Déchargement des données de la table `espaces`
--

INSERT INTO `espaces` (`id`, `nom`, `slug`, `categorie_id`, `capacite`, `description`, `equipements`, `mode_reservation`, `option_petit_dejeuner`, `option_vip`, `prix_vip`, `gerant_externe`, `gerant_nom`, `gerant_prenom`, `gerant_email`, `type_bail`, `gerant_contact`, `gerant_user_id`, `resiliation_demandee`, `resiliation_demandee_le`, `resiliation_note`, `groupe_batiment`, `disponible`, `created_at`) VALUES
(1, 'Salle Seydou BADIAN', 'salle-seydou-badian', 1, '400 places', 'Grand amphithéâtre du Palais, taillé pour les cérémonies officielles, conférences de grande envergure et rassemblements institutionnels. Dispose d\'un espace VVIP séparé pour les personnalités et délégations.', '', 'creneau', 0, 1, 150000.00, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(2, 'Salle Sory Ibrahim KOITA dit Bomba', 'salle-sory-ibrahim-koita-dit-bomba', 1, '420 places', 'Deuxième grand amphithéâtre du Palais, adapté aux mêmes usages que la Salle Seydou Badian : conférences, assemblées, cérémonies et grands événements institutionnels ou culturels.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(3, 'Terrain de Maracana', 'terrain-de-maracana', 3, 'Football', 'Terrain de football extérieur du Palais, ouvert aux séances d\'entraînement individuelles ou de club, ainsi qu\'aux matchs de gala et compétitions amicales.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(4, 'Centre d\'Accueil Tidiani COULIBALY dit Necker', 'centre-daccueil-tidiani-coulibaly-dit-necker', 4, '27 chambres', 'Hébergement économique du Palais, pensé pour l\'accueil de groupes, stagiaires et délégations en séjour à Bamako. 27 chambres réparties en deux formules : chambres ventilées, et chambres avec douche intérieure.', '', 'sejour', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(5, 'Piscine', 'piscine', 5, 'Événementiel', 'Piscine du Palais, ouverte aux cours de natation (enfants et adultes), aux entrées journalières, et à la location pour événements privés ou dînatoires.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(6, 'Terrain de Basketball', 'terrain-de-basketball', 3, 'Équipes de club', 'Terrain de basketball extérieur, disponible pour les séances d\'entraînement de club et les matchs de gala.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(7, 'Salle de Gym Daba Modibo KEITA', 'salle-de-gym-daba-modibo-keita', 3, 'Variable', 'Salle de gymnastique du complexe Daba Modibo Keita, avec une salle multifonction pouvant accueillir d\'autres disciplines et événements.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-27 15:19:28'),
(8, 'Résidence Ely Abdoulaye DIALLO', 'rsidence-ely-abdoulaye-diallo', 4, '26 chambres', 'Résidence haut de gamme du Palais : 26 chambres climatisées, entièrement équipées (téléviseur, douche intérieure), avec option petit-déjeuner. Un cadre confortable pour séjours officiels, missions ou délégations.', '', 'sejour', 1, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(12, 'Salles de Classe', 'salles-de-classe', 4, '6 salles', 'Six salles de classe identiques au sein de la Résidence Ely Abdoulaye Diallo, louables à l\'unité pour des formations, ateliers ou sessions pédagogiques.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(13, 'Cour Événementielle', 'cour-vnementielle', 5, 'Événementiel', 'Cour extérieure de la Résidence Ely Abdoulaye Diallo, pour l\'organisation d\'événements en plein air : cérémonies, réceptions, activités de groupe.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(14, 'Salle Adama SAMASSEKOU', 'salle-adama-samassekou', 2, '200 places', 'Amphithéâtre de taille moyenne, idéal pour les conférences, assemblées générales et cérémonies de moyenne envergure.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(15, 'Salle de conférence Pr Assétou Founé SAMAKE MIGAN', 'salle-de-confrence-pr-asstou-foun-samake-migan', 2, '80 places', 'Salle de conférence à taille humaine, adaptée aux réunions de travail, formations et ateliers nécessitant un cadre plus intimiste qu\'un grand amphithéâtre.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(16, 'Terrain de Handball', 'terrain-de-handball', 3, 'Équipes de club', 'Terrain de handball extérieur, distinct du terrain de basketball, disponible pour l\'entraînement de club et les matchs de gala.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(17, 'Salle Informatique Oumou Diarra Dite Dièma', 'salle-informatique-oumou-diarra-dite-dima', 2, 'Postes informatiques', 'Salle équipée de postes informatiques, dédiée aux formations numériques, à l\'initiation à l\'outil informatique et aux ateliers de bureautique.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-29 13:18:56'),
(18, 'Salle de Taekwondo Daba Modibo KEITA', 'salle-de-taekwondo-daba-modibo-keita', 3, 'Variable', 'Salle dédiée à la pratique du taekwondo au sein du complexe Daba Modibo Keita.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-29 23:57:43'),
(19, 'Salle de Karaté Daba Modibo KEITA', 'salle-de-karat-daba-modibo-keita', 3, 'Variable', 'Salle dédiée à la pratique du karaté au sein du complexe Daba Modibo Keita.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-29 23:57:43');

-- --------------------------------------------------------

--
-- Structure de la table `espace_images`
--

CREATE TABLE `espace_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `chemin` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Photos complémentaires des espaces';

--
-- Déchargement des données de la table `espace_images`
--

INSERT INTO `espace_images` (`id`, `espace_id`, `chemin`, `created_at`) VALUES
(3, 1, 'esp_1_02429b782bad541b.jpg', '2026-09-30 12:35:11'),
(4, 1, 'esp_1_0f6505b35fe9aa24.jpg', '2026-09-30 12:35:11'),
(5, 1, 'esp_1_97ba6cf794f85ad1.jpg', '2026-09-30 12:35:11'),
(6, 1, 'esp_1_545b64a99767590e.jpg', '2026-09-30 12:35:11'),
(7, 1, 'esp_1_b0fca7382fe188b7.jpg', '2026-09-30 12:35:11'),
(8, 1, 'esp_1_342cd2f8138a4560.jpg', '2026-09-30 12:35:11'),
(11, 6, 'esp_6_da31679ba0f4d25f.jpg', '2026-09-30 13:07:46'),
(12, 6, 'esp_6_791b61673d632de9.jpg', '2026-09-30 13:07:46'),
(13, 6, 'esp_6_77aba75551fc0e7c.jpg', '2026-09-30 13:07:46'),
(14, 3, 'esp_3_027ec9734457dc1b.jpg', '2026-09-30 13:08:29'),
(15, 3, 'esp_3_47e1180f43c3910d.jpg', '2026-09-30 13:08:29'),
(16, 6, 'esp_6_d567739243d85a19.jpg', '2026-09-30 13:09:38'),
(17, 14, 'esp_14_f67b9cd937954094.jpg', '2026-09-30 13:10:50'),
(18, 14, 'esp_14_63761bb4abdf6c06.jpg', '2026-09-30 13:10:50'),
(19, 14, 'esp_14_4233af805b446c69.jpg', '2026-09-30 13:10:50'),
(20, 14, 'esp_14_b500847675f4c4a4.jpg', '2026-09-30 13:10:50'),
(22, 18, 'esp_18_c99a1dba4c1eda40.jpg', '2026-09-30 13:12:15'),
(23, 18, 'esp_18_57bb32baacf8fad3.jpg', '2026-09-30 13:12:15'),
(24, 18, 'esp_18_bd9cfac814d29f2d.jpg', '2026-09-30 13:12:16'),
(25, 7, 'esp_7_66a6f2eaef5606c3.jpg', '2026-09-30 13:13:04'),
(26, 7, 'esp_7_9a2d70707e293e96.jpg', '2026-09-30 13:13:04'),
(27, 17, 'esp_17_3a4a1fe802336bb0.jpg', '2026-09-30 13:14:37'),
(28, 17, 'esp_17_1dbf0a729645d433.jpg', '2026-09-30 13:14:37'),
(29, 17, 'esp_17_4abfd163fe32e095.jpg', '2026-09-30 13:14:37'),
(30, 17, 'esp_17_df78cf6391fcc98a.jpg', '2026-09-30 13:14:37'),
(31, 17, 'esp_17_c6a80f26c183c588.jpg', '2026-09-30 13:14:37'),
(32, 15, 'esp_15_a178239d3abc37ad.jpg', '2026-09-30 14:39:29'),
(34, 13, 'esp_13_8beefa6db6bd3a0d.jpg', '2026-09-30 14:43:07'),
(35, 13, 'esp_13_550a39314494da43.jpg', '2026-09-30 14:43:07'),
(36, 13, 'esp_13_337d89dd33d40789.jpg', '2026-09-30 14:43:07'),
(37, 13, 'esp_13_bfd1f93e66ed95fd.jpg', '2026-09-30 14:43:07'),
(38, 12, 'esp_12_e75a6cd42106828c.jpg', '2026-09-30 14:45:27'),
(39, 12, 'esp_12_bcf2c0e973206823.jpg', '2026-09-30 14:45:27'),
(40, 12, 'esp_12_717085613c771b38.jpg', '2026-09-30 14:45:27'),
(41, 12, 'esp_12_546026148a515912.jpg', '2026-09-30 14:45:27'),
(42, 12, 'esp_12_47ffb6bf155f07b1.jpg', '2026-09-30 14:45:27'),
(43, 12, 'esp_12_f1b70c179278c10b.jpg', '2026-09-30 14:45:27'),
(44, 12, 'esp_12_2e86b7a46b831337.jpg', '2026-09-30 14:45:27'),
(45, 8, 'esp_8_482e5b23d12cf03a.jpg', '2026-09-30 14:48:59'),
(46, 8, 'esp_8_e879a6159ba9dac4.jpg', '2026-09-30 14:48:59'),
(47, 8, 'esp_8_a091dbabee2d7491.jpg', '2026-09-30 14:48:59'),
(48, 8, 'esp_8_0f8dad17632082b2.jpg', '2026-09-30 14:48:59'),
(49, 8, 'esp_8_54cda11a0b00d6a7.jpg', '2026-09-30 14:48:59'),
(50, 8, 'esp_8_0d17dc9ded7311df.jpg', '2026-09-30 14:48:59'),
(51, 8, 'esp_8_06859c450d275465.jpg', '2026-09-30 14:48:59'),
(52, 16, 'esp_16_0ebe4fccc7dd35d0.jpg', '2026-09-30 14:54:31'),
(53, 16, 'esp_16_76eb7a12922f7068.jpg', '2026-09-30 14:54:31'),
(54, 16, 'esp_16_e8139cd13232c7f1.jpg', '2026-09-30 14:54:31'),
(58, 2, 'esp_2_377afa6d48ca4fe3.jpg', '2026-09-30 14:57:39'),
(59, 2, 'esp_2_0d1821c139f058f4.jpg', '2026-09-30 14:57:39'),
(60, 5, 'esp_5_7ea758d50312bdee.jpg', '2026-09-30 15:00:19'),
(61, 5, 'esp_5_1560f65b6307d910.jpg', '2026-09-30 15:00:19'),
(62, 4, 'esp_4_98f8e8728a0481a0.jpg', '2026-09-30 15:03:53'),
(63, 4, 'esp_4_8c4f2a3865a5d411.jpg', '2026-09-30 15:03:53'),
(64, 4, 'esp_4_290ea152475a3f0b.jpg', '2026-09-30 15:03:53'),
(65, 4, 'esp_4_7d828b6d5994c6df.jpg', '2026-09-30 15:03:53'),
(66, 4, 'esp_4_7036380dd28d36fc.jpg', '2026-09-30 15:03:53'),
(67, 4, 'esp_4_cf02b624e8627875.jpg', '2026-09-30 15:03:53'),
(68, 4, 'esp_4_4a6297cf0451e3cf.jpg', '2026-09-30 15:03:53'),
(69, 4, 'esp_4_c865652e50a52a1f.jpg', '2026-09-30 15:03:53'),
(70, 4, 'esp_4_75f1089738c39c43.jpg', '2026-09-30 15:03:53'),
(71, 4, 'esp_4_9935be048273be97.jpg', '2026-09-30 15:03:53'),
(72, 5, 'esp_5_851d0dcf06138a6c.jpg', '2026-09-30 15:14:04'),
(73, 5, 'esp_5_279b9daa703a0955.jpg', '2026-09-30 15:14:04'),
(74, 19, 'esp_19_318617052fc00467.jpg', '2026-09-30 15:19:29'),
(75, 19, 'esp_19_59c6bdc74b816398.jpg', '2026-09-30 15:19:30'),
(76, 7, 'esp_7_32a08e0aaca2917c.jpg', '2026-09-30 15:21:32'),
(77, 7, 'esp_7_7620e354cb1ad2f2.jpg', '2026-09-30 15:21:32'),
(78, 2, 'esp_2_3452499ca53c5e95.jpg', '2026-09-30 15:52:11'),
(79, 2, 'esp_2_b8dc2254ba875ce6.jpg', '2026-09-30 15:52:11'),
(80, 8, 'esp_8_9fd577ca3097deac.jpg', '2026-09-30 16:12:59'),
(81, 8, 'esp_8_7ade8f8bf64a793d.jpg', '2026-09-30 16:12:59');

-- --------------------------------------------------------

--
-- Structure de la table `formations`
--

CREATE TABLE `formations` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `duree` varchar(150) DEFAULT NULL COMMENT 'Ex: 3 mois, 2 semaines...',
  `public_cible` varchar(200) DEFAULT NULL COMMENT 'Ex: Jeunes 15-25 ans, Femmes...',
  `photo` varchar(255) DEFAULT NULL,
  `contact` varchar(150) DEFAULT NULL,
  `date_fin` date DEFAULT NULL COMMENT 'Si dépassée, la formation reste visible mais affiche un badge Terminée',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `ordre` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Formations dispensées au sein du Palais';

--
-- Déchargement des données de la table `formations`
--

INSERT INTO `formations` (`id`, `nom`, `description`, `duree`, `public_cible`, `photo`, `contact`, `date_fin`, `actif`, `ordre`, `created_at`) VALUES
(1, 'Savonnerie', 'La formation en savonnerie initie les participants aux techniques de fabrication de savons artisanaux et aux différentes étapes nécessaires à la réalisation de produits de qualité. Elle combine des notions théoriques et des exercices pratiques afin de permettre aux participants de comprendre les matières premières utilisées, leur rôle et les précautions à respecter lors de la fabrication.\r\n\r\nAu cours de la formation, les participants découvrent notamment les différentes étapes de préparation, le dosage et le mélange des ingrédients, les techniques de fabrication, le moulage, le démoulage, le séchage ainsi que les méthodes de présentation et de conditionnement des savons.\r\n\r\nLa formation met également l’accent sur l’hygiène, la sécurité et les bonnes pratiques de production. Elle permet aux participants de développer un savoir-faire pratique pouvant être utilisé dans un cadre personnel, artisanal ou dans la mise en place d’une petite activité génératrice de revenus.\r\n\r\nLes participants pourront notamment apprendre à :\r\n\r\nIdentifier et utiliser les principales matières premières ;\r\nComprendre les différentes étapes de fabrication du savon ;\r\nPréparer et réaliser des savons artisanaux ;\r\nRespecter les règles d’hygiène et de sécurité ;\r\nAméliorer la présentation et le conditionnement des produits ;\r\nDévelopper une première approche entrepreneuriale autour de la production artisanale.', '3 mois', 'Jeunes 17 - 30', 'formation-6abd038812fe4.jpeg', 'palais@gmail.com', NULL, 1, 0, '2026-07-30 00:32:04'),
(2, 'Coupe - Couture', 'La formation en coupe et couture permet aux participants d’acquérir progressivement les connaissances et compétences nécessaires à la réalisation de vêtements et d’ouvrages textiles. Elle privilégie une approche pratique permettant de passer des notions de base à la réalisation concrète de différentes pièces.\r\n\r\nLes participants sont familiarisés avec le matériel de couture, les différents types de tissus et les techniques essentielles de prise de mesures, de traçage et de coupe. Ils apprennent également les différentes étapes d’assemblage, de couture et de finition afin de réaliser des pièces soignées et adaptées aux mesures.\r\n\r\nLa formation encourage également la créativité et l’autonomie. À travers les exercices pratiques, les participants peuvent développer leur capacité à concevoir et réaliser leurs propres modèles tout en découvrant les possibilités offertes par les métiers de la couture et de la confection.\r\n\r\nLes participants pourront notamment apprendre à :\r\n\r\nIdentifier et utiliser le matériel de coupe et de couture ;\r\nReconnaître différents types de tissus et leurs caractéristiques ;\r\nPrendre correctement les mesures ;\r\nRéaliser des tracés et des patrons simples ;\r\nEffectuer la coupe des tissus ;\r\nUtiliser une machine à coudre et les outils adaptés ;\r\nAssembler différentes pièces de tissu ;\r\nRéaliser les finitions d’un vêtement ;\r\nDévelopper leur créativité dans la conception de modèles ;\r\nAcquérir les bases nécessaires pour poursuivre une activité dans le domaine de la couture.', NULL, NULL, 'formation-6abd037502b90.jpeg', NULL, NULL, 1, 0, '2026-08-12 18:10:18'),
(3, 'Informatique', 'Description :\r\nLa formation en informatique vise à permettre aux participants de maîtriser les fondamentaux de l’utilisation d’un ordinateur et des outils numériques. Elle s’adresse particulièrement aux personnes souhaitant renforcer leurs compétences numériques pour leurs études, leur vie professionnelle ou leurs activités personnelles.\r\n\r\nLa formation permet de découvrir progressivement l’environnement informatique, l’utilisation du matériel, la gestion des fichiers et dossiers ainsi que les principaux outils bureautiques. Les participants sont également initiés à l’utilisation d’Internet, à la recherche d’informations, à la communication numérique et aux bonnes pratiques liées à la sécurité en ligne.\r\n\r\nUne attention particulière est accordée à la pratique afin que chaque participant puisse développer son autonomie dans l’utilisation des outils numériques. Les compétences acquises peuvent notamment être utiles pour la rédaction de documents, la création de présentations, la réalisation de travaux scolaires, la recherche d’opportunités ou encore l’utilisation des services numériques.\r\n\r\nLes participants pourront notamment apprendre à :\r\n\r\nIdentifier les principaux composants d’un ordinateur ;\r\nUtiliser correctement un ordinateur et ses périphériques ;\r\nCréer, organiser et gérer des fichiers et dossiers ;\r\nUtiliser les principaux outils de bureautique ;\r\nRédiger et mettre en forme des documents ;\r\nCréer des tableaux et effectuer des opérations simples ;\r\nRéaliser des présentations ;\r\nNaviguer efficacement sur Internet ;\r\nRechercher et vérifier des informations en ligne ;\r\nUtiliser les outils numériques de communication ;\r\nAdopter de bonnes pratiques en matière de sécurité numérique ;\r\nDévelopper leur autonomie face aux outils informatiques.', NULL, NULL, 'formation-6abd2ee39be16.jpeg', NULL, NULL, 1, 0, '2026-08-12 18:10:30');

-- --------------------------------------------------------

--
-- Structure de la table `formation_galerie`
--

CREATE TABLE `formation_galerie` (
  `id` int(10) UNSIGNED NOT NULL,
  `formation_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `legende` varchar(255) DEFAULT NULL,
  `ordre` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Photos complémentaires des formations, pour la fiche détail';

--
-- Déchargement des données de la table `formation_galerie`
--

INSERT INTO `formation_galerie` (`id`, `formation_id`, `image_path`, `legende`, `ordre`) VALUES
(2, 2, 'formation-gal-6abd2b18a518f.jpeg', NULL, 0),
(3, 2, 'formation-gal-6abd2b18a6f10.jpeg', NULL, 0),
(6, 3, 'formation-gal-6abd2e023d448.jpeg', NULL, 0),
(7, 3, 'formation-gal-6abd2e023e719.jpeg', NULL, 0),
(8, 3, 'formation-gal-6abd2e023fe2c.jpeg', NULL, 0),
(9, 3, 'formation-gal-6abd2e138d288.jpeg', NULL, 0),
(10, 1, 'formation-gal-6abd2e30aec5b.jpeg', NULL, 0),
(12, 1, 'formation-gal-6abd2e39bdbc1.jpeg', NULL, 0),
(13, 1, 'formation-gal-6abd2e5736dca.jpeg', NULL, 0),
(14, 1, 'formation-gal-6abd2f20c525f.jpeg', NULL, 0),
(15, 1, 'formation-gal-6abd2f20c6d28.jpeg', NULL, 0),
(16, 1, 'formation-gal-6abd2f20cc864.jpeg', NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `jeunes_engages`
--

CREATE TABLE `jeunes_engages` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `telephone` varchar(30) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `commune` varchar(150) DEFAULT NULL,
  `profession` varchar(150) DEFAULT NULL COMMENT 'Étudiant, travailleur, sans emploi...',
  `domaine_interet` varchar(150) DEFAULT NULL,
  `motivation` text DEFAULT NULL,
  `statut` enum('nouveau','contacte') NOT NULL DEFAULT 'nouveau',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Base des jeunes intéressés à s''engager auprès du Palais';

--
-- Déchargement des données de la table `jeunes_engages`
--

INSERT INTO `jeunes_engages` (`id`, `nom`, `prenom`, `age`, `date_naissance`, `telephone`, `email`, `commune`, `profession`, `domaine_interet`, `motivation`, `statut`, `created_at`) VALUES
(1, 'tapa', 'cly', 35, NULL, 'prks vkfDL', 'efaycuhjfkld@gmail.com', 'ygvjqztbeànTV P', 'Étudiant(e)', '?VNRQMKcix', 'gifwd!jlksàçrqzen', 'contacte', '2026-08-10 15:39:28'),
(2, 'Coulibaly', 'Tapa', 20, '2006-01-02', '+22390921120', 'coulibalytapa260@gmail.com', 'Kati', 'Étudiant(e)', 'Volontariat', 'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh', 'nouveau', '2026-08-14 02:31:36');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `sujet` enum('reservation_espace','activite','information_generale','reclamation','autre') NOT NULL DEFAULT 'information_generale',
  `espace_id` int(10) UNSIGNED DEFAULT NULL,
  `activite_id` int(10) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `reponse` text DEFAULT NULL COMMENT 'Réponse de l''admin messages',
  `repondu_par` int(10) UNSIGNED DEFAULT NULL,
  `repondu_at` timestamp NULL DEFAULT NULL,
  `envoye_email` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'La réponse a été envoyée par email',
  `envoye_site` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'La réponse est visible sur le compte du client',
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `lu_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Messages du formulaire de contact public';

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `nom`, `email`, `telephone`, `sujet`, `espace_id`, `activite_id`, `message`, `reponse`, `repondu_par`, `repondu_at`, `envoye_email`, `envoye_site`, `lu`, `lu_at`, `created_at`) VALUES
(1, 'Tapa COULIBALY', 'ladytapa@gmail.com', '+22390921120', 'activite', NULL, NULL, 'je souhaite y prendre part , comment faire ?', NULL, NULL, NULL, 0, 0, 1, '2026-07-29 14:14:01', '2026-07-28 21:10:15'),
(2, 'Tapa COULIBALY', 'ladytapa@gmail.com', '+22390921120', 'reservation_espace', 6, NULL, 'Nous souhaiterions organiser un tournoi , comment devrions nous proceder ?', 'veuillez passez au palais', 4, '2026-07-31 21:08:59', 0, 1, 1, '2026-07-28 21:12:29', '2026-07-28 21:10:57');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `destinataire_role` varchar(30) NOT NULL COMMENT 'Rôle ciblé ; NULL implicite = tous les membres du rôle',
  `destinataire_id` int(10) UNSIGNED DEFAULT NULL,
  `type` varchar(60) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(200) DEFAULT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Notifications internes du back-office';

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `destinataire_role`, `destinataire_id`, `type`, `message`, `lien`, `lu`, `created_at`) VALUES
(1, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 28/07/2026', 'reservations.php', 1, '2026-07-27 15:58:32'),
(2, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 28/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-27 16:00:17'),
(3, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000001 reçu pour «Salle Seydou BADIAN» le 28/07/2026 — Tapa COULIBALY', 'reservations.php?id=1', 1, '2026-07-27 16:19:47'),
(4, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000001 de 400 000 FCFA enregistré — Réservation #1', 'paiements.php?id=1', 1, '2026-07-27 16:19:47'),
(5, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Résidence Ely Abdoulaye DIALLO » du 28/07/2026 au 31/07/2026', 'reservations.php', 1, '2026-07-27 16:48:47'),
(6, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Résidence Ely Abdoulaye DIALLO » du 28/07/2026 au 31/07/2026', 'reservations.php', 1, '2026-07-27 16:48:47'),
(7, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Résidence Ely Abdoulaye DIALLO» le 28/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-27 16:49:28'),
(8, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Résidence Ely Abdoulaye DIALLO» le 28/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-27 16:49:28'),
(9, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000002 reçu pour «Résidence Ely Abdoulaye DIALLO» le 28/07/2026 — Tapa COULIBALY', 'reservations.php?id=2', 1, '2026-07-27 16:51:49'),
(10, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000002 de 90 000 FCFA enregistré — Réservation #2', 'paiements.php?id=2', 1, '2026-07-27 16:51:49'),
(11, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 30/07/2026', 'reservations.php', 1, '2026-07-27 23:34:03'),
(12, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 30/07/2026', 'reservations.php', 1, '2026-07-27 23:34:03'),
(13, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 30/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-27 23:36:00'),
(14, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 30/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-27 23:36:00'),
(15, '', 8, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-27 23:36:00'),
(16, 'superadmin', NULL, 'nouvelle_observation', 'Nouvelle observation de « sur Paiement #2', 'observations.php', 1, '2026-07-28 21:16:36'),
(17, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Terrain de Handball » le 30/07/2026', 'reservations.php', 1, '2026-07-28 22:58:00'),
(18, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Terrain de Handball » le 30/07/2026', 'reservations.php', 1, '2026-07-28 22:58:00'),
(19, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Terrain de Handball» le 30/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-28 23:00:15'),
(20, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Terrain de Handball» le 30/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-28 23:00:15'),
(21, '', 8, 'reservation_validee', 'Votre demande pour « Terrain de Handball » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-28 23:00:15'),
(22, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000003 reçu pour «Terrain de Handball» le 30/07/2026 — Tapa COULIBALY', 'reservations.php?id=4', 1, '2026-07-28 23:06:56'),
(23, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000003 de 50 000 FCFA enregistré — Réservation #4', 'paiements.php?id=4', 1, '2026-07-28 23:06:56'),
(24, '', 8, 'paiement_confirme', 'Paiement confirmé (REC-2026-000003) pour « Terrain de Handball » — votre reçu est disponible.', 'generer_bon.php?id=4', 1, '2026-07-28 23:06:56'),
(25, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000004 reçu pour «Salle Seydou BADIAN» le 30/07/2026 — Tapa COULIBALY', 'reservations.php?id=3', 1, '2026-07-28 23:23:18'),
(26, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000004 de 400 000 FCFA enregistré — Réservation #3', 'paiements.php?id=3', 1, '2026-07-28 23:23:18'),
(27, '', 8, 'paiement_confirme', 'Paiement confirmé (REC-2026-000004) pour « Salle Seydou BADIAN » — votre reçu est disponible.', 'generer_bon.php?id=3', 1, '2026-07-28 23:23:18'),
(28, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Terrain de Maracana » le 30/07/2026', 'reservations.php', 1, '2026-07-29 07:54:43'),
(29, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Terrain de Maracana » le 30/07/2026', 'reservations.php', 1, '2026-07-29 07:54:43'),
(30, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Terrain de Maracana» le 30/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-29 08:00:10'),
(31, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Terrain de Maracana» le 30/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-29 08:00:10'),
(32, '', 8, 'reservation_validee', 'Votre demande pour « Terrain de Maracana » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-29 08:00:10'),
(33, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000005 reçu pour «Terrain de Maracana» le 30/07/2026 — Tapa COULIBALY', 'reservations.php?id=5', 1, '2026-07-29 08:01:18'),
(34, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000005 de 20 000 FCFA enregistré — Réservation #5', 'paiements.php?id=5', 1, '2026-07-29 08:01:18'),
(35, '', 8, 'paiement_confirme', 'Paiement confirmé (REC-2026-000005) pour « Terrain de Maracana » — votre reçu est disponible.', 'generer_bon.php?id=5', 1, '2026-07-29 08:01:18'),
(36, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 08/10/2026', 'reservations.php', 1, '2026-07-29 14:30:05'),
(37, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 08/10/2026', 'reservations.php', 1, '2026-07-29 14:30:05'),
(38, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 08/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-30 02:25:52'),
(39, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 08/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-30 02:25:52'),
(40, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-30 02:25:52'),
(41, 'admin_espaces', NULL, 'paiement_recu', 'Paiement REC-2026-000006 reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 08/10/2026 — Tapa COULIBALY', 'reservations.php?id=6', 1, '2026-07-30 02:26:32'),
(42, 'superadmin', NULL, 'paiement_recu', 'Paiement REC-2026-000006 de 400 000 FCFA enregistré — Réservation #6', 'paiements.php?id=6', 1, '2026-07-30 02:26:32'),
(43, '', 8, 'paiement_confirme', 'Paiement confirmé (REC-2026-000006) pour « Salle Sory Ibrahim KOITA dit Bomba » — votre reçu est disponible.', 'generer_bon.php?id=6', 1, '2026-07-30 02:26:32'),
(44, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Résidence Ely Abdoulaye DIALLO » du 26/10/2026 au 29/11/2026 (12 chambres)', 'reservations.php', 1, '2026-07-30 14:03:10'),
(45, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Résidence Ely Abdoulaye DIALLO » du 26/10/2026 au 29/11/2026 (12 chambres)', 'reservations.php', 1, '2026-07-30 14:03:10'),
(46, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 31/07/2026', 'reservations.php', 1, '2026-07-30 14:10:25'),
(47, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 31/07/2026', 'reservations.php', 1, '2026-07-30 14:10:25'),
(48, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Résidence Ely Abdoulaye DIALLO» le 26/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-30 14:14:10'),
(49, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Résidence Ely Abdoulaye DIALLO» le 26/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-30 14:14:10'),
(50, '', 8, 'reservation_validee', 'Votre demande pour « Résidence Ely Abdoulaye DIALLO » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-30 14:14:10'),
(51, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 31/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-30 14:14:11'),
(52, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 31/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-30 14:14:11'),
(53, '', 8, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-30 14:14:11'),
(54, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-007/DGPP-C reçu pour «Salle Seydou BADIAN» le 31/07/2026 — Tapa COULIBALY', 'reservations.php?id=8', 1, '2026-07-30 14:16:41'),
(55, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-007/DGPP-C de 400 000 FCFA enregistré — Réservation #8', 'paiements.php?id=8', 1, '2026-07-30 14:16:41'),
(56, '', 8, 'paiement_confirme', 'Paiement confirmé (26-007/DGPP-C) pour « Salle Seydou BADIAN » — votre reçu est disponible.', 'generer_bon.php?id=8', 1, '2026-07-30 14:16:41'),
(57, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-008/DGPP-C reçu pour «Résidence Ely Abdoulaye DIALLO» le 26/10/2026 — Tapa COULIBALY', 'reservations.php?id=7', 1, '2026-07-30 14:17:21'),
(58, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-008/DGPP-C de 12 240 000 FCFA enregistré — Réservation #7', 'paiements.php?id=7', 1, '2026-07-30 14:17:21'),
(59, '', 8, 'paiement_confirme', 'Paiement confirmé (26-008/DGPP-C) pour « Résidence Ely Abdoulaye DIALLO » — votre reçu est disponible.', 'generer_bon.php?id=7', 1, '2026-07-30 14:17:21'),
(60, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 31/07/2026', 'reservations.php', 1, '2026-07-30 22:17:27'),
(61, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 31/07/2026', 'reservations.php', 1, '2026-07-30 22:17:27'),
(62, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 31/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-30 22:18:14'),
(63, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 31/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-30 22:18:14'),
(64, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-30 22:18:14'),
(65, '', 3, 'reponse_observation', '« a répondu à votre observation sur Paiement #2', 'observations.php', 1, '2026-07-30 22:35:09'),
(66, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Centre d\'Accueil Tidiani COULIBALY dit Necker » du 31/07/2026 au 02/08/2026', 'reservations.php', 1, '2026-07-31 11:37:25'),
(67, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Centre d\'Accueil Tidiani COULIBALY dit Necker » du 31/07/2026 au 02/08/2026', 'reservations.php', 1, '2026-07-31 11:37:25'),
(68, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Centre d\'Accueil Tidiani COULIBALY dit Necker» le 31/07/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-07-31 11:37:43'),
(69, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Centre d\'Accueil Tidiani COULIBALY dit Necker» le 31/07/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-07-31 11:37:43'),
(70, '', 8, 'reservation_validee', 'Votre demande pour « Centre d\'Accueil Tidiani COULIBALY dit Necker » est validée ! Votre bon de réservation est disponible — merci de régler au guichet sous 48h.', 'mon-compte.php', 1, '2026-07-31 11:37:43'),
(71, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-009/DGPP-C reçu pour «Salle Adama SAMASSEKOU» le 31/07/2026 — Tapa COULIBALY', 'reservations.php?id=9', 1, '2026-07-31 11:47:07'),
(72, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-009/DGPP-C de 150 000 FCFA enregistré — Réservation #9', 'paiements.php?id=9', 1, '2026-07-31 11:47:07'),
(73, '', 8, 'paiement_confirme', 'Paiement confirmé (26-009/DGPP-C) pour « Salle Adama SAMASSEKOU » — votre reçu est disponible.', 'generer_bon.php?id=9', 1, '2026-07-31 11:47:07'),
(74, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Piscine » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=5-2026-07-01', 1, '2026-07-31 12:22:15'),
(75, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Piscine » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=5-2026-07-01', 1, '2026-07-31 12:22:15'),
(76, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Salle de Gym Daba Modibo KEITA » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=7-2026-07-01', 1, '2026-07-31 12:22:15'),
(77, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Salle de Gym Daba Modibo KEITA » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=7-2026-07-01', 1, '2026-07-31 12:22:15'),
(78, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Basketball » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=6-2026-07-01', 1, '2026-07-31 12:22:15'),
(79, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Basketball » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=6-2026-07-01', 1, '2026-07-31 12:22:15'),
(80, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-07-01', 1, '2026-07-31 12:22:15'),
(81, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-07-01', 1, '2026-07-31 12:22:15'),
(82, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Maracana » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=3-2026-07-01', 1, '2026-07-31 12:22:15'),
(83, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Maracana » (1 mois) est dû depuis le 01/07/2026 — encaissement à effectuer.', 'baux.php?echeance=3-2026-07-01', 1, '2026-07-31 12:22:15'),
(84, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 01/08/2026', 'reservations.php', 1, '2026-07-31 13:15:35'),
(85, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 01/08/2026', 'reservations.php', 1, '2026-07-31 13:15:35'),
(86, '', 8, 'reponse_message', 'Réponse à votre message : veuillez passez au palais', 'mon-compte.php?tab=messages', 1, '2026-07-31 21:08:59'),
(87, 'superadmin', NULL, 'guichet_resa', 'Réservation guichet #12 créée — paiement à encaisser', 'reservations.php', 1, '2026-08-01 00:27:03'),
(88, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-010/DGPP-C reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 04/08/2026 — Oumou COULIBALY', 'reservations.php?id=12', 1, '2026-08-01 00:27:57'),
(89, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-010/DGPP-C de 200 000 FCFA enregistré — Réservation #12', 'paiements.php?id=12', 1, '2026-08-01 00:27:57'),
(90, '', 12, 'paiement_confirme', 'Paiement confirmé (26-010/DGPP-C) pour « Salle Sory Ibrahim KOITA dit Bomba » — votre reçu est disponible.', 'generer_bon.php?id=12', 0, '2026-08-01 00:27:57'),
(91, 'admin_dg', NULL, 'reduction_accordee', 'Réduction accordée sur le paiement 26-010/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', 'paiements.php?id=12', 0, '2026-08-01 00:27:57'),
(92, 'ministre', NULL, 'reduction_accordee', 'Réduction accordée sur le paiement 26-010/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', 'paiements.php?id=12', 1, '2026-08-01 00:27:57'),
(93, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-011/DGPP-C reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 04/08/2026 — Oumou COULIBALY', 'reservations.php?id=12', 1, '2026-08-01 00:28:08'),
(94, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-011/DGPP-C de 200 000 FCFA enregistré — Réservation #12', 'paiements.php?id=12', 1, '2026-08-01 00:28:08'),
(95, '', 12, 'paiement_confirme', 'Paiement confirmé (26-011/DGPP-C) pour « Salle Sory Ibrahim KOITA dit Bomba » — votre reçu est disponible.', 'generer_bon.php?id=12', 0, '2026-08-01 00:28:08'),
(96, 'admin_dg', NULL, 'reduction_accordee', 'Réduction accordée sur le paiement 26-011/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', 'paiements.php?id=12', 0, '2026-08-01 00:28:08'),
(97, 'ministre', NULL, 'reduction_accordee', 'Réduction accordée sur le paiement 26-011/DGPP-C : -200 000 FCFA (tarif 400 000 → 200 000 FCFA). Motif : Accord de la direction , pour l\'université', 'paiements.php?id=12', 1, '2026-08-01 00:28:08'),
(98, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Piscine » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=5-2026-08-01', 1, '2026-08-01 00:30:07'),
(99, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Piscine » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=5-2026-08-01', 1, '2026-08-01 00:30:07'),
(100, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Salle de Gym Daba Modibo KEITA » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=7-2026-08-01', 1, '2026-08-01 00:30:07'),
(101, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Salle de Gym Daba Modibo KEITA » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=7-2026-08-01', 1, '2026-08-01 00:30:07'),
(102, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Basketball » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=6-2026-08-01', 1, '2026-08-01 00:30:07'),
(103, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Basketball » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=6-2026-08-01', 1, '2026-08-01 00:30:07'),
(104, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-08-01', 1, '2026-08-01 00:30:07'),
(105, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-08-01', 1, '2026-08-01 00:30:07'),
(106, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Maracana » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=3-2026-08-01', 1, '2026-08-01 00:30:07'),
(107, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Maracana » (1 mois) est dû depuis le 01/08/2026 — encaissement à effectuer.', 'baux.php?echeance=3-2026-08-01', 1, '2026-08-01 00:30:07'),
(108, 'admin_espaces', NULL, 'reservation_expiree', 'Réservation #10 pour «Centre d\'Accueil Tidiani COULIBALY dit Necker» annulée automatiquement (48h sans paiement)', 'reservations.php', 1, '2026-08-03 11:13:33'),
(109, '', 8, 'reservation_expiree', 'Votre demande pour « Centre d\'Accueil Tidiani COULIBALY dit Necker » du 31/07/2026 a été annulée automatiquement : le paiement n\'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.', 'espaces.php', 1, '2026-08-03 11:13:33'),
(110, 'admin_espaces', NULL, 'demande_bail', 'Nouvelle demande de bail pour « Terrain de Maracana » — Coulibaly Tapa', 'admin/demandes-bail.php?id=1', 1, '2026-08-09 22:34:57'),
(111, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 10/08 au 16/08.', 'rapport.php?type=encaisse&debut=2026-08-10&fin=2026-08-16', 1, '2026-08-09 22:35:25'),
(112, '', 8, 'demande_bail_acceptee', 'Votre demande de bail pour « Terrain de Maracana » a été acceptée. Nous vous contacterons pour finaliser les modalités.', 'mon-compte.php?tab=baux', 1, '2026-08-09 23:24:35'),
(113, 'admin_espaces', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Administration Activités.', 'admin/demandes-services.php', 1, '2026-08-10 13:07:36'),
(114, 'admin_comptable', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Administration Activités. Tarif à négocier avec le client.', 'admin/demandes-services.php', 1, '2026-08-10 13:07:36'),
(115, 'admin_espaces', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Tapa COULIBALY.', 'admin/demandes-services.php', 1, '2026-08-10 13:07:59'),
(116, 'admin_comptable', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Tapa COULIBALY. Tarif à négocier avec le client.', 'admin/demandes-services.php', 1, '2026-08-10 13:07:59'),
(117, 'admin_espaces', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Tapa COULIBALY.', 'admin/demandes-services.php', 1, '2026-08-10 13:09:45'),
(118, 'admin_comptable', NULL, 'demande_service', 'Nouvelle demande de service « Espace lavage auto et moto » — Tapa COULIBALY. Tarif à négocier avec le client.', 'admin/demandes-services.php', 1, '2026-08-10 13:09:45'),
(119, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-08-10 13:14:22'),
(120, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 01/08/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-10 14:11:53'),
(121, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 01/08/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-10 14:11:53'),
(122, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Merci de régler au guichet avant le 12/08/2026 à 16:11 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-10 14:11:53'),
(123, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Autre espace » suite à la réquisition de « Salle Adama SAMASSEKOU ».', 'requisitions.php', 1, '2026-08-10 14:52:54'),
(124, 'admin_activites', NULL, 'jeune_engage', 'Nouvelle inscription « S\'engager » — tapa cly', 'admin/jeunes-engages.php', 1, '2026-08-10 15:39:28'),
(125, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-08-10 16:06:18'),
(126, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Adama SAMASSEKOU ».', 'requisitions.php', 1, '2026-08-10 16:06:58'),
(127, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Cour Événementielle » le 11/08/2026', 'reservations.php', 1, '2026-08-10 16:11:32'),
(128, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Cour Événementielle » le 11/08/2026', 'reservations.php', 1, '2026-08-10 16:11:32'),
(129, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Cour Événementielle» le 11/08/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-10 16:12:08'),
(130, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Cour Événementielle» le 11/08/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-10 16:12:09'),
(131, '', 8, 'reservation_validee', 'Votre demande pour « Cour Événementielle » est validée ! Merci de régler au guichet avant le 12/08/2026 à 18:12 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-10 16:12:09'),
(132, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Cour Événementielle » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-08-10 23:37:31'),
(133, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Cour Événementielle ».', 'requisitions.php', 1, '2026-08-10 23:37:58'),
(134, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 12/08/2026', 'reservations.php', 1, '2026-08-11 00:14:04'),
(135, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 12/08/2026', 'reservations.php', 1, '2026-08-11 00:14:04'),
(136, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 12/08/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-11 00:15:50'),
(137, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 12/08/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-11 00:15:50'),
(138, '', 8, 'reservation_validee', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » est validée ! Merci de régler au guichet avant le 13/08/2026 à 02:15 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-11 00:15:50'),
(139, 'admin_espaces', NULL, 'reservation_expiree', 'Réservation #14 pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» annulée automatiquement (48h sans paiement)', 'reservations.php', 1, '2026-08-13 12:02:22'),
(140, '', 8, 'reservation_expiree', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » du 12/08/2026 a été annulée automatiquement : le paiement n\'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.', 'espaces.php', 1, '2026-08-13 12:02:22'),
(141, 'admin_activites', NULL, 'jeune_engage', 'Nouvelle inscription « S\'engager » — Coulibaly Tapa', 'jeunes-engages.php', 1, '2026-08-14 02:31:36'),
(142, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 31/08 au 06/09.', 'rapport.php?type=encaisse&debut=2026-08-31&fin=2026-09-06', 1, '2026-08-31 00:25:20'),
(143, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 02/09/2026', 'reservations.php', 1, '2026-08-31 01:43:17'),
(144, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 02/09/2026', 'reservations.php', 1, '2026-08-31 01:43:17'),
(145, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-31 01:43:42'),
(146, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-31 01:43:42'),
(147, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 02/09/2026 à 03:43 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-31 01:43:42'),
(148, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-012/DGPP-C reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY — ACOMPTE reçu, solde restant : 200 000 FCFA', 'reservations.php?id=15', 1, '2026-08-31 01:46:47'),
(149, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-012/DGPP-C de 200 000 FCFA enregistré — Réservation #15 — ACOMPTE reçu, solde restant : 200 000 FCFA', 'paiements.php?id=15', 1, '2026-08-31 01:46:47'),
(150, '', 8, 'paiement_confirme', 'Paiement confirmé (26-012/DGPP-C) pour « Salle Sory Ibrahim KOITA dit Bomba » — votre reçu est disponible.', 'generer_bon.php?id=15', 1, '2026-08-31 01:46:47'),
(151, 'admin_espaces', NULL, 'reservation_expiree', 'Réservation #15 pour «Salle Sory Ibrahim KOITA dit Bomba» annulée automatiquement (48h sans paiement)', 'reservations.php', 1, '2026-09-22 20:52:18'),
(152, '', 8, 'reservation_expiree', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » du 02/09/2026 a été annulée automatiquement : le paiement n\'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.', 'espaces.php', 1, '2026-09-22 20:52:18'),
(153, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 21/09 au 27/09.', 'rapport.php?type=encaisse&debut=2026-09-21&fin=2026-09-27', 1, '2026-09-23 22:27:32'),
(154, 'admin_espaces', NULL, 'demande_bail', 'Nouvelle demande de bail pour « Terrain de Maracana » — COULIBALY Tapa', 'demandes-bail.php?id=2', 1, '2026-09-25 00:11:02'),
(155, 'admin_comptable', NULL, 'demande_bail', 'Nouvelle demande de bail pour « Terrain de Maracana » — COULIBALY Tapa. À traiter avant tout encaissement.', 'demandes-bail.php?id=2', 1, '2026-09-25 00:11:02'),
(156, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 1, '2026-09-26 22:06:29'),
(157, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 1, '2026-09-26 22:06:29'),
(158, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-26 22:06:56'),
(159, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-26 22:06:56'),
(160, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 29/09/2026 à 00:06 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-26 22:06:56'),
(161, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-26 22:07:52'),
(162, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Sory Ibrahim KOITA dit Bomba ».. Opération #1 à traiter.', 'requisitions.php', 1, '2026-09-26 22:08:26'),
(163, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 03/10/2026', 'reservations.php', 1, '2026-09-28 00:35:28'),
(164, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 03/10/2026', 'reservations.php', 1, '2026-09-28 00:35:28'),
(165, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 28/09 au 04/10.', 'rapport.php?type=encaisse&debut=2026-09-28&fin=2026-10-04', 1, '2026-09-28 00:35:37'),
(166, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 03/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 00:35:59'),
(167, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 03/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-28 00:35:59'),
(168, '', 8, 'reservation_validee', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » est validée ! Merci de régler au guichet avant le 30/09/2026 à 02:35 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 00:35:59'),
(169, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 00:36:08'),
(170, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle de conférence Pr Assétou Founé SAMAKE MIGAN ».. Opération #2 à traiter.', 'requisitions.php', 1, '2026-09-28 00:36:53'),
(171, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 08/10/2026', 'reservations.php', 1, '2026-09-28 14:48:47'),
(172, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 08/10/2026', 'reservations.php', 1, '2026-09-28 14:48:47'),
(173, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 08/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 14:49:01'),
(174, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 08/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-28 14:49:01'),
(175, '', 8, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Merci de régler au guichet avant le 30/09/2026 à 16:49 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 14:49:01'),
(176, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Seydou BADIAN » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 14:49:09'),
(177, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Autre espace » suite à la réquisition de « Salle Seydou BADIAN ».. Opération #3 à traiter.', 'requisitions.php', 1, '2026-09-28 14:50:50'),
(178, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/09/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-09-01', 1, '2026-09-28 16:06:16'),
(179, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/09/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-09-01', 1, '2026-09-28 16:06:16'),
(180, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 1, '2026-09-28 16:06:58'),
(181, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 1, '2026-09-28 16:06:58'),
(182, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 16:07:13'),
(183, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-28 16:07:13'),
(184, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 30/09/2026 à 18:07 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 16:07:13'),
(185, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 16:07:22'),
(186, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Sory Ibrahim KOITA dit Bomba ».. Opération #4 à traiter.', 'requisitions.php', 1, '2026-09-28 16:08:44'),
(187, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 07/10/2026', 'reservations.php', 1, '2026-09-28 18:17:11'),
(188, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 07/10/2026', 'reservations.php', 1, '2026-09-28 18:17:11'),
(189, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 18:17:25'),
(190, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-28 18:17:25'),
(191, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Merci de régler au guichet avant le 30/09/2026 à 20:17 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 18:17:25'),
(192, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-013/DGPP-C reçu pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY', 'reservations.php?id=20', 1, '2026-09-28 18:18:11'),
(193, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-013/DGPP-C de 150 000 FCFA enregistré — Réservation #20', 'paiements.php?id=20', 1, '2026-09-28 18:18:11'),
(194, '', 8, 'paiement_confirme', 'Paiement confirmé (26-013/DGPP-C) pour « Salle Adama SAMASSEKOU » — votre reçu est disponible.', 'generer_bon.php?id=20', 1, '2026-09-28 18:18:11'),
(195, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 18:18:23'),
(196, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Adama SAMASSEKOU ». — Montant à rembourser : 150 000 FCFA. Opération #5 à traiter.', 'requisitions.php', 1, '2026-09-28 18:19:40'),
(197, 'admin_comptable', NULL, 'requisition_traitement', 'La réquisition #8 est maintenant en cours de traitement.', 'requisitions.php', 1, '2026-09-28 20:24:45'),
(198, 'admin_comptable', NULL, 'requisition_remboursement', 'Le remboursement de la réquisition #8 a été enregistré : 150 000 FCFA.', 'requisitions.php', 1, '2026-09-28 20:25:30'),
(199, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #5 est maintenant en traitement.', 'requisition-detail.php?id=5', 1, '2026-09-28 20:43:03'),
(200, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 07/10/2026', 'reservations.php', 1, '2026-09-29 07:43:48'),
(201, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 07/10/2026', 'reservations.php', 1, '2026-09-29 07:43:48'),
(202, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 07/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-29 07:44:04'),
(203, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 07/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-29 07:44:04'),
(204, '', 8, 'reservation_validee', 'Votre demande pour « Salle Informatique Oumou Diarra Dite Dièma » est validée ! Merci de régler au guichet avant le 01/10/2026 à 09:44 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-29 07:44:04'),
(205, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-014/DGPP-C reçu pour «Salle Informatique Oumou Diarra Dite Dièma» le 07/10/2026 — Tapa COULIBALY', 'reservations.php?id=21', 1, '2026-09-29 07:46:59'),
(206, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-014/DGPP-C de 50 000 FCFA enregistré — Réservation #21', 'paiements.php?id=14', 1, '2026-09-29 07:46:59'),
(207, '', 8, 'paiement_confirme', 'Paiement confirmé (26-014/DGPP-C) de 50 000 FCFA pour « Salle Informatique Oumou Diarra Dite Dièma » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=21', 1, '2026-09-29 07:46:59'),
(208, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Informatique Oumou Diarra Dite Dièma » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-29 07:48:21'),
(209, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Informatique Oumou Diarra Dite Dièma ».. Opération #6 à traiter.', 'requisitions.php', 1, '2026-09-29 07:58:30'),
(210, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #9 est maintenant en traitement.', 'requisition-detail.php?id=9', 1, '2026-09-29 08:42:18'),
(211, '', 12, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 0, '2026-09-29 13:21:41'),
(212, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 08/10/2026 de 07:00 à 17:00 — suite à la réquisition #9', 'reservations.php?id=22', 1, '2026-09-29 15:41:51'),
(213, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 08/10/2026 de 07:00 à 17:00 — suite à la réquisition #9', 'reservations.php?id=22', 1, '2026-09-29 15:41:51'),
(214, 'admin_comptable', NULL, 'requisition_nouvelle_reservation', 'Réquisition #9 : le client a déposé la nouvelle réservation #22 (en attente de validation par l\'administration des espaces).', 'requisition-detail.php?id=9', 1, '2026-09-29 15:41:51'),
(215, 'admin_comptable', NULL, 'reservation_validee', 'Réquisition #9 : réservation #22 validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 08/10/2026 — 50 000 FCFA transférés.', 'requisition-detail.php?id=9', 1, '2026-09-29 15:42:10'),
(216, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 08/10/2026 — Tapa COULIBALY (réquisition #9)', 'reservations.php', 1, '2026-09-29 15:42:10'),
(217, '', 8, 'reservation_validee', 'Votre nouvelle réservation pour « Salle Informatique Oumou Diarra Dite Dièma » (suite à la réquisition) est validée ! Le paiement déjà versé (50 000 FCFA) a été rattaché à cette nouvelle réservation : aucun nouveau paiement n\'est nécessaire.', 'mon-compte.php', 1, '2026-09-29 15:42:10'),
(218, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Informatique Oumou Diarra Dite Dièma » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-29 15:42:17'),
(219, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Informatique Oumou Diarra Dite Dièma ».. Opération #7 à traiter.', 'requisitions.php', 1, '2026-09-29 15:46:24'),
(220, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 01/10/2026 de 07:00 à 17:00 — suite à la réquisition #11', 'reservations.php?id=23', 1, '2026-09-29 15:46:52'),
(221, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Informatique Oumou Diarra Dite Dièma » le 01/10/2026 de 07:00 à 17:00 — suite à la réquisition #11', 'reservations.php?id=23', 1, '2026-09-29 15:46:52'),
(222, 'admin_comptable', NULL, 'requisition_nouvelle_reservation', 'Réquisition #11 : le client a déposé la nouvelle réservation #23 (en attente de validation par l\'administration des espaces).', 'requisition-detail.php?id=11', 1, '2026-09-29 15:46:52'),
(223, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 03/10/2026', 'reservations.php', 1, '2026-09-29 15:47:27'),
(224, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 03/10/2026', 'reservations.php', 1, '2026-09-29 15:47:27'),
(225, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 03/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-29 15:47:44'),
(226, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 03/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-29 15:47:44'),
(227, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Merci de régler au guichet avant le 01/10/2026 à 17:47 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-29 15:47:44'),
(228, 'admin_comptable', NULL, 'reservation_validee', 'Réquisition #11 : réservation #23 validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 01/10/2026 — 50 000 FCFA transférés.', 'requisition-detail.php?id=11', 1, '2026-09-29 15:47:47'),
(229, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Informatique Oumou Diarra Dite Dièma» le 01/10/2026 — Tapa COULIBALY (réquisition #11)', 'reservations.php', 1, '2026-09-29 15:47:47'),
(230, '', 8, 'reservation_validee', 'Votre nouvelle réservation pour « Salle Informatique Oumou Diarra Dite Dièma » (suite à la réquisition) est validée ! Le paiement déjà versé (50 000 FCFA) a été rattaché à cette nouvelle réservation : aucun nouveau paiement n\'est nécessaire.', 'mon-compte.php', 1, '2026-09-29 15:47:47'),
(231, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #11 est maintenant en traitement.', 'requisition-detail.php?id=11', 1, '2026-09-29 15:47:59'),
(232, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-015/DGPP-C reçu pour «Salle Adama SAMASSEKOU» le 03/10/2026 — Tapa COULIBALY', 'reservations.php?id=24', 1, '2026-09-29 15:50:01'),
(233, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-015/DGPP-C de 150 000 FCFA enregistré — Réservation #24', 'paiements.php?id=15', 1, '2026-09-29 15:50:01'),
(234, '', 8, 'paiement_confirme', 'Paiement confirmé (26-015/DGPP-C) de 150 000 FCFA pour « Salle Adama SAMASSEKOU » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=24', 1, '2026-09-29 15:50:01'),
(235, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-29 15:50:15');
INSERT INTO `notifications` (`id`, `destinataire_role`, `destinataire_id`, `type`, `message`, `lien`, `lu`, `created_at`) VALUES
(236, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Adama SAMASSEKOU ». — Montant à rembourser : 150 000 FCFA. Opération #8 à traiter.', 'requisitions.php', 1, '2026-09-29 15:50:33'),
(237, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #12 est maintenant en traitement.', 'requisition-detail.php?id=12', 1, '2026-09-29 15:51:09'),
(238, 'ministre', NULL, 'requisition_traitee', 'Le remboursement de la réquisition #12 a été effectué.', 'requisition-detail.php?id=12', 0, '2026-09-29 15:51:37'),
(239, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #4 est maintenant en traitement.', 'requisition-detail.php?id=4', 1, '2026-09-29 16:43:05'),
(240, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 23/10/2026 de 08:00 à 18:00 — suite à la réquisition #5', 'reservations.php?id=25', 1, '2026-09-29 17:38:27'),
(241, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 23/10/2026 de 08:00 à 18:00 — suite à la réquisition #5', 'reservations.php?id=25', 1, '2026-09-29 17:38:27'),
(242, 'admin_comptable', NULL, 'requisition_nouvelle_reservation', 'Réquisition #5 : le client a déposé la nouvelle réservation #25 (en attente de validation par l\'administration des espaces).', 'requisition-detail.php?id=5', 1, '2026-09-29 17:38:27'),
(243, 'admin_comptable', NULL, 'reservation_validee', 'Réservation RESA-25 validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 23/10/2026 — Tapa COULIBALY (REQ-5, réservation initiale non payée) : 150 000 FCFA à encaisser sous 48 h', 'paiements.php?resa=25', 1, '2026-09-29 19:28:13'),
(244, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 23/10/2026 — Tapa COULIBALY (réquisition #5)', 'reservations.php', 1, '2026-09-29 19:28:13'),
(245, '', 8, 'reservation_validee', 'Votre nouvelle réservation pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » (suite à la réquisition) est validée ! Montant à régler : 150 000 FCFA, au guichet avant le 01/10/2026 à 21:28 (48 h) — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-29 19:28:13'),
(246, 'superadmin', NULL, 'nouvelle_suggestion', 'Nouvelle suggestion anonyme reçue.', 'suggestions.php', 1, '2026-09-29 23:09:28'),
(247, 'admin_espaces', NULL, 'nouvelle_reservation', '[Prioritaire · Partenaire CICB] Nouvelle demande de Cherif Haidara pour « Salle Seydou BADIAN » le 11/10/2026', 'reservations.php?id=26', 1, '2026-09-30 00:35:10'),
(248, 'superadmin', NULL, 'nouvelle_reservation', '[Prioritaire · Partenaire CICB] Nouvelle demande de Cherif Haidara pour « Salle Seydou BADIAN » le 11/10/2026', 'reservations.php?id=26', 1, '2026-09-30 00:35:10'),
(249, 'admin_comptable', NULL, 'nouvelle_reservation', '[Prioritaire · Partenaire CICB] Nouvelle demande partenaire RESA-26 pour « Salle Seydou BADIAN » le 11/10/2026 (en attente de validation).', 'reservations.php?id=26', 1, '2026-09-30 00:35:10'),
(250, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 11/10/2026 — Cherif Haidara : paiement à encaisser', 'paiements.php', 0, '2026-09-30 01:04:52'),
(251, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 11/10/2026 — Cherif Haidara', 'reservations.php', 1, '2026-09-30 01:04:52'),
(252, '', 13, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Merci de régler au guichet avant le 02/10/2026 à 03:04 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-30 01:04:52'),
(253, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-016/DGPP-C reçu pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 23/10/2026 — Tapa COULIBALY — ACOMPTE, reste à payer : 75 000 FCFA avant le 22/10/2026 à 08:00', 'reservations.php?id=25', 1, '2026-09-30 01:05:16'),
(254, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-016/DGPP-C de 75 000 FCFA enregistré — Réservation #25 — ACOMPTE, reste à payer : 75 000 FCFA avant le 22/10/2026 à 08:00', 'paiements.php?id=16', 1, '2026-09-30 01:05:16'),
(255, '', 8, 'paiement_confirme', 'Paiement confirmé (26-016/DGPP-C) de 75 000 FCFA pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » — reste à payer : 75 000 FCFA avant le 22/10/2026 à 08:00. Votre document est disponible.', 'generer_bon.php?id=25', 1, '2026-09-30 01:05:16'),
(256, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-017/DGPP-C reçu pour «Salle Seydou BADIAN» le 11/10/2026 — Cherif Haidara', 'reservations.php?id=26', 1, '2026-09-30 01:05:50'),
(257, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-017/DGPP-C de 400 000 FCFA enregistré — Réservation #26', 'paiements.php?id=17', 1, '2026-09-30 01:05:50'),
(258, '', 13, 'paiement_confirme', 'Paiement confirmé (26-017/DGPP-C) de 400 000 FCFA pour « Salle Seydou BADIAN » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=26', 1, '2026-09-30 01:05:50'),
(259, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 03/10/2026', 'reservations.php', 1, '2026-09-30 12:06:30'),
(260, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 03/10/2026', 'reservations.php', 1, '2026-09-30 12:06:30'),
(261, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 03/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 0, '2026-09-30 12:07:30'),
(262, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 03/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-30 12:07:30'),
(263, '', 8, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Merci de régler au guichet avant le 02/10/2026 à 14:07 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-30 12:07:30'),
(264, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-018/DGPP-C reçu pour «Salle Seydou BADIAN» le 03/10/2026 — Tapa COULIBALY', 'reservations.php?id=27', 1, '2026-09-30 12:11:51'),
(265, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-018/DGPP-C de 400 000 FCFA enregistré — Réservation #27', 'paiements.php?id=18', 1, '2026-09-30 12:11:51'),
(266, '', 8, 'paiement_confirme', 'Paiement confirmé (26-018/DGPP-C) de 400 000 FCFA pour « Salle Seydou BADIAN » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=27', 1, '2026-09-30 12:11:51'),
(267, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Seydou BADIAN » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-30 12:15:34'),
(268, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Seydou BADIAN ».. Opération #9 à traiter.', 'requisitions.php', 0, '2026-09-30 12:16:58'),
(269, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 24/10/2026 de 07:00 à 18:00 — suite à la réquisition #13', 'reservations.php?id=28', 1, '2026-09-30 12:17:48'),
(270, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 24/10/2026 de 07:00 à 18:00 — suite à la réquisition #13', 'reservations.php?id=28', 1, '2026-09-30 12:17:48'),
(271, 'admin_comptable', NULL, 'requisition_nouvelle_reservation', 'Réquisition #13 : le client a déposé la nouvelle réservation #28 (en attente de validation par l\'administration des espaces).', 'requisition-detail.php?id=13', 0, '2026-09-30 12:17:48'),
(272, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #13 est maintenant en traitement.', 'requisition-detail.php?id=13', 0, '2026-09-30 12:18:13'),
(273, 'admin_comptable', NULL, 'reservation_validee', 'Réquisition #13 : réservation #28 validée pour «Salle Seydou BADIAN» le 24/10/2026 — 400 000 FCFA transférés.', 'requisition-detail.php?id=13', 0, '2026-09-30 12:18:38'),
(274, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 24/10/2026 — Tapa COULIBALY (réquisition #13)', 'reservations.php', 1, '2026-09-30 12:18:39'),
(275, '', 8, 'reservation_validee', 'Votre nouvelle réservation pour « Salle Seydou BADIAN » (suite à la réquisition) est validée ! Le paiement déjà versé (400 000 FCFA) a été rattaché à cette nouvelle réservation : aucun nouveau paiement n\'est nécessaire.', 'mon-compte.php', 1, '2026-09-30 12:18:39'),
(276, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 01/10/2026', 'reservations.php', 0, '2026-09-30 13:24:37'),
(277, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 01/10/2026', 'reservations.php', 1, '2026-09-30 13:24:37'),
(278, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 01/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 0, '2026-09-30 13:26:05'),
(279, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 01/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-30 13:26:05'),
(280, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 02/10/2026 à 15:26 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-30 13:26:05'),
(281, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-019/DGPP-C reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 01/10/2026 — Tapa COULIBALY', 'reservations.php?id=29', 0, '2026-09-30 13:32:23'),
(282, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-019/DGPP-C de 400 000 FCFA enregistré — Réservation #29', 'paiements.php?id=19', 1, '2026-09-30 13:32:23'),
(283, '', 8, 'paiement_confirme', 'Paiement confirmé (26-019/DGPP-C) de 400 000 FCFA pour « Salle Sory Ibrahim KOITA dit Bomba » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=29', 1, '2026-09-30 13:32:23'),
(284, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-30 13:32:59'),
(285, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Sory Ibrahim KOITA dit Bomba ». — Montant à rembourser : 400 000 FCFA. Opération #10 à traiter.', 'requisitions.php', 0, '2026-09-30 13:33:27'),
(286, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #14 est maintenant en traitement.', 'requisition-detail.php?id=14', 0, '2026-09-30 13:33:54'),
(287, 'ministre', NULL, 'requisition_traitee', 'Le remboursement de la réquisition #14 a été effectué.', 'requisition-detail.php?id=14', 0, '2026-09-30 13:34:23'),
(288, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 04/10/2026', 'reservations.php', 0, '2026-09-30 13:52:35'),
(289, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 04/10/2026', 'reservations.php', 1, '2026-09-30 13:52:35'),
(290, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 04/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 0, '2026-09-30 13:53:02'),
(291, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 04/10/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-09-30 13:53:02'),
(292, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Merci de régler au guichet avant le 02/10/2026 à 15:53 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-30 13:53:02'),
(293, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-020/DGPP-C reçu pour «Salle Adama SAMASSEKOU» le 04/10/2026 — Tapa COULIBALY', 'reservations.php?id=30', 0, '2026-09-30 13:54:44'),
(294, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-020/DGPP-C de 150 000 FCFA enregistré — Réservation #30', 'paiements.php?id=20', 1, '2026-09-30 13:54:44'),
(295, '', 8, 'paiement_confirme', 'Paiement confirmé (26-020/DGPP-C) de 150 000 FCFA pour « Salle Adama SAMASSEKOU » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=30', 1, '2026-09-30 13:54:44'),
(296, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-30 13:56:05'),
(297, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Adama SAMASSEKOU ». — Montant à rembourser : 150 000 FCFA. Opération #11 à traiter.', 'requisitions.php', 0, '2026-09-30 13:56:39'),
(298, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #15 est maintenant en traitement.', 'requisition-detail.php?id=15', 0, '2026-09-30 13:57:10'),
(299, 'ministre', NULL, 'requisition_traitee', 'Le remboursement de la réquisition #15 a été effectué.', 'requisition-detail.php?id=15', 0, '2026-09-30 13:57:39'),
(300, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Sitan COULIBALY pour « Salle Seydou BADIAN » le 30/10/2026', 'reservations.php', 0, '2026-09-30 20:07:39'),
(301, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Sitan COULIBALY pour « Salle Seydou BADIAN » le 30/10/2026', 'reservations.php', 0, '2026-09-30 20:07:39'),
(302, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 30/10/2026 — Sitan COULIBALY : paiement à encaisser', 'paiements.php', 0, '2026-09-30 20:08:00'),
(303, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 30/10/2026 — Sitan COULIBALY', 'reservations.php', 0, '2026-09-30 20:08:00'),
(304, '', 14, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Merci de régler au guichet avant le 02/10/2026 à 22:08 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-30 20:08:00'),
(305, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-021/DGPP-C reçu pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 23/10/2026 — Tapa COULIBALY', 'reservations.php?id=25', 0, '2026-09-30 20:09:07'),
(306, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-021/DGPP-C de 75 000 FCFA enregistré — Réservation #25', 'paiements.php?id=21', 0, '2026-09-30 20:09:07'),
(307, '', 8, 'paiement_confirme', 'Paiement confirmé (26-021/DGPP-C) de 75 000 FCFA pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=25', 0, '2026-09-30 20:09:07'),
(308, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-022/DGPP-C reçu pour «Salle Seydou BADIAN» le 30/10/2026 — Sitan COULIBALY', 'reservations.php?id=31', 0, '2026-09-30 20:09:47'),
(309, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-022/DGPP-C de 400 000 FCFA enregistré — Réservation #31', 'paiements.php?id=22', 0, '2026-09-30 20:09:47'),
(310, '', 14, 'paiement_confirme', 'Paiement confirmé (26-022/DGPP-C) de 400 000 FCFA pour « Salle Seydou BADIAN » — réservation entièrement réglée. Votre document est disponible.', 'generer_bon.php?id=31', 1, '2026-09-30 20:09:47');

-- --------------------------------------------------------

--
-- Structure de la table `observations`
--

CREATE TABLE `observations` (
  `id` int(10) UNSIGNED NOT NULL,
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `cible_type` enum('reservation','paiement','activite','espace','requisition','remboursement') NOT NULL,
  `cible_id` int(10) UNSIGNED NOT NULL,
  `contenu` text NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Si renseigné, ceci est une réponse à cette observation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Notes de suivi rédigées par les profils d''administration';

--
-- Déchargement des données de la table `observations`
--

INSERT INTO `observations` (`id`, `auteur_id`, `cible_type`, `cible_id`, `contenu`, `parent_id`, `created_at`) VALUES
(1, 3, 'paiement', 2, 'Pourquoi avoir fait une réduction', NULL, '2026-07-28 21:16:36'),
(2, 7, 'paiement', 2, 'C\'était pour un evenement caritatif , une cause très noble , et il sont avec le ministère de la promotion de la femme et de l\'enfant', 1, '2026-07-30 22:35:09');

-- --------------------------------------------------------

--
-- Structure de la table `operations_requisition`
--

CREATE TABLE `operations_requisition` (
  `id` int(10) UNSIGNED NOT NULL,
  `requisition_id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `type_operation` enum('annulation','remboursement','changement_espace','nouvelle_date','autre') NOT NULL,
  `statut` enum('a_traiter','en_cours','en_attente_information','traitee','annulee') NOT NULL DEFAULT 'a_traiter',
  `montant_concerne` decimal(12,2) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `agent_assigne` int(10) UNSIGNED DEFAULT NULL,
  `date_prise_en_charge` datetime DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `resultat` text DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Opérations administratives issues des réquisitions';

--
-- Déchargement des données de la table `operations_requisition`
--

INSERT INTO `operations_requisition` (`id`, `requisition_id`, `reservation_id`, `type_operation`, `statut`, `montant_concerne`, `description`, `agent_assigne`, `date_prise_en_charge`, `traite_par`, `date_traitement`, `resultat`, `reference`, `justificatif`, `note`, `created_at`, `updated_at`) VALUES
(1, 4, 16, 'nouvelle_date', 'en_cours', NULL, 'Choix du client : nouvelle_date — 10', 7, '2026-09-29 16:43:05', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-26 22:08:26', '2026-09-29 16:43:05'),
(2, 5, 17, 'nouvelle_date', 'en_cours', NULL, 'Choix du client : nouvelle_date — 2026-10-10\nNouvelle réservation #25 demandée par le client pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 23/10/2026 de 08:00 à 18:00 — en attente de validation (29/09/2026 à 19:38).', 7, '2026-09-28 20:43:03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:36:53', '2026-09-29 17:38:27'),
(3, 6, 18, 'changement_espace', 'traitee', NULL, 'Choix du client : autre_espace — Salle Adama SAMASSEKOU', NULL, NULL, 7, '2026-09-28 15:51:52', 'Demande de changement d’espace enregistrée et traitée.', NULL, NULL, NULL, '2026-09-28 14:50:50', '2026-09-28 15:51:52'),
(4, 7, 19, 'nouvelle_date', 'traitee', NULL, 'Choix du client : nouvelle_date — 2026-09-30', NULL, NULL, 7, '2026-09-28 16:09:23', 'Demande de nouvelle date enregistrée et traitée.', NULL, NULL, NULL, '2026-09-28 16:08:44', '2026-09-28 16:09:23'),
(5, 8, 20, 'remboursement', 'traitee', 150000.00, 'Choix du client : remboursement', 7, '2026-09-28 20:24:45', 7, '2026-09-28 20:25:30', 'effectue', '02', NULL, 'Remboursement effectué par Espèces — montant : 150 000 FCFA — Réf. : 02', '2026-09-28 18:19:40', '2026-09-28 20:25:30'),
(6, 9, 21, 'nouvelle_date', 'en_cours', NULL, 'Choix du client : nouvelle_date — 2026-10-07\nNouvelle réservation #22 demandée par le client pour « Salle Informatique Oumou Diarra Dite Dièma » le 08/10/2026 de 07:00 à 17:00 — en attente de validation (29/09/2026 à 17:41).\nPaiements transférés vers la réservation #22 : 50 000 FCFA — montant net dû : 50 000 FCFA.', 7, '2026-09-29 08:42:18', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 07:58:30', '2026-09-29 15:42:10'),
(7, 11, 22, 'nouvelle_date', 'traitee', NULL, 'Choix du client : nouvelle_date — 2026-10-01\nNouvelle réservation #23 demandée par le client pour « Salle Informatique Oumou Diarra Dite Dièma » le 01/10/2026 de 07:00 à 17:00 — en attente de validation (29/09/2026 à 17:46).\nPaiements transférés vers la réservation #23 : 50 000 FCFA — montant net dû : 50 000 FCFA.', 7, '2026-09-29 15:47:59', 7, '2026-09-29 15:48:21', 'Valider', '01', NULL, '', '2026-09-29 15:46:24', '2026-09-29 15:48:21'),
(8, 12, 24, 'remboursement', 'traitee', 150000.00, 'Choix du client : remboursement', 7, '2026-09-29 15:51:09', 7, '2026-09-29 15:51:37', 'effectue', '02', NULL, '', '2026-09-29 15:50:33', '2026-09-29 15:51:37'),
(9, 13, 27, 'nouvelle_date', 'en_cours', NULL, 'Choix du client : nouvelle_date — 2026-10-03\nNouvelle réservation #28 demandée par le client pour « Salle Seydou BADIAN » le 24/10/2026 de 07:00 à 18:00 — en attente de validation (30/09/2026 à 14:17).\nPaiements transférés vers la réservation #28 : 400 000 FCFA — montant net dû : 400 000 FCFA.', 7, '2026-09-30 12:18:13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 12:16:58', '2026-09-30 12:18:38'),
(10, 14, 29, 'remboursement', 'traitee', 400000.00, 'Choix du client : remboursement', 7, '2026-09-30 13:33:54', 7, '2026-09-30 13:34:23', 'effectue', '02', NULL, '', '2026-09-30 13:33:27', '2026-09-30 13:34:23'),
(11, 15, 30, 'remboursement', 'traitee', 150000.00, 'Choix du client : remboursement', 7, '2026-09-30 13:57:10', 7, '2026-09-30 13:57:39', 'effectue', '', NULL, '', '2026-09-30 13:56:39', '2026-09-30 13:57:39');

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `montant` decimal(12,2) NOT NULL COMMENT 'Montant réellement encaissé (après réduction éventuelle)',
  `montant_reference` decimal(12,2) DEFAULT NULL COMMENT 'Montant attendu selon le tarif, avant toute réduction',
  `motif_reduction` text DEFAULT NULL COMMENT 'Raison de la réduction accordée, obligatoire si montant < montant_reference',
  `mode` enum('especes','orange_money','moov_money','virement','cheque') NOT NULL COMMENT 'Mobile Money = paiement présentiel via marchand Orange/Moov, sans API en ligne',
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `enregistre_par` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des encaissements physiques (guichet)';

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`id`, `reservation_id`, `montant`, `montant_reference`, `motif_reduction`, `mode`, `reference`, `note`, `enregistre_par`, `created_at`) VALUES
(1, 1, 400000.00, 400000.00, NULL, 'especes', '01', '', 7, '2026-07-27 16:19:47'),
(2, 2, 90000.00, 90000.00, NULL, 'especes', '02', '', 7, '2026-07-27 16:51:49'),
(3, 4, 50000.00, 50000.00, NULL, 'orange_money', 'om2026', '', 7, '2026-07-28 23:06:56'),
(4, 3, 400000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-07-28 23:23:18'),
(5, 5, 20000.00, 20000.00, NULL, 'especes', '', '', 7, '2026-07-29 08:01:18'),
(6, 6, 400000.00, 400000.00, NULL, 'cheque', 'om2026', '', 7, '2026-07-30 02:26:32'),
(7, 8, 400000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-07-30 14:16:41'),
(8, 7, 12240000.00, 12240000.00, NULL, 'especes', '', '', 7, '2026-07-30 14:17:21'),
(9, 9, 150000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-07-31 11:47:07'),
(10, 12, 200000.00, 400000.00, 'Accord de la direction , pour l\'université', 'especes', '', '', 7, '2026-08-01 00:27:57'),
(11, 12, 200000.00, 400000.00, 'Accord de la direction , pour l\'université', 'especes', '', '', 7, '2026-08-01 00:28:08'),
(12, 15, 200000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-08-31 01:46:47'),
(13, 20, 150000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-09-28 18:18:11'),
(14, 23, 50000.00, 50000.00, NULL, 'especes', '', '[Réquisition #9] Paiement transféré de la réservation #21 vers la réservation #22 le 29/09/2026 à 17:42.\n[Réquisition #11] Paiement transféré de la réservation #22 vers la réservation #23 le 29/09/2026 à 17:47.', 7, '2026-09-29 07:46:59'),
(15, 24, 150000.00, 150000.00, NULL, 'especes', '02', '', 7, '2026-09-29 15:50:01'),
(16, 25, 75000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-09-30 01:05:16'),
(17, 26, 400000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-09-30 01:05:50'),
(18, 28, 400000.00, 400000.00, NULL, 'especes', '', '[Réquisition #13] Paiement transféré de la réservation #27 vers la réservation #28 le 30/09/2026 à 14:18.', 7, '2026-09-30 12:11:51'),
(19, 29, 400000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-09-30 13:32:23'),
(20, 30, 150000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-09-30 13:54:44'),
(21, 25, 75000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-09-30 20:09:07'),
(22, 31, 400000.00, 400000.00, NULL, 'especes', '', '', 7, '2026-09-30 20:09:47');

-- --------------------------------------------------------

--
-- Structure de la table `partenaires`
--

CREATE TABLE `partenaires` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `type` varchar(100) DEFAULT NULL COMMENT 'Institution, ministère, entreprise, association…',
  `contact_nom` varchar(200) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = désactivé (les réservations passées restent attribuées)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Organismes et institutions partenaires du Palais';

--
-- Déchargement des données de la table `partenaires`
--

INSERT INTO `partenaires` (`id`, `nom`, `type`, `contact_nom`, `telephone`, `email`, `notes`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'CICB', 'Centre de conférence', 'Cherif', '90921120', 'cicb@gmail.com', NULL, 1, '2026-09-29 20:21:33', '2026-09-29 20:22:38');

-- --------------------------------------------------------

--
-- Structure de la table `personnalites`
--

CREATE TABLE `personnalites` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL COMMENT 'Ex: Écrivain, Homme politique, ancien Ministre...',
  `photo` varchar(255) DEFAULT NULL,
  `parcours` text DEFAULT NULL COMMENT 'Biographie / parcours',
  `espace_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Espace du Palais qui porte son nom, si applicable',
  `ordre` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Figures dont les espaces du Palais portent le nom';

--
-- Déchargement des données de la table `personnalites`
--

INSERT INTO `personnalites` (`id`, `nom`, `prenom`, `titre`, `photo`, `parcours`, `espace_id`, `ordre`, `created_at`) VALUES
(1, 'Samaké Migan', 'Assetou Founé', 'Ancienne Ministre', 'perso-6abcf8bc5fe61.jpeg', 'Domaine : Sciences (physiologie végétale, génétique), Recherche et Politique\r\nDate de naissance : 1960 à San (région de Ségou), Mali\r\nFormations académiques : Doctorat en sciences biologiques, spécialité génétique des plantes et amélioration variétale, Université d\'État de Kharkiv (ex-URSS) ; certificat en biotechnologie agricole, Laboratoire de biotechnologie UNESCO de l\'Université Cheikh-Anta-Diop de Dakar\r\nProfession : Professeure titulaire de biologie, physiologiste, enseignante-chercheuse\r\nStatut actuel : Conseillère spéciale du Président de la Transition, Chef de l\'État, le Général d\'Armée Assimi Goïta (nommée par décret présidentiel du 11 août 2023) ; présidente du Conseil d\'administration de RobotsMali ; ancienne ministre de l\'Enseignement supérieur et de la Recherche scientifique (2016-2019)\r\nBiographie\r\nNée en 1960 à San, dans la région de Ségou, Assétou Founè Samaké Migan effectue ses études supérieures en ex-URSS, à l\'Université d\'État de Kharkiv, où elle obtient un doctorat en sciences biologiques spécialisé en génétique des plantes et amélioration variétale. Elle complète sa formation par un certificat en biotechnologie agricole au Laboratoire de biotechnologie UNESCO de l\'Université Cheikh-Anta-Diop de Dakar, au Sénégal.\r\nDe retour au Mali, elle mène une riche carrière d\'enseignante-chercheuse : de 1993 à 2000, elle enseigne la physiologie végétale à l\'École Normale Supérieure (ENSup) de Bamako, où elle encadre également des mémoires et des thèses. À partir de 1997, elle devient enseignante-chercheuse puis maître de conférences à la Faculté des sciences et techniques de l\'université des sciences, des techniques et des technologies de Bamako (USTTB), fonction qu\'elle occupe jusqu\'à son entrée au gouvernement.\r\nParallèlement à ses activités académiques, elle met très tôt son expertise au service du développement rural et de la sécurité alimentaire, en collaborant avec de nombreuses organisations nationales et internationales : assistante des programmes à Winrock International (2000-2004), responsable de la méthodologie au Forum social polycentrique de Bamako (2005-2006), cofondatrice et coordinatrice scientifique de l\'Institut de recherche et de promotion des alternatives en développement (IRPAD, 2006-2009), puis fondatrice et responsable de programme à l\'Institut africain de l\'alimentation et du développement durable (2009-2011). Elle représente également l\'Unitarian Service Committee of Canada au Mali, au Sénégal et au Burkina Faso de mai 2011 à juillet 2013, avant de devenir, de novembre 2013 à juin 2014, conseillère technique au ministère de la Réconciliation nationale et du Développement des régions du Nord, dans le contexte de sortie de la crise politico-sécuritaire de 2012-2013. Elle est aussi membre de plusieurs associations et ONG, dont le Forum pour un autre Mali, et secrétaire générale du Centre d\'études et de réflexion au Mali (CERM).\r\nRepérée pour sa rigueur scientifique, elle est nommée conseillère technique au ministère de l\'Enseignement supérieur et de la Recherche scientifique, poste depuis lequel elle est chargée, le 28 décembre 2015, de présenter la leçon inaugurale de la rentrée universitaire devant le président Ibrahim Boubacar Keïta, sur le thème « la recherche scientifique : moteur du développement ». Sa prestation impressionne le chef de l\'État, qui décide de scinder le ministère : le tout nouveau ministère de la Recherche scientifique est créé le 15 janvier 2016 et confié à Assétou Founè Samaké Migan. Sept mois plus tard, le 7 juin 2016, les deux portefeuilles sont réunis et elle devient ministre de l\'Enseignement supérieur et de la Recherche scientifique, fonction qu\'elle exerce jusqu\'en avril 2019. Durant son mandat, elle œuvre notamment à l\'opérationnalisation du Fonds compétitif pour la recherche et l\'innovation technologique (FCRIT), institué en 2011 sous le président Amadou Toumani Touré mais resté inactif jusque-là, dans un contexte national où près de 70 % des financements de la recherche scientifique proviennent alors de bailleurs extérieurs. Elle supervise également, durant cette période, plusieurs sessions d\'examens de fin d\'année scolaire réputées pour leur bon déroulement, sans fuite de sujets.\r\nAprès son départ du gouvernement en 2019, elle poursuit son engagement au service de l\'État : par décret présidentiel signé le 11 août 2023, elle est nommée Conseillère spéciale à la Présidence de la République par le Colonel (alors) Assimi Goïta, Président de la Transition, Chef de l\'État, aux côtés d\'autres personnalités telles que l\'artiste Salif Keïta et d\'anciennes ministres comme Diéminatou Sangaré et Sidibé Dedeou Ousmane. Elle occupe aujourd\'hui, en cette qualité de Conseillère spéciale du Président de la Transition, Chef de l\'État — depuis promu Général d\'Armée en octobre 2024, et dont le mandat a été consolidé par la Charte de la Transition révisée, promulguée le 10 juillet 2025, qui lui octroie un mandat de cinq ans renouvelable sans élection —, une fonction consultative de premier plan auprès de la présidence malienne.\r\nSon expertise scientifique continue par ailleurs d\'être sollicitée en dehors de ses fonctions présidentielles : elle préside, depuis au moins début 2025, le Conseil d\'administration de RobotsMali, centre dédié à la formation et à l\'innovation en robotique et en intelligence artificielle, et intervient encore régulièrement comme conférencière lors de rencontres scientifiques nationales, à l\'image des Journées scientifiques de biologie de l\'USTTB, où elle est présentée comme « ancienne ministre de l\'Enseignement supérieur et de la Recherche scientifique du Mali » et « Conseillère spéciale du Président ».', 15, 0, '2026-07-29 13:29:42'),
(2, 'Diarra', 'Oumou dit Djèma', 'Artiste', 'perso-6abcf85148fe9.jpg', 'Domaine : Médias, journalisme et communication\r\nNaissance : 1965 à Ségou, Mali\r\nDates : Décédée le 26 décembre 2023 à l\'Hôpital du Mali, Bamako, à l\'âge de 58 ans\r\nFormations académiques / Profession : Institut National des Arts (INA) ; diplômée de l\'École Centrale pour l\'Industrie, le Commerce et l\'Administration (ECICA), section Douanes ; animatrice de radio et de télévision, productrice, comédienne\r\nBiographie\r\nNée en 1965 à Ségou, Oumou Diarra, universellement connue sous le pseudonyme de « Djèma » (également orthographié « Dièman » ou « Dièma »), a été l\'une des voix les plus populaires et les plus aimées de la radiodiffusion malienne. Après avoir fréquenté le lycée Sankoré de Bamako, sa scolarité est interrompue par une longue maladie qui la cloue neuf mois à l\'hôpital du Point G, en classe de 11e année, puis l\'oblige à une année entière de convalescence. Ayant pris beaucoup de retard sur ses camarades de promotion, elle se réoriente vers l\'Institut National des Arts (INA), avant d\'obtenir un diplôme de l\'École Centrale pour l\'Industrie, le Commerce et l\'Administration (ECICA), section Douanes. En attendant le concours des douanes, elle fait de petits métiers — vente de marchandises rapportées du Nigeria et de Côte d\'Ivoire, travail en usine de cartons, coiffure et maquillage de mariées.\r\nC\'est par un pur hasard, vers 1993, que sa carrière radiophonique commence : appelée par Michel Sangaré, animateur à la Radio Kayira, pour venir chercher un parent, elle est installée devant un micro et testée sur place. Sa voix — naturelle, grave et imposante — séduit immédiatement Oumar Mariko, le promoteur de la radio, qui lui propose d\'animer l\'émission nocturne « Ginguin Grin », diffusée à partir de minuit, en attendant le concours des douanes. Ses parents ignorent longtemps sa nouvelle activité ; sa mère, en particulier, se montre d\'abord réticente à ce choix de carrière. Elle poursuit ensuite son parcours à la radio Tabalé, avant de rejoindre, au début des années 2000, la Chaîne 2 de l\'Office de Radiodiffusion Télévision du Mali (ORTM), où elle devient, durant près de trois décennies, l\'une des animatrices vedettes les plus populaires du pays.\r\nGrâce à son ton chaleureux, sa proximité avec les réalités locales et son sens aigu de l\'écoute, ses émissions consacrées à la vie quotidienne, à la solidarité, à la condition féminine et aux conseils familiaux étaient suivies par des milliers d\'auditeurs à travers le pays. Elle acquiert une notoriété particulière avec « Guakounda », un théâtre radiophonique consacré à la famille qu\'elle anime aux côtés de sa complice Lala Drado, dite « Fiman » — un duo si populaire que l\'émission est couramment surnommée « Fiman ni Dièman ». Son émission phare, « 20 sur 20 », consacrée aux problèmes de couple et de vie familiale, rassemblait chaque soir des milliers de femmes à l\'écoute. Elle anime également « Yélen » (« la lumière »), en langue bamanan, aux côtés de l\'animateur Bouréma Kané. Comédienne à l\'occasion, elle est également reconnue comme l\'interprète du tout premier « woman show » au Mali, et se plaisait à raconter qu\'elle comptait pas moins de 25 homonymes à travers le pays, sans compter ceux de sa propre famille.\r\nAffaiblie par une longue maladie depuis son hospitalisation de juin 2016 — qui avait alors suscité de nombreuses rumeurs infondées sur son décès — elle continue néanmoins d\'animer ses émissions avec passion et professionnalisme malgré une santé fragile, recevant au passage la visite de nombreuses personnalités, dont l\'ancien Premier ministre Modibo Sidibé. Son décès, survenu le 26 décembre 2023 à l\'Hôpital du Mali de Bamako, a suscité une vive émotion et un deuil profond dans le monde des médias et de la culture au Mali — endeuillé, la même semaine, par la disparition de deux autres figures publiques maliennes. De nombreuses personnalités et une multitude d\'auditeurs anonymes lui ont rendu hommage, saluant en elle une « conseillère hors pair » et une « grande voix de la radio » qui a marqué durablement le paysage médiatique malien.', 17, 6, '2026-08-03 11:28:15'),
(3, 'KEÏTA', 'Daba Modibo', NULL, 'perso-6abcf7990dcd9.jpg', 'Domaine : Sport (Taekwondo)\r\nDate de naissance : 5 avril 1981 à Abidjan (Côte d\'Ivoire)\r\nProfession : Taekwondoïste de haut niveau, ancien champion du monde, dirigeant sportif\r\nStatut : Vice-président de la Fédération malienne de Taekwondo ; figure historique du sport malien et africain ; fondateur d\'une association de promotion de la jeunesse\r\nBiographie\r\nNé le 5 avril 1981 à Abidjan, en Côte d\'Ivoire, de parents maliens, Daba Modibo Keïta porte un prénom qui n\'est pas anodin : il est nommé en hommage à Modibo Keïta, premier président de la République du Mali. Sa famille et lui sont contraints de fuir la Côte d\'Ivoire en 2000, au moment de la vague de xénophobie qui précède la première guerre civile ivoirienne, et s\'installent alors au Mali, où le jeune homme découvre véritablement le taekwondo. Ses débuts sur la scène compétitive remontent toutefois à 1996-1997, à l\'occasion des championnats ouest-africains d\'Abidjan puis de Bamako, où il décroche des médailles d\'argent. Il devient ensuite médaillé de bronze au championnat ouest-africain d\'Accra, avant de s\'imposer comme champion du Mali en 2002 puis en 2004, confirmant sa progression par plusieurs titres remportés dans des tournois internationaux Open, à Paris, Nantes et en Picardie.\r\nLes débuts difficiles de sa carrière internationale sont marqués par un manque criant de moyens : faute de financement suffisant pour s\'entraîner, il bénéficie d\'une bourse de solidarité olympique du Comité international olympique (CIO), qui lui permet de s\'entraîner aux États-Unis auprès du combattant ivoirien Patrice Rémarck, puis sous la direction du technicien Jorge F. Ramos. Lors des Championnats du monde de 2007 à Pékin, faute de moyens suffisants, il doit encore loger chez des amis et s\'entraîner dans la cour d\'un hôtel. C\'est pourtant dans ces conditions précaires qu\'il réalise l\'exploit qui va faire basculer sa carrière : avec son gabarit impressionnant (2,05 m pour environ 105 kg), il devient le premier Africain sacré champion du monde de taekwondo, en remportant la médaille d\'or de la catégorie des plus de 84 kg face à l\'Iranien Rostami Morteza. Il confirme cet exploit deux ans plus tard, à Copenhague en 2009, en conservant son titre dans la catégorie des plus de 87 kg face au Sud-Coréen Yun-Bae Nam — devenant ainsi le premier et, à ce jour, le seul Africain double champion du monde des poids lourds en taekwondo.\r\nSurnommé « le Gladiateur », Daba Modibo Keïta porte également à deux reprises les couleurs du Mali aux Jeux Olympiques : à Pékin en 2008, où il est le porte-drapeau de la délégation malienne, puis à Londres en 2012, où il s\'incline aux portes du podium malgré plusieurs blessures qui ont émaillé sa préparation. Géré par son frère aîné Badra, il a vécu et s\'est entraîné tour à tour en France et aux États-Unis ; le taekwondo est resté une histoire de famille, puisque deux de ses frères et deux de ses cinq sœurs pratiquent également la discipline, ses sœurs ayant atteint le niveau de ceinture bleue.\r\nMembre du collectif des « Champions de la Paix » de l\'organisation internationale Peace and Sport, qui rassemble plus d\'une centaine de sportifs de haut niveau engagés personnellement en faveur de la paix par le sport, il s\'est également illustré par un engagement fort pour la jeunesse malienne : il fonde en 2008 l\'Association Daba Modibo Keïta (ADMK) à Bamako, structure dédiée à la promotion des valeurs civiques, de l\'éducation et de la pratique sportive chez les jeunes. Devenu une figure de référence du sport malien, il occupe aujourd\'hui la fonction de vice-président de la Fédération malienne de Taekwondo, où il continue de transmettre son expérience aux jeunes générations de combattants, tout en intervenant régulièrement, y compris à l\'étranger, pour la formation de sportifs d\'autres pays de la sous-région. Interrogé sur son parcours, il aime rappeler que son sacre mondial « dépasse le Mali » et « appartient à toute l\'Afrique ».', 18, 2, '2026-09-30 01:58:31'),
(4, 'COULIBALY', 'Tidiane', NULL, 'perso-6abcf8af64b52.jpeg', 'Domaine : Éducation citoyenne, civisme et mouvements de jeunesse\r\nStatut : Commissaire général de l\'Association des Pionniers du Mali (APM), cadre émérite, également connu sous le surnom de « Necker »\r\nBiographie\r\nTidiane Coulibaly, dit « Necker », est aujourd\'hui le Commissaire général de l\'Association des Pionniers du Mali (APM), héritière du Mouvement des Jeunes Pionniers créé au tout début des années 1960 sous l\'impulsion du parti de Modibo Keïta, l\'Union soudanaise - Rassemblement démocratique africain (US-RDA), et voué depuis lors à la formation civique et patriotique des jeunes Maliens. À la tête de cette structure reconnue d\'utilité publique, il en est aujourd\'hui l\'un des principaux porte-voix, s\'attachant à faire vivre, plus de soixante ans après sa création, l\'héritage de ce mouvement de jeunesse emblématique de l\'histoire postindépendance du Mali.\r\nHomme de terrain et pédagogue engagé, il consacre son action à la refondation de la citoyenneté à travers tout le pays et s\'illustre par ses prises de parole régulières lors des grands rassemblements et cérémonies nationales. Il explique volontiers la symbolique de l\'emblème du mouvement — le foulard du pionnier, dont les trois pans représentent la famille, l\'école et la rue, les trois milieux qui façonnent le citoyen, et dont le rouge et l\'or rappellent respectivement le sang et la richesse du Mali. Il a ainsi plaidé, lors d\'une visite du ministre de la Refondation de l\'État en 2021, pour le retour de l\'éducation pionnière au sein du système scolaire national, estimant qu\'« il faut former l\'homme malien pour devenir un bon citoyen et un bon patriote » avant d\'être un savant.\r\nIl s\'est également illustré par des prises de position engagées en matière de civisme environnemental, notamment lors de la Journée mondiale de l\'environnement, où il a appelé les Maliens à un changement profond de comportement individuel et collectif face à la dégradation de l\'environnement. Figure respectée du monde associatif, il a rendu à plusieurs reprises hommage à d\'anciennes figures historiques du mouvement pionnier, comme lors de l\'exposition consacrée en 2017 à Bakary Koniba Traoré, dit « Bakary Pionnier », qu\'il a salué comme « une légende et une fierté maliennes ».\r\nSous son impulsion, l\'Association des Pionniers du Mali a organisé, en septembre 2023 au Palais des Pionniers de Dianéguéla, le lancement du camp national des pionniers baptisé « Camp Assimi Goïta » — le premier grand camp de formation national de l\'association depuis le Camp national Maïmouna Ba de 2002, soit plus de vingt ans auparavant. À cette occasion, il a souligné les difficultés persistantes de financement de l\'association et plaidé pour le déblocage effectif de la subvention prévue par le décret de reconnaissance d\'utilité publique de l\'APM. Son action s\'inscrit pleinement dans la dynamique de refondation de la citoyenneté portée par les autorités de la Transition malienne, avec pour objectif de structurer durablement les espaces d\'encadrement de la jeunesse, de réintroduire l\'éducation civique dans le système scolaire et de prémunir les jeunes générations contre la délinquance.', 4, 3, '2026-09-30 01:58:31'),
(5, 'KOÏTA', 'Sory Ibrahim, dit « Chef Bomba »', NULL, 'perso-6abcf8c9cbfde.jpg', 'Domaine : Mouvements de jeunesse, civisme et culture\r\nStatut : Figure historique de l\'Association des Pionniers du Mali (APM)\r\nBiographie\r\nSory Ibrahim Koïta, connu sous le surnom affectueux de « Chef Bomba », est l\'une des figures marquantes de l\'histoire du Mouvement Pionnier au Mali. Il a consacré une grande partie de sa vie à l\'encadrement, à l\'éducation et à la formation civique de la jeunesse malienne, au sein de l\'Association des Pionniers du Mali (APM), héritière du Mouvement des Jeunes Pionniers créé au tout début des années 1960 par le parti unique de Modibo Keïta, l\'Union soudanaise - Rassemblement démocratique africain (US-RDA), au lendemain de l\'indépendance du Mali.\r\nÀ travers son action d\'encadrement de terrain, il a contribué à transmettre à plusieurs générations de jeunes Maliens les valeurs fondatrices du mouvement pionnier : patriotisme, solidarité nationale, rigueur et dévouement à la patrie, incarnées notamment par le foulard tricolore porté par chaque pionnier. Son engagement s\'inscrit dans la longue histoire sociale et culturelle du Mali postindépendance, marquée par la volonté de structurer l\'encadrement de la jeunesse autour de valeurs civiques fortes, dans la continuité d\'autres figures historiques du mouvement, à l\'image de Bakary Koniba Traoré, dit « Bakary Pionnier ».\r\nLe Mali a rendu un hommage solennel à son héritage éducatif à l\'occasion de l\'inauguration, le 10 mars 2026, du nouveau Palais des Pionniers de Bamako, à Dianéguéla (Magnambougou, Commune VI) — un vaste complexe éducatif et événementiel de trois hectares comprenant salles de conférences, de spectacles et de réunions, espaces informatiques, résidence, centre d\'accueil et installations sportives, inauguré par le Premier ministre le Général de Division Abdoulaye Maïga, au nom du Président de la Transition, le Général d\'Armée Assimi Goïta. Les différents espaces de ce complexe portent les noms de personnalités s\'étant distinguées par leur engagement civique, social ou sportif, en reconnaissance de leur contribution durable à la construction citoyenne de la jeunesse malienne — une manière pour les autorités de perpétuer, à travers l\'architecture même du lieu, la mémoire des artisans historiques du mouvement pionnier comme Sory Ibrahim Koïta.\r\nLes informations biographiques publiques disponibles sur les dates précises de naissance et le parcours détaillé de Sory Ibrahim Koïta demeurent limitées ; elles pourront être complétées si des sources supplémentaires, notamment issues des archives de l\'Association des Pionniers du Mali, deviennent accessibles.', 2, 6, '2026-09-30 01:58:31'),
(6, 'DIALLO', 'Abdoulaye Ely', NULL, NULL, 'Domaine : Secteur privé, immobilier et économie\r\nProfession / Statut : Opérateur économique et promoteur immobilier ; cadre du mouvement Scouts et Guides du Mali\r\nBiographie\r\nAbdoulaye Ely Diallo est un acteur du secteur privé malien, actif dans le développement immobilier et urbain à Bamako. Il s\'inscrit dans cette génération d\'entrepreneurs locaux qui, portés par la croissance démographique rapide de la capitale malienne et la demande grandissante en logements modernes, investissent massivement dans la construction résidentielle pour accompagner l\'expansion urbaine de Bamako.\r\nIl est notamment connu comme le promoteur et le fondateur de la Résidence Abdoulaye Ely Diallo, un ensemble résidentiel qui porte son nom et qui est situé sur la Corniche de Magnambougou, un quartier de la Commune VI de Bamako en bordure du fleuve Niger. Ce secteur de la Corniche de Magnambougou connaît, ces dernières années, un développement immobilier soutenu, porté par plusieurs opérateurs privés qui y construisent appartements, duplex et villas de standing. Par cette activité de promotion immobilière, Abdoulaye Ely Diallo participe à la création d\'emplois locaux, dans les métiers du bâtiment comme dans la gestion locative, et contribue au dynamisme du secteur privé de la construction au Mali ainsi qu\'à la transformation du paysage urbain le long du fleuve Niger.\r\nPar ailleurs, Abdoulaye Ely Diallo est également connu comme cadre du mouvement Scouts et Guides du Mali, organisation de jeunesse à laquelle il apporte son soutien, illustrant un engagement qui dépasse le seul cadre économique pour toucher à l\'encadrement citoyen et éducatif des jeunes.\r\nLes données publiques disponibles concernant sa date de naissance, son parcours de formation précis et l\'ensemble de ses activités professionnelles restent limitées ; cette biographie reflète les éléments vérifiables à ce jour et pourra être enrichie si des informations complémentaires deviennent disponibles.', 8, 5, '2026-09-30 01:58:31'),
(7, 'KOUYATÉ', 'Seydou Badian', NULL, 'perso-6abcf87049460.jpg', 'Domaine : Littérature, politique et médecine\r\nDates : 10 avril 1928 (Bamako) – 28 décembre 2018 (Bamako), à l\'âge de 90 ans\r\nFormations académiques : Études de médecine à l\'Université de Montpellier (France) ; auteur d\'une thèse sur les traitements traditionnels africains de la fièvre jaune\r\nProfession / Statut : Médecin de circonscription, homme d\'État (ministre), écrivain, poète et militant politique du parti US-RDA / UM-RDA\r\nBiographie\r\nNé le 10 avril 1928 à Bamako, alors capitale du Soudan français, Seydou Badian Kouyaté figure parmi les plus grandes figures intellectuelles et politiques de l\'histoire du Mali indépendant. Poète talentueux dès sa jeunesse, il part étudier la médecine à l\'Université de Montpellier, en France, où il rédige une thèse consacrée aux traitements traditionnels africains de la fièvre jaune. Il rentre au Mali en 1956 et est nommé médecin de circonscription, avant de s\'engager résolument, aux côtés de Modibo Keïta, dans la lutte pour l\'indépendance du pays. Nationaliste convaincu et panafricaniste, proche du premier président du Mali, il est l\'auteur des paroles de l\'hymne national malien, « Pour l\'Afrique et pour toi, Mali », ainsi que de l\'hymne du mouvement des pionniers.\r\nDès l\'indépendance du pays en 1960, il est nommé ministre de l\'Économie rurale et du Plan. Lors du remaniement ministériel du 17 septembre 1962, il devient ministre du Développement, chargé de la Coordination économique et financière et du Plan, contribuant activement à la définition des grandes orientations économiques et sociales du jeune État malien, notamment dans le cadre du premier plan quinquennal (1961-1965). Militant convaincu du parti unique comme instrument de construction nationale dans l\'Afrique post-coloniale, il devient l\'un des idéologues marquants de l\'Union soudanaise - Rassemblement démocratique africain (US-RDA), le parti de Modibo Keïta. Le coup d\'État militaire du 19 novembre 1968, mené par le lieutenant Moussa Traoré et qui renverse Modibo Keïta, marque un tournant douloureux dans son parcours : il est déporté et détenu à la prison de Kidal, dans le Nord-Est du Mali, avant de s\'exiler durant de nombreuses années à Dakar, au Sénégal, où il est accueilli sur l\'invitation du président-poète Léopold Sédar Senghor. Il y passera une partie importante de sa vie avant de regagner définitivement Bamako.\r\nDe retour dans son pays, il reste une figure politique respectée et écoutée : en 1997, il se présente à l\'élection présidentielle, avant de retirer sa candidature, comme la plupart des autres opposants au président sortant Alpha Oumar Konaré, pour protester contre la mauvaise organisation du scrutin. Il est par ailleurs radié puis réintégré au sein de l\'US-RDA (devenue UM-RDA), dont il demeure l\'une des figures morales jusqu\'à la fin de sa vie, fréquemment consulté sur les grands dossiers et défis de la République malienne.\r\nSur le plan littéraire, Seydou Badian Kouyaté laisse une œuvre majeure et durable dans le paysage des lettres africaines, publiée entre 1957 et 2007. Son roman le plus célèbre, Sous l\'orage (suivi de La Mort de Chaka), publié dès 1957, avant même l\'indépendance du Mali, aborde le conflit des générations et le choc entre traditions africaines et modernité coloniale ; il reste, aujourd\'hui encore, inscrit aux programmes scolaires de nombreux pays francophones et continue de bercer des générations d\'écoliers africains. Il est également l\'auteur d\'essais et de récits marquants, parmi lesquels Les dirigeants africains face à leur peuple (1964-1965), qui lui vaut le prestigieux Grand Prix littéraire d\'Afrique noire en 1965, ainsi que Le sang des masques (1976) et Noces sacrées (1977). En octobre 2007, il publie encore un roman, La Saison des pièges. En 2009, il choisit de changer officiellement de nom pour devenir Seydou Badian Noumboïna, du nom d\'un village du cercle de Macina. En 2017, l\'ensemble de sa production bibliographique est couronné par le Grand Prix des Mécènes, décerné lors des Grands Prix des associations littéraires (GPAL).\r\nSeydou Badian Kouyaté s\'éteint à Bamako dans la nuit du 28 au 29 décembre 2018, à l\'âge de 90 ans. Sa disparition suscite un deuil national : des funérailles officielles se tiennent le 3 janvier 2019 sur le boulevard de l\'Indépendance à Bamako, en présence de nombreuses personnalités maliennes, de délégations étrangères venues du Congo-Brazzaville et du Sénégal, ainsi que du corps diplomatique accrédité — témoignage de la dimension panafricaniste de l\'homme, dont l\'héritage politique et littéraire continue, aujourd\'hui encore, d\'inspirer les générations maliennes.', 1, 5, '2026-09-30 01:58:31'),
(8, 'Samassékou', 'Adama', NULL, 'perso-6abcf86469aac.jpg', 'Adama Samassékou, une vie au service du Mali, de l’éducation et de la pensée africaine\r\nBAMAKO — Homme politique, intellectuel, linguiste et défenseur infatigable des langues africaines, Adama Samassékou aura marqué la vie publique malienne bien au-delà des fonctions ministérielles qu’il a occupées. De son engagement dans la lutte démocratique à ses responsabilités internationales, son parcours aura été placé sous le signe de l’éducation, de la culture, de la démocratie et de l’unité africaine.\r\nNé en 1946, Adama Samassékou appartient à une génération d’intellectuels maliens profondément engagés dans les transformations politiques et sociales de leur pays. Formé à l’Université d’État Lomonossov de Moscou, où il obtient un diplôme en philologie et linguistique, il poursuit ensuite ses études en France, notamment à la Sorbonne et à l’Université Paris-Dauphine. Cette solide formation universitaire va contribuer à forger le profil d\'un homme à la croisée de la politique, des sciences humaines et de la culture.\r\nDe l\'engagement clandestin à la démocratie\r\nAvant de connaître les responsabilités gouvernementales, Adama Samassékou s\'engage dans la contestation du régime de Moussa Traoré. Dans les années 1970, alors que le Mali est dirigé par un régime militaire, il fait partie des intellectuels arrêtés pour leur engagement politique.\r\nCette expérience marque durablement son parcours. Lorsque le Mali s\'engage sur la voie de la démocratie au début des années 1990, Samassékou devient naturellement l\'un des acteurs de cette nouvelle page de l\'histoire politique nationale.\r\nIl participe à la structuration de l\'Alliance pour la démocratie au Mali – Parti africain pour la solidarité et la justice (ADEMA-PASJ) et devient notamment président fondateur de la section ADEMA-France.\r\nL\'arrivée au pouvoir d\'Alpha Oumar Konaré ouvre alors une nouvelle étape de sa carrière.\r\nSept années au ministère de l\'Éducation\r\nEn 1993, Adama Samassékou entre au gouvernement et prend les commandes du ministère de l\'Éducation. Il conservera ce portefeuille pendant près de sept ans, jusqu\'en 2000.\r\nSon profil de linguiste et de spécialiste des sciences humaines donne une orientation particulière à son action. L\'éducation, la formation, l\'alphabétisation et la valorisation des langues nationales occupent une place importante dans sa vision du développement du Mali.\r\nDe 1997 à 2000, il devient également porte-parole du gouvernement. Il s\'impose alors comme l\'une des principales voix publiques du pouvoir d\'Alpha Oumar Konaré, défendant et expliquant les politiques gouvernementales dans une période où le Mali cherche à consolider son expérience démocratique.\r\nDes responsabilités nationales à une ambition africaine\r\nAprès son départ du gouvernement, Samassékou ne quitte pas pour autant la vie publique. Son engagement se déplace progressivement vers un domaine qui deviendra l\'un des grands combats de son existence : la défense et la promotion des langues africaines.\r\nIl joue un rôle déterminant dans la création de l\'Académie africaine des langues (ACALAN). Son ambition est claire : faire des langues africaines des instruments de transmission du savoir, de développement, d\'éducation et d\'intégration du continent.\r\nPour lui, la question linguistique n\'est pas simplement culturelle. Elle touche directement à l\'identité et à la souveraineté des peuples africains.\r\nCette conviction l\'amène à défendre une Afrique capable de moderniser ses sociétés sans renoncer à ses langues, à ses cultures et à ses systèmes de pensée.\r\nUne voix malienne dans les grandes instances internationales\r\nLa dimension internationale de son parcours va progressivement prendre de l\'ampleur.\r\nEn 2002, Adama Samassékou est élu président du Comité préparatoire de la phase de Genève du Sommet mondial sur la société de l\'information (SMSI). Il se retrouve ainsi au cœur des discussions internationales consacrées à l\'avenir du numérique et de la société de l\'information.\r\nCette responsabilité illustre l\'étendue de son parcours : le ministre malien de l\'Éducation devient désormais l\'une des personnalités africaines intervenant dans les grands débats mondiaux sur les technologies, la connaissance et la diversité culturelle.\r\nIl défend notamment la nécessité de préserver la diversité linguistique dans l\'espace numérique, estimant que l\'Afrique ne devait pas entrer dans la société de l\'information en abandonnant ses propres langues.\r\nUn intellectuel panafricain\r\nAu fil des années, Adama Samassékou s\'impose comme une figure du panafricanisme intellectuel.\r\nSes responsabilités au sein de l\'Académie africaine des langues, de réseaux consacrés à la diversité linguistique, de la Francophonie et d\'organisations internationales de sciences humaines témoignent d\'une conception du panafricanisme qui dépasse les seuls enjeux politiques.\r\nSon combat porte également sur la transmission des savoirs, la reconnaissance des cultures africaines et la capacité des sociétés du continent à produire leur propre pensée.\r\nChez lui, la langue devient ainsi un instrument de souveraineté.\r\nUn retour au cœur des préoccupations nationales\r\nMême après ses grandes responsabilités internationales, Samassékou reste attentif à la situation de son pays.\r\nIl continue d\'intervenir dans la vie publique malienne et demeure une figure historique de l\'ADEMA. Il exerce également des responsabilités de conseil auprès des autorités maliennes.\r\nEn 2024, alors que le Mali traverse une période particulièrement délicate de son histoire politique, son expérience est de nouveau sollicitée.\r\nIl est désigné porte-parole du Comité de pilotage du Dialogue inter-Maliens pour la paix et la réconciliation nationale.\r\nCe choix apparaît comme le prolongement naturel d\'une vie consacrée au dialogue, à l\'éducation et à la construction d\'une société malienne plus consciente de son histoire et de ses identités.\r\nMais il n\'aura pas le temps d\'achever cette dernière mission.\r\nLa disparition d\'une figure de la vie publique malienne\r\nAdama Samassékou s\'éteint à Bamako le 23 février 2024, à l\'âge de 77 ans.\r\nSa disparition suscite de nombreux hommages au Mali et au sein des milieux intellectuels africains et internationaux.\r\nIl laisse derrière lui l\'image d\'un homme dont le parcours aura traversé plusieurs époques du Mali contemporain : la période de la dictature militaire, la lutte démocratique, les années de construction institutionnelle, l\'ouverture internationale et les nouvelles interrogations autour de l\'avenir du pays.\r\nUn héritage qui dépasse la politique\r\nAdama Samassékou aura été ministre, porte-parole du gouvernement, militant politique, linguiste, diplomate intellectuel et défenseur des langues africaines.\r\nMais réduire son parcours à ses fonctions officielles serait probablement passer à côté de l\'essentiel.\r\nSon véritable héritage réside dans une conviction qui aura accompagné toute son existence : l\'Afrique peut se moderniser sans renoncer à ce qui constitue son identité.\r\nL\'éducation, la culture, les langues, la démocratie et la transmission du savoir auront ainsi constitué les différents visages d\'un même combat.\r\nAu Mali, son nom demeure aujourd\'hui associé à cette vision. Et le fait qu\'une salle du Palais des Pionniers porte son nom constitue, à sa manière, un rappel de cette philosophie : former la jeunesse, transmettre le savoir et préparer les générations futures à prendre leur place dans la construction du pays.\r\nAdama Samassékou n\'aura donc pas seulement servi l\'État malien. Il aura consacré une grande partie de sa vie à une ambition plus vaste : contribuer à faire de la connaissance, de la culture et de l\'identité africaine des instruments de développement et de souveraineté.\r\n— Fin du document —', 14, 7, '2026-09-30 01:58:32');

-- --------------------------------------------------------

--
-- Structure de la table `personnel`
--

CREATE TABLE `personnel` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `prenom` varchar(150) DEFAULT NULL,
  `poste` varchar(200) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `ordre` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Membres du personnel du Palais, affichés en carrousel sur À propos';

--
-- Déchargement des données de la table `personnel`
--

INSERT INTO `personnel` (`id`, `nom`, `prenom`, `poste`, `photo`, `ordre`, `actif`) VALUES
(2, 'Haïdara', 'Nouhoum Chérif', 'Directeur Géneral Adjoint', 'personnel-6abcf211abf0d.jpeg', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `reductions_accordees`
--

CREATE TABLE `reductions_accordees` (
  `id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `montant_reduction` decimal(12,2) UNSIGNED NOT NULL COMMENT 'Montant de la réduction en F CFA',
  `pourcentage` decimal(5,2) UNSIGNED DEFAULT NULL COMMENT 'Pourcentage saisi, à titre indicatif',
  `motif` text DEFAULT NULL,
  `autorise_par` varchar(150) DEFAULT NULL COMMENT 'Autorité ayant accordé la réduction (DG, Ministre...), si différente du comptable',
  `reference_accord` varchar(150) DEFAULT NULL COMMENT 'Référence de la note / lettre d''accord',
  `statut` enum('appliquee','non_appliquee','annulee') NOT NULL DEFAULT 'appliquee',
  `origine` enum('commerciale','requisition') NOT NULL DEFAULT 'commerciale' COMMENT 'commerciale = réduction accordée au guichet ; requisition = prise en charge du surcoût imposé par une réquisition',
  `motif_statut` text DEFAULT NULL COMMENT 'Raison du passage en non_appliquee / annulee',
  `reportee_de` int(10) UNSIGNED DEFAULT NULL COMMENT 'Réduction d''origine reportée (réquisition → nouvelle réservation)',
  `saisi_par` int(10) UNSIGNED NOT NULL,
  `statut_modifie_par` int(10) UNSIGNED DEFAULT NULL,
  `statut_modifie_le` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reservation_appliquee` int(10) UNSIGNED GENERATED ALWAYS AS (if(`statut` = 'appliquee',`reservation_id`,NULL)) STORED COMMENT 'Technique : garantit une seule réduction appliquée par réservation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Réductions accordées au guichet (distinctes des paiements)';

-- --------------------------------------------------------

--
-- Structure de la table `remboursements`
--

CREATE TABLE `remboursements` (
  `id` int(10) UNSIGNED NOT NULL,
  `operation_id` int(10) UNSIGNED NOT NULL,
  `requisition_id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `montant_paye` decimal(12,2) UNSIGNED NOT NULL,
  `montant_a_rembourser` decimal(12,2) UNSIGNED NOT NULL,
  `montant_rembourse` decimal(12,2) UNSIGNED DEFAULT NULL,
  `motif` text NOT NULL,
  `mode` enum('especes','orange_money','moov_money','virement','cheque') DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL,
  `date_demande` datetime NOT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `resultat` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Remboursements liés aux opérations de réquisition';

--
-- Déchargement des données de la table `remboursements`
--

INSERT INTO `remboursements` (`id`, `operation_id`, `requisition_id`, `reservation_id`, `client_id`, `montant_paye`, `montant_a_rembourser`, `montant_rembourse`, `motif`, `mode`, `reference`, `justificatif`, `date_demande`, `date_traitement`, `traite_par`, `resultat`, `note`, `created_at`, `updated_at`) VALUES
(1, 5, 8, 20, 8, 150000.00, 150000.00, 150000.00, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 'especes', '02', NULL, '2026-09-28 20:25:30', '2026-09-28 20:25:30', 7, 'effectue', NULL, '2026-09-28 20:25:30', NULL),
(2, 8, 12, 24, 8, 150000.00, 150000.00, 150000.00, 'Remboursement suite à réquisition ministérielle', 'especes', '02', NULL, '2026-09-29 15:51:37', '2026-09-29 15:51:37', 7, 'effectue', '', '2026-09-29 15:51:37', NULL),
(3, 10, 14, 29, 8, 400000.00, 400000.00, 400000.00, 'Remboursement suite à réquisition ministérielle', 'especes', '02', NULL, '2026-09-30 13:34:23', '2026-09-30 13:34:23', 7, 'effectue', '', '2026-09-30 13:34:23', NULL),
(4, 11, 15, 30, 8, 150000.00, 150000.00, 150000.00, 'Remboursement suite à réquisition ministérielle', 'especes', '', NULL, '2026-09-30 13:57:39', '2026-09-30 13:57:39', 7, 'effectue', '', '2026-09-30 13:57:39', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `requisitions_ministerielles`
--

CREATE TABLE `requisitions_ministerielles` (
  `id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `motif` text NOT NULL,
  `declenche_par` int(10) UNSIGNED NOT NULL,
  `date_declenchee` timestamp NOT NULL DEFAULT current_timestamp(),
  `choix_client` enum('annulation','remboursement','changement_espace','nouvelle_date','autre_espace') DEFAULT NULL COMMENT 'Choix du client ; autre_espace est conservé pour compatibilité historique',
  `details_choix` text DEFAULT NULL COMMENT 'Précisions du client selon son choix (date souhaitée, espace souhaité...)',
  `date_choix` timestamp NULL DEFAULT NULL,
  `statut` enum('en_attente_choix','choix_recu','en_traitement','cloturee','annulee','traite') NOT NULL DEFAULT 'en_attente_choix' COMMENT 'traite est conservé uniquement pour les anciennes réquisitions',
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `note_traitement` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Réquisitions ministérielles/institutionnelles d''un espace déjà réservé';

--
-- Déchargement des données de la table `requisitions_ministerielles`
--

INSERT INTO `requisitions_ministerielles` (`id`, `reservation_id`, `motif`, `declenche_par`, `date_declenchee`, `choix_client`, `details_choix`, `date_choix`, `statut`, `traite_par`, `date_traitement`, `note_traitement`) VALUES
(1, 9, 'Activité de l\'UNICEF', 7, '2026-08-10 13:14:22', 'autre_espace', NULL, '2026-08-10 14:52:54', 'en_attente_choix', NULL, NULL, NULL),
(2, 11, 'SSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSS', 7, '2026-08-10 16:06:18', 'remboursement', NULL, '2026-08-10 16:06:58', 'en_attente_choix', NULL, NULL, NULL),
(3, 13, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', 7, '2026-08-10 23:37:31', 'remboursement', NULL, '2026-08-10 23:37:58', 'traite', 7, '2026-08-12 21:24:29', NULL),
(4, 16, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-26 22:07:52', 'nouvelle_date', '10', '2026-09-26 22:08:26', 'en_traitement', 7, NULL, NULL),
(5, 17, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 00:36:08', 'nouvelle_date', '2026-10-10', '2026-09-28 00:36:53', 'en_traitement', 7, NULL, NULL),
(6, 18, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', 7, '2026-09-28 14:49:09', 'autre_espace', 'Salle Adama SAMASSEKOU', '2026-09-28 14:50:50', 'cloturee', 7, '2026-09-28 15:51:52', 'Demande de changement d’espace enregistrée et traitée.'),
(7, 19, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 16:07:22', 'nouvelle_date', '2026-09-30', '2026-09-28 16:08:44', 'cloturee', 7, '2026-09-28 16:09:23', 'Demande de nouvelle date enregistrée et traitée.'),
(8, 20, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 18:18:23', 'remboursement', NULL, '2026-09-28 18:19:40', 'cloturee', 7, '2026-09-28 20:25:30', 'Remboursement effectué par Espèces — montant : 150 000 FCFA — Réf. : 02'),
(9, 21, 'Cet espace a été réquisitionné en raison d\'une urgence nationale.', 7, '2026-09-29 07:48:21', 'nouvelle_date', '2026-10-07', '2026-09-29 07:58:30', 'en_traitement', 7, NULL, NULL),
(10, 12, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', 7, '2026-09-29 13:21:41', NULL, NULL, NULL, 'en_attente_choix', NULL, NULL, NULL),
(11, 22, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-29 15:42:17', 'nouvelle_date', '2026-10-01', '2026-09-29 15:46:24', 'cloturee', 7, '2026-09-29 15:48:21', ''),
(12, 24, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-29 15:50:15', 'remboursement', NULL, '2026-09-29 15:50:33', 'cloturee', 7, '2026-09-29 15:51:37', ''),
(13, 27, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-30 12:15:34', 'nouvelle_date', '2026-10-03', '2026-09-30 12:16:58', 'en_traitement', 7, NULL, NULL),
(14, 29, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-30 13:32:59', 'remboursement', NULL, '2026-09-30 13:33:27', 'cloturee', 7, '2026-09-30 13:34:23', ''),
(15, 30, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-30 13:56:05', 'remboursement', NULL, '2026-09-30 13:56:39', 'cloturee', 7, '2026-09-30 13:57:39', '');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `partenaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Partenaire au moment de la réservation (NULL = réservation classique)',
  `espace_id` int(10) UNSIGNED NOT NULL,
  `tarif_id` int(10) UNSIGNED DEFAULT NULL,
  `date_resa` date NOT NULL COMMENT 'Date du créneau, ou date d''arrivée pour un séjour',
  `date_depart` date DEFAULT NULL COMMENT 'Date de départ — uniquement pour les espaces en mode séjour (nuitées)',
  `date_validation` datetime DEFAULT NULL COMMENT 'Horodatage de la validation par admin_espaces — sert au calcul du délai de 48h avant expiration automatique',
  `heure_debut` time DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  `heure_fin` time DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  `petit_dejeuner` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Option petit-déjeuner (hébergement uniquement)',
  `vip` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Option accueil VIP (supplément, espaces qui le proposent)',
  `canal` enum('en_ligne','guichet') NOT NULL DEFAULT 'en_ligne' COMMENT 'Réservation faite en ligne par le client, ou saisie directement au guichet par un admin',
  `requisition_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Réquisition à l''origine de cette réservation, si applicable',
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nombre d''unités réservées',
  `montant_initial` decimal(12,2) UNSIGNED DEFAULT NULL COMMENT 'Tarif normal avant toute réduction, figé à la validation (NULL = anciennes réservations, recalculé depuis le tarif)',
  `statut` enum('en_attente','validee','refusee','annulee','expiree','requisitionnee') NOT NULL DEFAULT 'en_attente',
  `statut_paiement` enum('non_paye','attente_paiement','partiellement_paye','paye') NOT NULL DEFAULT 'non_paye',
  `date_limite_solde` date DEFAULT NULL COMMENT 'Date limite pour régler le solde d''un acompte',
  `paiement_notifie` tinyint(1) NOT NULL DEFAULT 0,
  `motif` text DEFAULT NULL,
  `note_admin` text DEFAULT NULL,
  `notification_vue` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Demandes de réservation, avec détection de conflit sur les créneaux';

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `partenaire_id`, `espace_id`, `tarif_id`, `date_resa`, `date_depart`, `date_validation`, `heure_debut`, `heure_fin`, `petit_dejeuner`, `vip`, `canal`, `requisition_id`, `quantite`, `montant_initial`, `statut`, `statut_paiement`, `date_limite_solde`, `paiement_notifie`, `motif`, `note_admin`, `notification_vue`, `created_at`, `updated_at`) VALUES
(1, 8, NULL, 1, 1, '2026-07-28', NULL, '2026-07-27 16:19:47', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'grand debat interuniversitaire', NULL, 0, '2026-07-27 15:58:32', '2026-07-27 23:35:53'),
(2, 8, NULL, 8, 27, '2026-07-28', '2026-07-31', '2026-07-27 16:51:49', NULL, NULL, 1, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'Sejour en famille', NULL, 0, '2026-07-27 16:48:47', '2026-07-27 23:35:53'),
(3, 8, NULL, 1, 1, '2026-07-30', NULL, '2026-07-27 23:36:00', '07:00:00', '14:30:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'grande conférence sur le climat', NULL, 0, '2026-07-27 23:34:03', '2026-07-28 23:23:18'),
(4, 8, NULL, 16, 39, '2026-07-30', NULL, '2026-07-28 23:00:15', '07:00:00', '12:30:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'matfchhhhhhhhhh de gala', NULL, 0, '2026-07-28 22:58:00', '2026-07-28 23:06:56'),
(5, 8, NULL, 3, 5, '2026-07-30', NULL, '2026-07-29 08:00:10', '07:00:00', '14:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'CCCCCCCCCCCCCCCCCC', NULL, 0, '2026-07-29 07:54:43', '2026-07-29 08:01:18'),
(6, 8, NULL, 2, 3, '2026-10-08', NULL, '2026-07-30 02:25:52', '07:00:00', '08:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'o^hfsndg,;:', NULL, 0, '2026-07-29 14:30:05', '2026-07-30 02:26:32'),
(7, 8, NULL, 8, 27, '2026-10-26', '2026-11-29', '2026-07-30 14:14:10', NULL, NULL, 1, 0, 'en_ligne', NULL, 12, NULL, 'validee', 'paye', NULL, 0, 'j\'ai une fete ave des amis', NULL, 0, '2026-07-30 14:03:10', '2026-07-30 14:17:21'),
(8, 8, NULL, 1, 1, '2026-07-31', NULL, '2026-07-30 14:14:11', '09:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'validee', 'paye', NULL, 0, 'vvvvvvvvvvvvvvvvvvvvvvv', NULL, 0, '2026-07-30 14:10:25', '2026-07-30 14:16:41'),
(9, 8, NULL, 14, 35, '2026-07-31', NULL, '2026-07-30 22:18:13', '07:00:00', '15:30:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'paye', NULL, 0, 'xxxxxxxxxxxxddddddcc', NULL, 0, '2026-07-30 22:17:27', '2026-08-10 13:14:22'),
(10, 8, NULL, 4, 7, '2026-07-31', '2026-08-02', '2026-07-31 11:37:43', NULL, NULL, 0, 0, 'en_ligne', NULL, 1, NULL, 'expiree', 'non_paye', NULL, 0, 'sxxwwwwwwwww', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-07-31 11:37:25', '2026-08-03 11:13:33'),
(11, 8, NULL, 14, 35, '2026-08-01', NULL, '2026-08-10 14:11:53', '10:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjjjjjjjj', NULL, 0, '2026-07-31 13:15:35', '2026-08-10 16:06:18'),
(12, 12, NULL, 2, 3, '2026-08-04', NULL, '2026-08-01 00:27:03', '09:00:00', '14:00:00', 0, 0, 'guichet', NULL, 1, NULL, 'requisitionnee', 'paye', NULL, 0, 'Sortie de promotion universitaire', NULL, 0, '2026-08-01 00:27:03', '2026-09-29 13:21:41'),
(13, 8, NULL, 13, 34, '2026-08-11', NULL, '2026-08-10 16:12:08', '07:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, 'GHGGGGGGGGGGGGGGGGGGGGGG', NULL, 0, '2026-08-10 16:11:32', '2026-08-10 23:37:31'),
(14, 8, NULL, 15, 36, '2026-08-12', NULL, '2026-08-11 00:15:50', '09:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'expiree', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjjjjjjjj', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-08-11 00:14:04', '2026-08-13 12:02:22'),
(15, 8, NULL, 2, 3, '2026-09-02', NULL, '2026-08-31 01:43:42', '07:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'expiree', 'partiellement_paye', '2026-09-01', 0, 'kjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-08-31 01:43:17', '2026-09-22 20:52:18'),
(16, 8, NULL, 2, 3, '2026-10-06', NULL, '2026-09-26 22:06:56', '11:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, 'konbvgfèhunu', NULL, 0, '2026-09-26 22:06:29', '2026-09-26 22:07:52'),
(17, 8, NULL, 15, 36, '2026-10-03', NULL, '2026-09-28 00:35:59', '08:00:00', '18:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, 'conference sur octobre rose', NULL, 0, '2026-09-28 00:35:28', '2026-09-28 00:36:08'),
(18, 8, NULL, 1, 1, '2026-10-08', NULL, '2026-09-28 14:49:01', '07:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', NULL, 0, '2026-09-28 14:48:47', '2026-09-28 14:49:09'),
(19, 8, NULL, 2, 3, '2026-10-06', NULL, '2026-09-28 16:07:13', '07:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'non_paye', NULL, 0, ',,,,,,,,,,,,,,,,,,,,,,,,,,,,,', NULL, 0, '2026-09-28 16:06:58', '2026-09-28 16:07:22'),
(20, 8, NULL, 14, 35, '2026-10-07', NULL, '2026-09-28 18:17:25', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, NULL, 'requisitionnee', 'paye', NULL, 0, 'kpmihhhhhhhhhhhh', NULL, 0, '2026-09-28 18:17:11', '2026-09-28 18:18:23'),
(21, 8, NULL, 17, 41, '2026-10-07', NULL, '2026-09-29 07:44:04', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 50000.00, 'requisitionnee', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjj', '[Réquisition #9] 50 000 FCFA encaissés transférés vers la nouvelle réservation #22 le 29/09/2026 à 17:42.', 0, '2026-09-29 07:43:48', '2026-09-29 15:42:10'),
(22, 8, NULL, 17, 41, '2026-10-08', NULL, '2026-09-29 15:42:10', '07:00:00', '17:00:00', 0, 0, 'en_ligne', 9, 1, 50000.00, 'requisitionnee', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjj', '[Réquisition #11] 50 000 FCFA encaissés transférés vers la nouvelle réservation #23 le 29/09/2026 à 17:47.', 0, '2026-09-29 15:41:51', '2026-09-29 15:47:47'),
(23, 8, NULL, 17, 41, '2026-10-01', NULL, '2026-09-29 15:47:47', '07:00:00', '17:00:00', 0, 0, 'en_ligne', 11, 1, 50000.00, 'validee', 'paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjj', NULL, 0, '2026-09-29 15:46:52', '2026-09-29 15:47:47'),
(24, 8, NULL, 14, 35, '2026-10-03', NULL, '2026-09-29 15:47:44', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 150000.00, 'requisitionnee', 'paye', NULL, 0, 'fiuhazodclona', NULL, 0, '2026-09-29 15:47:27', '2026-09-29 15:50:15'),
(25, 8, NULL, 15, 36, '2026-10-23', NULL, '2026-09-29 19:28:13', '08:00:00', '18:00:00', 0, 0, 'en_ligne', 5, 1, 150000.00, 'validee', 'paye', NULL, 0, 'conference sur octobre rose', NULL, 0, '2026-09-29 17:38:27', '2026-09-30 20:09:07'),
(26, 13, 1, 1, 1, '2026-10-11', NULL, '2026-09-30 01:04:52', '07:00:00', '16:30:00', 0, 0, 'en_ligne', NULL, 1, 400000.00, 'validee', 'paye', NULL, 0, 'bggggggggggg', NULL, 0, '2026-09-30 00:35:09', '2026-09-30 01:05:50'),
(27, 8, NULL, 1, 1, '2026-10-03', NULL, '2026-09-30 12:07:30', '07:00:00', '18:00:00', 0, 0, 'en_ligne', NULL, 1, 400000.00, 'requisitionnee', 'non_paye', NULL, 0, 'kooojjjjjjjjjjjjjjjjjjjj', '[Réquisition #13] 400 000 FCFA encaissés transférés vers la nouvelle réservation #28 le 30/09/2026 à 14:18.', 0, '2026-09-30 12:06:30', '2026-09-30 12:18:38'),
(28, 8, NULL, 1, 1, '2026-10-24', NULL, '2026-09-30 12:18:38', '07:00:00', '18:00:00', 0, 0, 'en_ligne', 13, 1, 400000.00, 'validee', 'paye', NULL, 0, 'kooojjjjjjjjjjjjjjjjjjjj', NULL, 0, '2026-09-30 12:17:48', '2026-09-30 12:18:38'),
(29, 8, NULL, 2, 3, '2026-10-01', NULL, '2026-09-30 13:26:05', '08:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 400000.00, 'requisitionnee', 'paye', NULL, 0, 'gggggggggggggg', NULL, 0, '2026-09-30 13:24:37', '2026-09-30 13:32:59'),
(30, 8, NULL, 14, 35, '2026-10-04', NULL, '2026-09-30 13:53:02', '07:00:00', '12:00:00', 0, 0, 'en_ligne', NULL, 1, 150000.00, 'requisitionnee', 'paye', NULL, 0, 'iiiiiiiiiiiiiiiiiiiiiii', NULL, 0, '2026-09-30 13:52:34', '2026-09-30 13:56:05'),
(31, 14, NULL, 1, 1, '2026-10-30', NULL, '2026-09-30 20:08:00', '07:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, 400000.00, 'validee', 'paye', NULL, 0, 'Pour l’organisation d’une journée rose', NULL, 0, '2026-09-30 20:07:39', '2026-09-30 20:09:47');

-- --------------------------------------------------------

--
-- Structure de la table `services_annexes`
--

CREATE TABLE `services_annexes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `montant` decimal(12,2) NOT NULL COMMENT 'Montant en F CFA',
  `unite` varchar(50) NOT NULL DEFAULT 'mois',
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Prestations annexes informationnelles (contact direct, non réservables)';

--
-- Déchargement des données de la table `services_annexes`
--

INSERT INTO `services_annexes` (`id`, `nom`, `description`, `montant`, `unite`, `actif`) VALUES
(1, 'Support publicitaire au sein du Palais des Pionniers', 'Affichage publicitaire dans les espaces du Palais — visibilité auprès du public fréquentant les activités sportives et culturelles.', 100000.00, 'mois', 1),
(2, 'Espace lavage auto et moto', 'Service de lavage automobile et deux-roues disponible au sein du Palais des Pionniers.', 100000.00, 'mois', 1);

-- --------------------------------------------------------

--
-- Structure de la table `suggestions`
--

CREATE TABLE `suggestions` (
  `id` int(10) UNSIGNED NOT NULL,
  `contenu` text NOT NULL,
  `statut` enum('nouvelle','lue','traitee') NOT NULL DEFAULT 'nouvelle',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut_modifie_par` int(10) UNSIGNED DEFAULT NULL COMMENT 'Administrateur (jamais l auteur de la suggestion)',
  `statut_modifie_le` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Boîte à suggestions anonyme (aucune donnée personnelle)';

--
-- Déchargement des données de la table `suggestions`
--

INSERT INTO `suggestions` (`id`, `contenu`, `statut`, `created_at`, `statut_modifie_par`, `statut_modifie_le`) VALUES
(1, 'Vos devriez revoir vos tarifs je trouve', 'nouvelle', '2026-09-29 23:09:28', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `tarifs`
--

CREATE TABLE `tarifs` (
  `id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(200) NOT NULL,
  `montant` decimal(12,2) NOT NULL COMMENT 'Montant en F CFA',
  `unite` enum('heure','demi-journee','jour','mois','match','nuitée','événement','seance','personne_jour','personne_mois','personne_an','activite','support') NOT NULL DEFAULT 'jour',
  `quantite_disponible` int(10) UNSIGNED DEFAULT NULL COMMENT 'Nombre d''unités identiques disponibles (ex: 7 chambres) — NULL = unité unique (comportement classique, un seul créneau à la fois)',
  `est_bail` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Location longue durée négociée directement (bail) — affiché à titre informatif, jamais réservable en ligne',
  `gerant_nom` varchar(150) DEFAULT NULL COMMENT 'Nom du gestionnaire à contacter pour ce tarif en bail',
  `gerant_contact` varchar(100) DEFAULT NULL COMMENT 'Téléphone/contact du gestionnaire pour ce tarif en bail'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Grille tarifaire officielle par espace';

--
-- Déchargement des données de la table `tarifs`
--

INSERT INTO `tarifs` (`id`, `espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`, `gerant_nom`, `gerant_contact`) VALUES
(1, 1, 'Location Journée complète', 400000.00, 'jour', NULL, 0, NULL, NULL),
(3, 2, 'Location Journée complète', 400000.00, 'jour', NULL, 0, NULL, NULL),
(4, 3, 'Séance d\'entraînement', 10000.00, 'heure', NULL, 0, NULL, NULL),
(5, 3, 'Match de Gala', 20000.00, 'match', NULL, 0, NULL, NULL),
(6, 3, 'Location en bail', 500000.00, 'mois', NULL, 1, NULL, NULL),
(7, 4, 'Chambre avec douche intérieure', 10000.00, 'nuitée', 7, 0, NULL, NULL),
(8, 4, 'Chambre ventilée sans douche', 5000.00, 'nuitée', 20, 0, NULL, NULL),
(9, 5, 'Location en PPP', 750000.00, 'mois', NULL, 1, NULL, NULL),
(10, 5, 'Location pour dîner ou déjeuner', 200000.00, 'jour', NULL, 0, NULL, NULL),
(11, 5, 'Fédération Aquatique du Mali', 50000.00, 'activite', NULL, 0, NULL, NULL),
(12, 5, 'Redevance support publicitaire', 25000.00, 'support', NULL, 0, NULL, NULL),
(13, 5, 'Natation enfant', 1500.00, 'jour', NULL, 0, NULL, NULL),
(14, 5, 'Natation adulte', 3000.00, 'jour', NULL, 0, NULL, NULL),
(15, 5, 'Inscription natation (mensualité)', 10000.00, 'personne_mois', NULL, 0, NULL, NULL),
(16, 5, 'Location en bail', 750000.00, 'mois', NULL, 1, NULL, NULL),
(17, 6, 'Location terrain de basket (mensuel)', 150000.00, 'mois', NULL, 0, NULL, NULL),
(18, 6, 'Séance d\'entraînement', 10000.00, 'seance', NULL, 1, NULL, NULL),
(19, 6, 'Match de Gala', 50000.00, 'match', NULL, 1, NULL, NULL),
(20, 6, 'Location en bail', 300000.00, 'mois', NULL, 0, 'Monssieur Diallo', '+223 76146680'),
(21, 7, 'Location salle de gymnastique (PPP)', 500000.00, 'mois', NULL, 1, NULL, NULL),
(26, 7, 'Salle multifonction', 200000.00, 'mois', NULL, 1, NULL, NULL),
(27, 8, 'Chambre climatisée (douche intérieure, TV)', 25000.00, 'nuitée', 26, 0, NULL, NULL),
(28, 8, 'Location en bail de la résidence entière', 10000000.00, 'mois', NULL, 1, NULL, NULL),
(33, 12, 'Location salle de classe', 25000.00, 'jour', 6, 0, NULL, NULL),
(34, 13, 'Événement dans la cour', 200000.00, 'jour', NULL, 0, NULL, NULL),
(35, 14, 'Location journée', 200000.00, 'jour', NULL, 0, NULL, NULL),
(36, 15, 'Location journée', 150000.00, 'jour', NULL, 0, NULL, NULL),
(37, 16, 'Séance d\'entraînement', 10000.00, 'heure', NULL, 1, 'Seydou', '+22390921120'),
(38, 16, 'Club (abonnement mensuel)', 30000.00, 'mois', NULL, 0, NULL, NULL),
(39, 16, 'Match de Gala', 50000.00, 'match', NULL, 0, NULL, NULL),
(40, 16, 'Location en bail', 300000.00, 'mois', NULL, 0, 'Monssieur Diallo', '+223 76146680'),
(41, 17, 'Location journée', 50000.00, 'jour', NULL, 0, NULL, NULL),
(42, 18, 'Location salle (PPP)', 250000.00, 'mois', NULL, 1, NULL, NULL),
(43, 18, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0, NULL, NULL),
(44, 18, 'Mensualité', 15000.00, 'personne_mois', NULL, 0, NULL, NULL),
(45, 18, 'Location en bail', 500000.00, 'mois', NULL, 1, NULL, NULL),
(46, 19, 'Location salle (PPP)', 250000.00, 'mois', NULL, 1, NULL, NULL),
(47, 19, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0, NULL, NULL),
(48, 19, 'Mensualité', 15000.00, 'personne_mois', NULL, 0, NULL, NULL),
(49, 19, 'Location en bail', 500000.00, 'mois', NULL, 1, NULL, NULL),
(50, 18, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0, NULL, NULL),
(51, 18, 'Mensualité', 15000.00, 'personne_mois', NULL, 0, NULL, NULL),
(52, 18, 'Location en bail', 500000.00, 'mois', NULL, 1, NULL, NULL),
(53, 19, 'Inscription annuelle', 50000.00, 'personne_an', NULL, 0, NULL, NULL),
(54, 19, 'Mensualité', 15000.00, 'personne_mois', NULL, 0, NULL, NULL),
(55, 19, 'Location en bail', 500000.00, 'mois', NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom_complet` varchar(200) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable','partenaire') NOT NULL DEFAULT 'user',
  `partenaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Compte associé à un partenaire (NULL = client classique)',
  `actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Blocage de compte (0 = suspendu)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Comptes utilisateurs publics et administrateurs';

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom_complet`, `email`, `telephone`, `password_hash`, `role`, `partenaire_id`, `actif`, `created_at`) VALUES
(1, 'Administration Système', 'superadmin@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin', NULL, 1, '2026-07-27 15:19:28'),
(2, 'Direction Générale', 'dg@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin', NULL, 1, '2026-07-27 15:19:28'),
(3, 'Cabinet du Ministre', 'ministre@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'ministre', NULL, 1, '2026-07-27 15:19:28'),
(4, 'Administration Espaces', 'admin.espaces@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_espaces', NULL, 1, '2026-07-27 15:19:28'),
(5, 'Administration Activités', 'admin.activites@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_activites', NULL, 1, '2026-07-27 15:19:28'),
(6, 'Administration Messages', 'admin.messages@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_messages', NULL, 1, '2026-07-27 15:19:28'),
(7, 'Service Comptable', 'comptable@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_comptable', NULL, 1, '2026-07-27 15:19:28'),
(8, 'Tapa COULIBALY', 'ladytapa@gmail.com', '+22390921120', '$2y$10$DkqbcprqEXz1s32OgDiBDu7xccVubAoFNecCEqCiVbgOmAw9ETYEK', 'user', NULL, 1, '2026-07-27 15:56:44'),
(9, 'Seydou COULIBALY', 'seydou@gmail.com', '+22390921120', '$2y$10$4g/vQR7q2ON2s0S38RVPgejOBLG/270s1OXPHkCRKItr93tsaz27.', 'user', NULL, 1, '2026-07-29 14:04:34'),
(10, 'mimi CISSÉ', 'mimi@gmail.com', '+22390921120', '$2y$10$ZScYrNh76fYnnFe4sHeFUOpyhw4kFGhTOC/CAbOSUQdsdJFUbOlqa', 'user', NULL, 1, '2026-07-30 14:11:02'),
(11, 'tata CISSÉ', 'tata@gmail.com', '+22390921120', '$2y$10$kd9FRcAy4U.PZEApxbfNJ.OG4cBH28ZZG4mIkX3lsZ4OTmr6PJBO6', 'user', NULL, 1, '2026-07-31 13:16:35'),
(12, 'Oumou COULIBALY', 'coul@gmail.com', '+22390921120', '$2y$10$qVqwVcE3gltoCQCjSP5Ud.xlx71bf8qcT16TR5EvyuaZ5eVtTvMLm', 'user', NULL, 1, '2026-08-01 00:27:02'),
(13, 'CICB', 'cicb@gmail.com', '90921120', '$2y$10$rzVTMsUfnTuvA5NtLtHKQenkGVAOSoD5OU998.rmk9OwVw8zpOruO', 'partenaire', 1, 1, '2026-09-29 23:07:33'),
(14, 'Sitan COULIBALY', 'sitan@gmail.com', '90921120', '$2y$10$BFp7GX8icqJEpjQJ0obEeO3LgUsIzFakckQKU1gXbrFEDW9FMe.Zi', 'user', NULL, 1, '2026-09-30 20:06:51');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activites`
--
ALTER TABLE `activites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `activite_galerie`
--
ALTER TABLE `activite_galerie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activite_galerie_parent` (`activite_id`);

--
-- Index pour la table `activite_liens`
--
ALTER TABLE `activite_liens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activite_liens_parent` (`activite_id`);

--
-- Index pour la table `activite_sections`
--
ALTER TABLE `activite_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activite_sections_parent` (`activite_id`);

--
-- Index pour la table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_module` (`module`),
  ADD KEY `idx_log_user` (`user_id`),
  ADD KEY `idx_log_created` (`created_at`);

--
-- Index pour la table `bail_paiements`
--
ALTER TABLE `bail_paiements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_espace_periode` (`espace_id`,`periode_debut`),
  ADD KEY `fk_bail_paiement_user` (`enregistre_par`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `demandes_bail`
--
ALTER TABLE `demandes_bail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_demande_bail_espace` (`espace_id`);

--
-- Index pour la table `demandes_services`
--
ALTER TABLE `demandes_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_demande_service_user` (`user_id`),
  ADD KEY `fk_demande_service_service` (`service_id`);

--
-- Index pour la table `demande_bail_espaces`
--
ALTER TABLE `demande_bail_espaces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dbe_demande` (`demande_id`),
  ADD KEY `fk_dbe_espace` (`espace_id`);

--
-- Index pour la table `direction`
--
ALTER TABLE `direction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_role_key` (`role_key`);

--
-- Index pour la table `espaces`
--
ALTER TABLE `espaces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_espaces_cat` (`categorie_id`);

--
-- Index pour la table `espace_images`
--
ALTER TABLE `espace_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_images_espace` (`espace_id`);

--
-- Index pour la table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `formation_galerie`
--
ALTER TABLE `formation_galerie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_formation_galerie_parent` (`formation_id`);

--
-- Index pour la table `jeunes_engages`
--
ALTER TABLE `jeunes_engages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sujet` (`sujet`),
  ADD KEY `idx_messages_lu` (`lu`),
  ADD KEY `fk_messages_espace` (`espace_id`),
  ADD KEY `fk_messages_activite` (`activite_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_role` (`destinataire_role`),
  ADD KEY `idx_notif_lu` (`lu`);

--
-- Index pour la table `observations`
--
ALTER TABLE `observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_obs_cible` (`cible_type`,`cible_id`),
  ADD KEY `idx_obs_auteur` (`auteur_id`),
  ADD KEY `idx_obs_parent` (`parent_id`);

--
-- Index pour la table `operations_requisition`
--
ALTER TABLE `operations_requisition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_operation_context` (`id`,`requisition_id`,`reservation_id`),
  ADD KEY `idx_operation_requisition` (`requisition_id`),
  ADD KEY `idx_operation_reservation` (`reservation_id`),
  ADD KEY `idx_operation_statut` (`statut`),
  ADD KEY `idx_operation_type` (`type_operation`),
  ADD KEY `idx_operation_agent` (`agent_assigne`),
  ADD KEY `idx_operation_traite_par` (`traite_par`),
  ADD KEY `fk_operation_requisition_reservation` (`requisition_id`,`reservation_id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_paiement_resa` (`reservation_id`),
  ADD KEY `idx_paiement_user` (`enregistre_par`);

--
-- Index pour la table `partenaires`
--
ALTER TABLE `partenaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partenaires_actif` (`actif`);

--
-- Index pour la table `personnalites`
--
ALTER TABLE `personnalites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_personnalite_espace` (`espace_id`);

--
-- Index pour la table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `reductions_accordees`
--
ALTER TABLE `reductions_accordees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reduction_appliquee` (`reservation_appliquee`),
  ADD KEY `idx_reduction_reservation` (`reservation_id`,`statut`),
  ADD KEY `idx_reduction_saisi_par` (`saisi_par`),
  ADD KEY `idx_reduction_reportee_de` (`reportee_de`),
  ADD KEY `fk_reduction_statut_par` (`statut_modifie_par`),
  ADD KEY `idx_reduction_origine` (`origine`,`statut`);

--
-- Index pour la table `remboursements`
--
ALTER TABLE `remboursements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remboursement_operation` (`operation_id`),
  ADD KEY `idx_remboursement_requisition` (`requisition_id`),
  ADD KEY `idx_remboursement_reservation` (`reservation_id`),
  ADD KEY `idx_remboursement_client` (`client_id`),
  ADD KEY `idx_remboursement_traite_par` (`traite_par`),
  ADD KEY `fk_remboursement_operation_context` (`operation_id`,`requisition_id`,`reservation_id`),
  ADD KEY `fk_remboursement_client_reservation` (`reservation_id`,`client_id`);

--
-- Index pour la table `requisitions_ministerielles`
--
ALTER TABLE `requisitions_ministerielles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_requisition_reservation` (`id`,`reservation_id`),
  ADD KEY `fk_requisition_reservation` (`reservation_id`);

--
-- Index pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reservation_user` (`id`,`user_id`),
  ADD KEY `idx_resa_user` (`user_id`),
  ADD KEY `idx_resa_espace` (`espace_id`),
  ADD KEY `idx_resa_date` (`date_resa`),
  ADD KEY `idx_resa_statut` (`statut`),
  ADD KEY `idx_resa_horaires` (`espace_id`,`date_resa`,`heure_debut`,`heure_fin`),
  ADD KEY `fk_reservation_tarif` (`tarif_id`),
  ADD KEY `idx_reservation_requisition` (`requisition_id`),
  ADD KEY `idx_reservations_partenaire` (`partenaire_id`);

--
-- Index pour la table `services_annexes`
--
ALTER TABLE `services_annexes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_suggestions_statut` (`statut`,`created_at`);

--
-- Index pour la table `tarifs`
--
ALTER TABLE `tarifs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tarifs_espace` (`espace_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_partenaire` (`partenaire_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activites`
--
ALTER TABLE `activites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `activite_galerie`
--
ALTER TABLE `activite_galerie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT pour la table `activite_liens`
--
ALTER TABLE `activite_liens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `activite_sections`
--
ALTER TABLE `activite_sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- AUTO_INCREMENT pour la table `bail_paiements`
--
ALTER TABLE `bail_paiements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `demandes_bail`
--
ALTER TABLE `demandes_bail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `demandes_services`
--
ALTER TABLE `demandes_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `demande_bail_espaces`
--
ALTER TABLE `demande_bail_espaces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `direction`
--
ALTER TABLE `direction`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `espaces`
--
ALTER TABLE `espaces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `espace_images`
--
ALTER TABLE `espace_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `formation_galerie`
--
ALTER TABLE `formation_galerie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `jeunes_engages`
--
ALTER TABLE `jeunes_engages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=311;

--
-- AUTO_INCREMENT pour la table `observations`
--
ALTER TABLE `observations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `operations_requisition`
--
ALTER TABLE `operations_requisition`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `partenaires`
--
ALTER TABLE `partenaires`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `personnalites`
--
ALTER TABLE `personnalites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `reductions_accordees`
--
ALTER TABLE `reductions_accordees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `remboursements`
--
ALTER TABLE `remboursements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `requisitions_ministerielles`
--
ALTER TABLE `requisitions_ministerielles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `services_annexes`
--
ALTER TABLE `services_annexes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activite_galerie`
--
ALTER TABLE `activite_galerie`
  ADD CONSTRAINT `fk_activite_galerie_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `activite_liens`
--
ALTER TABLE `activite_liens`
  ADD CONSTRAINT `fk_activite_liens_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `activite_sections`
--
ALTER TABLE `activite_sections`
  ADD CONSTRAINT `fk_activite_sections_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `bail_paiements`
--
ALTER TABLE `bail_paiements`
  ADD CONSTRAINT `fk_bail_paiement_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bail_paiement_user` FOREIGN KEY (`enregistre_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `demandes_bail`
--
ALTER TABLE `demandes_bail`
  ADD CONSTRAINT `fk_demande_bail_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demandes_services`
--
ALTER TABLE `demandes_services`
  ADD CONSTRAINT `fk_demande_service_service` FOREIGN KEY (`service_id`) REFERENCES `services_annexes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_demande_service_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demande_bail_espaces`
--
ALTER TABLE `demande_bail_espaces`
  ADD CONSTRAINT `fk_dbe_demande` FOREIGN KEY (`demande_id`) REFERENCES `demandes_bail` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dbe_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `espaces`
--
ALTER TABLE `espaces`
  ADD CONSTRAINT `fk_espaces_cat` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`);

--
-- Contraintes pour la table `espace_images`
--
ALTER TABLE `espace_images`
  ADD CONSTRAINT `fk_images_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `formation_galerie`
--
ALTER TABLE `formation_galerie`
  ADD CONSTRAINT `fk_formation_galerie_parent` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_activite` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `observations`
--
ALTER TABLE `observations`
  ADD CONSTRAINT `fk_obs_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_obs_parent` FOREIGN KEY (`parent_id`) REFERENCES `observations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `operations_requisition`
--
ALTER TABLE `operations_requisition`
  ADD CONSTRAINT `fk_operation_agent` FOREIGN KEY (`agent_assigne`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_operation_requisition_reservation` FOREIGN KEY (`requisition_id`,`reservation_id`) REFERENCES `requisitions_ministerielles` (`id`, `reservation_id`),
  ADD CONSTRAINT `fk_operation_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  ADD CONSTRAINT `fk_operation_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `fk_paiement_resa` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_paiement_user` FOREIGN KEY (`enregistre_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `personnalites`
--
ALTER TABLE `personnalites`
  ADD CONSTRAINT `fk_personnalite_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `reductions_accordees`
--
ALTER TABLE `reductions_accordees`
  ADD CONSTRAINT `fk_reduction_reportee_de` FOREIGN KEY (`reportee_de`) REFERENCES `reductions_accordees` (`id`),
  ADD CONSTRAINT `fk_reduction_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  ADD CONSTRAINT `fk_reduction_saisi_par` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reduction_statut_par` FOREIGN KEY (`statut_modifie_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `remboursements`
--
ALTER TABLE `remboursements`
  ADD CONSTRAINT `fk_remboursement_client_reservation` FOREIGN KEY (`reservation_id`,`client_id`) REFERENCES `reservations` (`id`, `user_id`),
  ADD CONSTRAINT `fk_remboursement_operation_context` FOREIGN KEY (`operation_id`,`requisition_id`,`reservation_id`) REFERENCES `operations_requisition` (`id`, `requisition_id`, `reservation_id`),
  ADD CONSTRAINT `fk_remboursement_requisition` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions_ministerielles` (`id`),
  ADD CONSTRAINT `fk_remboursement_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`),
  ADD CONSTRAINT `fk_remboursement_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `requisitions_ministerielles`
--
ALTER TABLE `requisitions_ministerielles`
  ADD CONSTRAINT `fk_requisition_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_resa_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`),
  ADD CONSTRAINT `fk_resa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reservation_requisition` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions_ministerielles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservation_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservations_partenaire` FOREIGN KEY (`partenaire_id`) REFERENCES `partenaires` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `tarifs`
--
ALTER TABLE `tarifs`
  ADD CONSTRAINT `fk_tarifs_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_partenaire` FOREIGN KEY (`partenaire_id`) REFERENCES `partenaires` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
