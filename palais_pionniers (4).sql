-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 29 sep. 2026 à 04:53
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
(3, 'Ecole de la citoyenneté', 'ecole-de-la-citoyennet', 'Le programme « À l\'École de la Citoyenneté » est une initiative présidentielle et ministérielle majeure pilotée par le Ministère de la Jeunesse et des Sports, chargé de l\'Instruction civique et de la Construction citoyenne du Mali. Conçu dans le cadre de la refondation nationale, ce projet d\'envergure vise à mobiliser, éduquer et responsabiliser la jeunesse malienne afin d\'en faire le moteur du sursaut patriotique et du développement socio-économique du pays.\r\nAu cœur de ce programme se trouve la volonté politique de réaffirmer les valeurs cardinales de la société malienne, telles que le Danbé (la dignité et l\'honneur) et le Maaya (l\'humanisme et le sens de la communauté).\r\n À travers des sessions de formation intensives organisées au niveau national et dans les régions, le programme rassemble des jeunes venus de tous les horizons, y compris des déplacés internes, des membres de la diaspora et des délégations des pays frères de la Confédération de l\'Alliance des États du Sahel (AES). Durant leur séjour, les auditeurs participent à des modules rigoureux axés sur le civisme, le respect des symboles de la République, l\'histoire du Mali et la culture de la paix.\r\nL\'originalité de l\'École de la Citoyenneté repose sur son approche interactive et son ouverture sur la gouvernance de l\'État. \r\nLe programme intègre des panels ministériels interactifs, des espaces d\'échange direct où plusieurs membres du gouvernement viennent exposer les priorités de leurs départements respectifs et répondre sans tabou aux questions de la jeunesse sur l\'emploi, la sécurité, l\'éducation ou l\'économie. En parallèle à cette immersion institutionnelle, le programme met un point d\'honneur à l\'autonomisation des participants en leur offrant des formations pratiques dans des métiers porteurs, comme la saponification et l\'informatique. ', NULL, 'hero-ecole-de-la-citoyennet-6a7e7ed3279e1.jpeg', 'Forger le maliden koura', NULL, '1000 jeunes formés', NULL, NULL, '2026-08-14 02:34:27'),
(5, 'Campagne de reboisement du ministère de l\'environnement', 'campagne-de-reboisement-du-minist-re-de-l-environnement', 'nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn', NULL, 'hero-campagne-de-reboisement-du-minist-re-de-l-environnement-6a7e8a73802e2.jpeg', 'nkkkkkkkkkkkkkkkkkkkkkkk', NULL, '', '#14b53a', NULL, '2026-08-14 03:24:35');

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
(7, 5, 5, 'gal-6a7e8a7383720.jpeg', NULL, NULL, 0),
(8, 5, 6, 'gal-6a7e8a7383720.jpeg', NULL, NULL, 0),
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
(3, 3, 0, 'Sortie - Rencontre', NULL),
(6, 5, 0, 'arbre', NULL);

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
(119, 7, 'Service Comptable', 'requisition_traitement_commence', '', 'Traitement commencé pour la réquisition #5', '2026-09-28 20:43:03');

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
  `statut` enum('en_attente','traitee') NOT NULL DEFAULT 'en_attente',
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `note_traitement` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Demandes tracées de services annexes, réservées aux comptes connectés';

--
-- Déchargement des données de la table `demandes_services`
--

INSERT INTO `demandes_services` (`id`, `user_id`, `service_id`, `message`, `statut`, `traite_par`, `date_traitement`, `note_traitement`, `created_at`) VALUES
(1, 5, 2, NULL, 'en_attente', NULL, NULL, NULL, '2026-08-10 13:07:36'),
(2, 8, 2, NULL, 'en_attente', NULL, NULL, NULL, '2026-08-10 13:07:59'),
(3, 8, 2, NULL, 'traitee', 4, '2026-08-10 13:49:09', NULL, '2026-08-10 13:09:45');

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
(1, 'Salle Seydou BADIAN', 'salle-seydou-badian', 1, '400 places', 'Grand amphithéâtre du Palais, taillé pour les cérémonies officielles, conférences de grande envergure et rassemblements institutionnels. Dispose d\'un espace VVIP séparé pour les personnalités et délégations.', NULL, 'creneau', 0, 1, 150000.00, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(2, 'Salle Sory Ibrahim KOITA dit Bomba', 'salle-sory-ibrahim-koita-dit-bomba', 1, '420 places', 'Deuxième grand amphithéâtre du Palais, adapté aux mêmes usages que la Salle Seydou Badian : conférences, assemblées, cérémonies et grands événements institutionnels ou culturels.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(3, 'Terrain de Maracana', 'terrain-de-maracana', 3, 'Football', 'Terrain de football extérieur du Palais, ouvert aux séances d\'entraînement individuelles ou de club, ainsi qu\'aux matchs de gala et compétitions amicales.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(4, 'Centre d\'Accueil Tidiani COULIBALY dit Necker', 'centre-tidiani-coulibaly-dit-necker', 4, '27 chambres', 'Hébergement économique du Palais, pensé pour l\'accueil de groupes, stagiaires et délégations en séjour à Bamako. 27 chambres réparties en deux formules : chambres ventilées, et chambres avec douche intérieure.', NULL, 'sejour', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(5, 'Piscine', 'piscine', 5, 'Événementiel', 'Piscine du Palais, ouverte aux cours de natation (enfants et adultes), aux entrées journalières, et à la location pour événements privés ou dînatoires.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(6, 'Terrain de Basketball', 'terrain-de-basketball', 3, 'Équipes de club', 'Terrain de basketball extérieur, disponible pour les séances d\'entraînement de club et les matchs de gala.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(7, 'Salle de Gym Daba Modibo KEITA', 'salle-de-gym-daba-modibo-keita', 3, 'Variable', 'Salle de gymnastique du complexe Daba Modibo Keita, avec une salle multifonction pouvant accueillir d\'autres disciplines et événements.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-27 15:19:28'),
(8, 'Résidence Ely Abdoulaye DIALLO', 'residence-ely-abdoulaye-diallo', 4, '26 chambres', 'Résidence haut de gamme du Palais : 26 chambres climatisées, entièrement équipées (téléviseur, douche intérieure), avec option petit-déjeuner. Un cadre confortable pour séjours officiels, missions ou délégations.', NULL, 'sejour', 1, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(9, 'Salle de Séminaire', 'salle-de-seminaire', 2, 'Séminaires & réunions', 'Salle de travail au sein de la Résidence Ely Abdoulaye Diallo, adaptée aux séminaires, formations et réunions de délégation en séjour sur place.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(12, 'Salles de Classe', 'salles-de-classe', 4, '6 salles', 'Six salles de classe identiques au sein de la Résidence Ely Abdoulaye Diallo, louables à l\'unité pour des formations, ateliers ou sessions pédagogiques.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(13, 'Cour Événementielle', 'cour-vnementielle', 5, 'Événementiel', 'Cour extérieure de la Résidence Ely Abdoulaye Diallo, pour l\'organisation d\'événements en plein air : cérémonies, réceptions, activités de groupe.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(14, 'Salle Adama SAMASSEKOU', 'salle-adama-samassekou', 2, '200 places', 'Amphithéâtre de taille moyenne, idéal pour les conférences, assemblées générales et cérémonies de moyenne envergure.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(15, 'Salle de conférence Pr Assétou Founé SAMAKE MIGAN', 'salle-pr-assetou-founé-samake-migan', 2, '80 places', 'Salle de conférence à taille humaine, adaptée aux réunions de travail, formations et ateliers nécessitant un cadre plus intimiste qu\'un grand amphithéâtre.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(16, 'Terrain de Handball', 'terrain-de-handball', 3, 'Équipes de club', 'Terrain de handball extérieur, distinct du terrain de basketball, disponible pour l\'entraînement de club et les matchs de gala.', '', 'creneau', 0, 0, NULL, 'Cet espace est géré par un tiers. Contactez l\'administration du Palais pour obtenir les coordonnées du gestionnaire.', 'Coulibaly', 'Seydou', 'coulibalytapa260@gmail.com', 'mensuel', '+22390921120', NULL, 0, NULL, NULL, NULL, 1, '2026-07-27 15:19:28'),
(17, 'Salle Informatique Oumou Diarra Dite Dièma', 'salle-informatique-oumou-diarra-dite-dima', 2, 'Postes informatiques', 'Salle équipée de postes informatiques, dédiée aux formations numériques, à l\'initiation à l\'outil informatique et aux ateliers de bureautique.', '', 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-07-29 13:18:56'),
(18, 'Salle de Taekwondo Daba Modibo KEITA', 'salle-de-taekwondo-daba-modibo-keita', 3, 'Variable', 'Salle dédiée à la pratique du taekwondo au sein du complexe Daba Modibo Keita.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-29 23:57:43'),
(19, 'Salle de Karaté Daba Modibo KEITA', 'salle-de-karate-daba-modibo-keita', 3, 'Variable', 'Salle dédiée à la pratique du karaté au sein du complexe Daba Modibo Keita.', NULL, 'creneau', 0, 0, NULL, NULL, NULL, NULL, NULL, 'mensuel', NULL, NULL, 0, NULL, NULL, 'Daba Modibo Keita', 1, '2026-07-29 23:57:43');

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
(1, 'Savonnerie', NULL, '3 mois', 'Jeunes 17 - 30', 'formation-6a6a9b8414e11.jpeg', 'palais@gmail.com', NULL, 1, 0, '2026-07-30 00:32:04'),
(2, 'Coupe - Couture', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-08-12 18:10:18'),
(3, 'Informatique', NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, '2026-08-12 18:10:30');

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
(127, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Cour Événementielle » le 11/08/2026', 'reservations.php', 0, '2026-08-10 16:11:32'),
(128, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Cour Événementielle » le 11/08/2026', 'reservations.php', 1, '2026-08-10 16:11:32'),
(129, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Cour Événementielle» le 11/08/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-10 16:12:08'),
(130, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Cour Événementielle» le 11/08/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-10 16:12:09'),
(131, '', 8, 'reservation_validee', 'Votre demande pour « Cour Événementielle » est validée ! Merci de régler au guichet avant le 12/08/2026 à 18:12 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-10 16:12:09'),
(132, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Cour Événementielle » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-08-10 23:37:31'),
(133, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Cour Événementielle ».', 'requisitions.php', 1, '2026-08-10 23:37:58'),
(134, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 12/08/2026', 'reservations.php', 0, '2026-08-11 00:14:04'),
(135, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 12/08/2026', 'reservations.php', 1, '2026-08-11 00:14:04'),
(136, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 12/08/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-11 00:15:50'),
(137, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 12/08/2026 — Tapa COULIBALY', 'reservations.php', 1, '2026-08-11 00:15:50'),
(138, '', 8, 'reservation_validee', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » est validée ! Merci de régler au guichet avant le 13/08/2026 à 02:15 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-11 00:15:50'),
(139, 'admin_espaces', NULL, 'reservation_expiree', 'Réservation #14 pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» annulée automatiquement (48h sans paiement)', 'reservations.php', 0, '2026-08-13 12:02:22'),
(140, '', 8, 'reservation_expiree', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » du 12/08/2026 a été annulée automatiquement : le paiement n\'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.', 'espaces.php', 1, '2026-08-13 12:02:22'),
(141, 'admin_activites', NULL, 'jeune_engage', 'Nouvelle inscription « S\'engager » — Coulibaly Tapa', 'jeunes-engages.php', 1, '2026-08-14 02:31:36'),
(142, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 31/08 au 06/09.', 'rapport.php?type=encaisse&debut=2026-08-31&fin=2026-09-06', 1, '2026-08-31 00:25:20'),
(143, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 02/09/2026', 'reservations.php', 0, '2026-08-31 01:43:17'),
(144, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 02/09/2026', 'reservations.php', 0, '2026-08-31 01:43:17'),
(145, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-08-31 01:43:42'),
(146, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-08-31 01:43:42'),
(147, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 02/09/2026 à 03:43 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-08-31 01:43:42'),
(148, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-012/DGPP-C reçu pour «Salle Sory Ibrahim KOITA dit Bomba» le 02/09/2026 — Tapa COULIBALY — ACOMPTE reçu, solde restant : 200 000 FCFA', 'reservations.php?id=15', 0, '2026-08-31 01:46:47'),
(149, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-012/DGPP-C de 200 000 FCFA enregistré — Réservation #15 — ACOMPTE reçu, solde restant : 200 000 FCFA', 'paiements.php?id=15', 0, '2026-08-31 01:46:47'),
(150, '', 8, 'paiement_confirme', 'Paiement confirmé (26-012/DGPP-C) pour « Salle Sory Ibrahim KOITA dit Bomba » — votre reçu est disponible.', 'generer_bon.php?id=15', 1, '2026-08-31 01:46:47'),
(151, 'admin_espaces', NULL, 'reservation_expiree', 'Réservation #15 pour «Salle Sory Ibrahim KOITA dit Bomba» annulée automatiquement (48h sans paiement)', 'reservations.php', 0, '2026-09-22 20:52:18'),
(152, '', 8, 'reservation_expiree', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » du 02/09/2026 a été annulée automatiquement : le paiement n\'a pas été effectué dans le délai de 48h. Vous pouvez faire une nouvelle demande si le créneau vous intéresse toujours.', 'espaces.php', 1, '2026-09-22 20:52:18'),
(153, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 21/09 au 27/09.', 'rapport.php?type=encaisse&debut=2026-09-21&fin=2026-09-27', 1, '2026-09-23 22:27:32'),
(154, 'admin_espaces', NULL, 'demande_bail', 'Nouvelle demande de bail pour « Terrain de Maracana » — COULIBALY Tapa', 'demandes-bail.php?id=2', 0, '2026-09-25 00:11:02'),
(155, 'admin_comptable', NULL, 'demande_bail', 'Nouvelle demande de bail pour « Terrain de Maracana » — COULIBALY Tapa. À traiter avant tout encaissement.', 'demandes-bail.php?id=2', 1, '2026-09-25 00:11:02'),
(156, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 0, '2026-09-26 22:06:29'),
(157, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 0, '2026-09-26 22:06:29'),
(158, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-26 22:06:56'),
(159, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-09-26 22:06:56'),
(160, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 29/09/2026 à 00:06 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-26 22:06:56'),
(161, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-26 22:07:52'),
(162, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Sory Ibrahim KOITA dit Bomba ».. Opération #1 à traiter.', 'requisitions.php', 1, '2026-09-26 22:08:26'),
(163, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 03/10/2026', 'reservations.php', 0, '2026-09-28 00:35:28'),
(164, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » le 03/10/2026', 'reservations.php', 0, '2026-09-28 00:35:28'),
(165, 'admin_comptable', NULL, 'rapport_hebdo', 'Le rapport hebdomadaire des paiements est disponible pour la semaine du 28/09 au 04/10.', 'rapport.php?type=encaisse&debut=2026-09-28&fin=2026-10-04', 1, '2026-09-28 00:35:37'),
(166, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 03/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 00:35:59'),
(167, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle de conférence Pr Assétou Founé SAMAKE MIGAN» le 03/10/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-09-28 00:35:59'),
(168, '', 8, 'reservation_validee', 'Votre demande pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » est validée ! Merci de régler au guichet avant le 30/09/2026 à 02:35 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 00:35:59'),
(169, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle de conférence Pr Assétou Founé SAMAKE MIGAN » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 00:36:08'),
(170, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle de conférence Pr Assétou Founé SAMAKE MIGAN ».. Opération #2 à traiter.', 'requisitions.php', 1, '2026-09-28 00:36:53'),
(171, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 08/10/2026', 'reservations.php', 0, '2026-09-28 14:48:47'),
(172, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Seydou BADIAN » le 08/10/2026', 'reservations.php', 0, '2026-09-28 14:48:47'),
(173, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 08/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 14:49:01'),
(174, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Seydou BADIAN» le 08/10/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-09-28 14:49:01'),
(175, '', 8, 'reservation_validee', 'Votre demande pour « Salle Seydou BADIAN » est validée ! Merci de régler au guichet avant le 30/09/2026 à 16:49 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 14:49:01'),
(176, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Seydou BADIAN » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 14:49:09'),
(177, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Autre espace » suite à la réquisition de « Salle Seydou BADIAN ».. Opération #3 à traiter.', 'requisitions.php', 1, '2026-09-28 14:50:50'),
(178, 'admin_comptable', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/09/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-09-01', 1, '2026-09-28 16:06:16'),
(179, 'admin_espaces', NULL, 'echeance_bail', 'Échéance de bail : le loyer de « Terrain de Handball » (1 mois) est dû depuis le 01/09/2026 — encaissement à effectuer.', 'baux.php?echeance=16-2026-09-01', 0, '2026-09-28 16:06:16'),
(180, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 0, '2026-09-28 16:06:58'),
(181, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Sory Ibrahim KOITA dit Bomba » le 06/10/2026', 'reservations.php', 0, '2026-09-28 16:06:58'),
(182, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 1, '2026-09-28 16:07:13'),
(183, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Sory Ibrahim KOITA dit Bomba» le 06/10/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-09-28 16:07:13'),
(184, '', 8, 'reservation_validee', 'Votre demande pour « Salle Sory Ibrahim KOITA dit Bomba » est validée ! Merci de régler au guichet avant le 30/09/2026 à 18:07 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 16:07:13'),
(185, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Sory Ibrahim KOITA dit Bomba » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 16:07:22'),
(186, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Nouvelle date » suite à la réquisition de « Salle Sory Ibrahim KOITA dit Bomba ».. Opération #4 à traiter.', 'requisitions.php', 1, '2026-09-28 16:08:44'),
(187, 'admin_espaces', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 07/10/2026', 'reservations.php', 0, '2026-09-28 18:17:11'),
(188, 'superadmin', NULL, 'nouvelle_reservation', 'Nouvelle demande de Tapa COULIBALY pour « Salle Adama SAMASSEKOU » le 07/10/2026', 'reservations.php', 0, '2026-09-28 18:17:11'),
(189, 'admin_comptable', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY : paiement à encaisser', 'paiements.php', 0, '2026-09-28 18:17:25'),
(190, 'superadmin', NULL, 'reservation_validee', 'Réservation validée pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY', 'reservations.php', 0, '2026-09-28 18:17:25'),
(191, '', 8, 'reservation_validee', 'Votre demande pour « Salle Adama SAMASSEKOU » est validée ! Merci de régler au guichet avant le 30/09/2026 à 20:17 — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.', 'mon-compte.php', 1, '2026-09-28 18:17:25'),
(192, 'admin_espaces', NULL, 'paiement_recu', 'Paiement 26-013/DGPP-C reçu pour «Salle Adama SAMASSEKOU» le 07/10/2026 — Tapa COULIBALY', 'reservations.php?id=20', 0, '2026-09-28 18:18:11'),
(193, 'superadmin', NULL, 'paiement_recu', 'Paiement 26-013/DGPP-C de 150 000 FCFA enregistré — Réservation #20', 'paiements.php?id=20', 0, '2026-09-28 18:18:11'),
(194, '', 8, 'paiement_confirme', 'Paiement confirmé (26-013/DGPP-C) pour « Salle Adama SAMASSEKOU » — votre reçu est disponible.', 'generer_bon.php?id=20', 1, '2026-09-28 18:18:11'),
(195, '', 8, 'requisition_ministerielle', 'Votre réservation pour « Salle Adama SAMASSEKOU » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.', 'mon-compte.php', 1, '2026-09-28 18:18:23'),
(196, 'admin_comptable', NULL, 'requisition_choix', 'Le client a choisi « Remboursement » suite à la réquisition de « Salle Adama SAMASSEKOU ». — Montant à rembourser : 150 000 FCFA. Opération #5 à traiter.', 'requisitions.php', 0, '2026-09-28 18:19:40'),
(197, 'admin_comptable', NULL, 'requisition_traitement', 'La réquisition #8 est maintenant en cours de traitement.', 'requisitions.php', 0, '2026-09-28 20:24:45'),
(198, 'admin_comptable', NULL, 'requisition_remboursement', 'Le remboursement de la réquisition #8 a été enregistré : 150 000 FCFA.', 'requisitions.php', 0, '2026-09-28 20:25:30'),
(199, 'admin_comptable', NULL, 'requisition_en_traitement', 'La réquisition #5 est maintenant en traitement.', 'requisition-detail.php?id=5', 0, '2026-09-28 20:43:03');

-- --------------------------------------------------------

--
-- Structure de la table `observations`
--

CREATE TABLE `observations` (
  `id` int(10) UNSIGNED NOT NULL,
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `cible_type` enum('reservation','paiement','activite','espace') NOT NULL,
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
(1, 4, 16, 'nouvelle_date', 'a_traiter', NULL, 'Choix du client : nouvelle_date — 10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-26 22:08:26', NULL),
(2, 5, 17, 'nouvelle_date', 'en_cours', NULL, 'Choix du client : nouvelle_date — 2026-10-10', 7, '2026-09-28 20:43:03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-28 00:36:53', '2026-09-28 20:43:03'),
(3, 6, 18, 'changement_espace', 'traitee', NULL, 'Choix du client : autre_espace — Salle Adama SAMASSEKOU', NULL, NULL, 7, '2026-09-28 15:51:52', 'Demande de changement d’espace enregistrée et traitée.', NULL, NULL, NULL, '2026-09-28 14:50:50', '2026-09-28 15:51:52'),
(4, 7, 19, 'nouvelle_date', 'traitee', NULL, 'Choix du client : nouvelle_date — 2026-09-30', NULL, NULL, 7, '2026-09-28 16:09:23', 'Demande de nouvelle date enregistrée et traitée.', NULL, NULL, NULL, '2026-09-28 16:08:44', '2026-09-28 16:09:23'),
(5, 8, 20, 'remboursement', 'traitee', 150000.00, 'Choix du client : remboursement', 7, '2026-09-28 20:24:45', 7, '2026-09-28 20:25:30', 'effectue', '02', NULL, 'Remboursement effectué par Espèces — montant : 150 000 FCFA — Réf. : 02', '2026-09-28 18:19:40', '2026-09-28 20:25:30');

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
(13, 20, 150000.00, 150000.00, NULL, 'especes', '', '', 7, '2026-09-28 18:18:11');

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
(1, 'Samaké Migan', 'Assetou Founé', 'Ancienne Ministre', 'perso-6a70a9942a93e.jpg', NULL, 15, 0, '2026-07-29 13:29:42'),
(2, 'Diarra', 'Oumou dit Djèma', 'Artiste', 'perso-6a70a9a20fc61.webp', NULL, 17, 1, '2026-08-03 11:28:15');

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
(2, 'Haïdara', 'Nouhoum Chérif', 'Directeur Géneral Adjoint', 'personnel-6a70a981dea28.jpg', 1, 1);

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
(1, 5, 8, 20, 8, 150000.00, 150000.00, 150000.00, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 'especes', '02', NULL, '2026-09-28 20:25:30', '2026-09-28 20:25:30', 7, 'effectue', NULL, '2026-09-28 20:25:30', NULL);

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
(4, 16, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-26 22:07:52', 'nouvelle_date', '10', '2026-09-26 22:08:26', 'choix_recu', NULL, NULL, NULL),
(5, 17, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 00:36:08', 'nouvelle_date', '2026-10-10', '2026-09-28 00:36:53', 'en_traitement', 7, NULL, NULL),
(6, 18, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.', 7, '2026-09-28 14:49:09', 'autre_espace', 'Salle Adama SAMASSEKOU', '2026-09-28 14:50:50', 'cloturee', 7, '2026-09-28 15:51:52', 'Demande de changement d’espace enregistrée et traitée.'),
(7, 19, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 16:07:22', 'nouvelle_date', '2026-09-30', '2026-09-28 16:08:44', 'cloturee', 7, '2026-09-28 16:09:23', 'Demande de nouvelle date enregistrée et traitée.'),
(8, 20, 'Cet espace a été réquisitionné dans le cadre d\'une activité officielle du Gouvernement de la République du Mali.', 7, '2026-09-28 18:18:23', 'remboursement', NULL, '2026-09-28 18:19:40', 'cloturee', 7, '2026-09-28 20:25:30', 'Remboursement effectué par Espèces — montant : 150 000 FCFA — Réf. : 02');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
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

INSERT INTO `reservations` (`id`, `user_id`, `espace_id`, `tarif_id`, `date_resa`, `date_depart`, `date_validation`, `heure_debut`, `heure_fin`, `petit_dejeuner`, `vip`, `canal`, `requisition_id`, `quantite`, `statut`, `statut_paiement`, `date_limite_solde`, `paiement_notifie`, `motif`, `note_admin`, `notification_vue`, `created_at`, `updated_at`) VALUES
(1, 8, 1, 1, '2026-07-28', NULL, '2026-07-27 16:19:47', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'grand debat interuniversitaire', NULL, 0, '2026-07-27 15:58:32', '2026-07-27 23:35:53'),
(2, 8, 8, 27, '2026-07-28', '2026-07-31', '2026-07-27 16:51:49', NULL, NULL, 1, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'Sejour en famille', NULL, 0, '2026-07-27 16:48:47', '2026-07-27 23:35:53'),
(3, 8, 1, 1, '2026-07-30', NULL, '2026-07-27 23:36:00', '07:00:00', '14:30:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'grande conférence sur le climat', NULL, 0, '2026-07-27 23:34:03', '2026-07-28 23:23:18'),
(4, 8, 16, 39, '2026-07-30', NULL, '2026-07-28 23:00:15', '07:00:00', '12:30:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'matfchhhhhhhhhh de gala', NULL, 0, '2026-07-28 22:58:00', '2026-07-28 23:06:56'),
(5, 8, 3, 5, '2026-07-30', NULL, '2026-07-29 08:00:10', '07:00:00', '14:00:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'CCCCCCCCCCCCCCCCCC', NULL, 0, '2026-07-29 07:54:43', '2026-07-29 08:01:18'),
(6, 8, 2, 3, '2026-10-08', NULL, '2026-07-30 02:25:52', '07:00:00', '08:00:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'o^hfsndg,;:', NULL, 0, '2026-07-29 14:30:05', '2026-07-30 02:26:32'),
(7, 8, 8, 27, '2026-10-26', '2026-11-29', '2026-07-30 14:14:10', NULL, NULL, 1, 0, 'en_ligne', NULL, 12, 'validee', 'paye', NULL, 0, 'j\'ai une fete ave des amis', NULL, 0, '2026-07-30 14:03:10', '2026-07-30 14:17:21'),
(8, 8, 1, 1, '2026-07-31', NULL, '2026-07-30 14:14:11', '09:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, 'validee', 'paye', NULL, 0, 'vvvvvvvvvvvvvvvvvvvvvvv', NULL, 0, '2026-07-30 14:10:25', '2026-07-30 14:16:41'),
(9, 8, 14, 35, '2026-07-31', NULL, '2026-07-30 22:18:13', '07:00:00', '15:30:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'paye', NULL, 0, 'xxxxxxxxxxxxddddddcc', NULL, 0, '2026-07-30 22:17:27', '2026-08-10 13:14:22'),
(10, 8, 4, 7, '2026-07-31', '2026-08-02', '2026-07-31 11:37:43', NULL, NULL, 0, 0, 'en_ligne', NULL, 1, 'expiree', 'non_paye', NULL, 0, 'sxxwwwwwwwww', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-07-31 11:37:25', '2026-08-03 11:13:33'),
(11, 8, 14, 35, '2026-08-01', NULL, '2026-08-10 14:11:53', '10:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjjjjjjjj', NULL, 0, '2026-07-31 13:15:35', '2026-08-10 16:06:18'),
(12, 12, 2, 3, '2026-08-04', NULL, '2026-08-01 00:27:03', '09:00:00', '14:00:00', 0, 0, 'guichet', NULL, 1, 'validee', 'paye', NULL, 0, 'Sortie de promotion universitaire', NULL, 0, '2026-08-01 00:27:03', '2026-08-01 00:27:57'),
(13, 8, 13, 34, '2026-08-11', NULL, '2026-08-10 16:12:08', '07:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, 'GHGGGGGGGGGGGGGGGGGGGGGG', NULL, 0, '2026-08-10 16:11:32', '2026-08-10 23:37:31'),
(14, 8, 15, 36, '2026-08-12', NULL, '2026-08-11 00:15:50', '09:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 'expiree', 'non_paye', NULL, 0, 'jjjjjjjjjjjjjjjjjjjjjjjjjjjj', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-08-11 00:14:04', '2026-08-13 12:02:22'),
(15, 8, 2, 3, '2026-09-02', NULL, '2026-08-31 01:43:42', '07:00:00', '16:00:00', 0, 0, 'en_ligne', NULL, 1, 'expiree', 'partiellement_paye', '2026-09-01', 0, 'kjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj', '\n[Auto] Annulée : délai de paiement de 48h dépassé sans encaissement.', 0, '2026-08-31 01:43:17', '2026-09-22 20:52:18'),
(16, 8, 2, 3, '2026-10-06', NULL, '2026-09-26 22:06:56', '11:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, 'konbvgfèhunu', NULL, 0, '2026-09-26 22:06:29', '2026-09-26 22:07:52'),
(17, 8, 15, 36, '2026-10-03', NULL, '2026-09-28 00:35:59', '08:00:00', '18:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, 'conference sur octobre rose', NULL, 0, '2026-09-28 00:35:28', '2026-09-28 00:36:08'),
(18, 8, 1, 1, '2026-10-08', NULL, '2026-09-28 14:49:01', '07:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', NULL, 0, '2026-09-28 14:48:47', '2026-09-28 14:49:09'),
(19, 8, 2, 3, '2026-10-06', NULL, '2026-09-28 16:07:13', '07:00:00', '15:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'non_paye', NULL, 0, ',,,,,,,,,,,,,,,,,,,,,,,,,,,,,', NULL, 0, '2026-09-28 16:06:58', '2026-09-28 16:07:22'),
(20, 8, 14, 35, '2026-10-07', NULL, '2026-09-28 18:17:25', '07:00:00', '17:00:00', 0, 0, 'en_ligne', NULL, 1, 'requisitionnee', 'paye', NULL, 0, 'kpmihhhhhhhhhhhh', NULL, 0, '2026-09-28 18:17:11', '2026-09-28 18:18:23');

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
(29, 9, 'Location journée', 100000.00, 'jour', NULL, 0, NULL, NULL),
(33, 12, 'Location salle de classe', 25000.00, 'jour', 6, 0, NULL, NULL),
(34, 13, 'Événement dans la cour', 200000.00, 'jour', NULL, 0, NULL, NULL),
(35, 14, 'Location journée', 150000.00, 'jour', NULL, 0, NULL, NULL),
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
  `role` enum('user','superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable') NOT NULL DEFAULT 'user',
  `actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Blocage de compte (0 = suspendu)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Comptes utilisateurs publics et administrateurs';

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom_complet`, `email`, `telephone`, `password_hash`, `role`, `actif`, `created_at`) VALUES
(1, 'Administration Système', 'superadmin@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin', 1, '2026-07-27 15:19:28'),
(2, 'Direction Générale', 'dg@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin', 1, '2026-07-27 15:19:28'),
(3, 'Cabinet du Ministre', 'ministre@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'ministre', 1, '2026-07-27 15:19:28'),
(4, 'Administration Espaces', 'admin.espaces@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_espaces', 1, '2026-07-27 15:19:28'),
(5, 'Administration Activités', 'admin.activites@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_activites', 1, '2026-07-27 15:19:28'),
(6, 'Administration Messages', 'admin.messages@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_messages', 1, '2026-07-27 15:19:28'),
(7, 'Service Comptable', 'comptable@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_comptable', 1, '2026-07-27 15:19:28'),
(8, 'Tapa COULIBALY', 'ladytapa@gmail.com', '+22390921120', '$2y$10$DkqbcprqEXz1s32OgDiBDu7xccVubAoFNecCEqCiVbgOmAw9ETYEK', 'user', 1, '2026-07-27 15:56:44'),
(9, 'Seydou COULIBALY', 'seydou@gmail.com', '+22390921120', '$2y$10$4g/vQR7q2ON2s0S38RVPgejOBLG/270s1OXPHkCRKItr93tsaz27.', 'user', 1, '2026-07-29 14:04:34'),
(10, 'mimi CISSÉ', 'mimi@gmail.com', '+22390921120', '$2y$10$ZScYrNh76fYnnFe4sHeFUOpyhw4kFGhTOC/CAbOSUQdsdJFUbOlqa', 'user', 1, '2026-07-30 14:11:02'),
(11, 'tata CISSÉ', 'tata@gmail.com', '+22390921120', '$2y$10$kd9FRcAy4U.PZEApxbfNJ.OG4cBH28ZZG4mIkX3lsZ4OTmr6PJBO6', 'user', 1, '2026-07-31 13:16:35'),
(12, 'Oumou COULIBALY', 'coul@gmail.com', '+22390921120', '$2y$10$qVqwVcE3gltoCQCjSP5Ud.xlx71bf8qcT16TR5EvyuaZ5eVtTvMLm', 'user', 1, '2026-08-01 00:27:02');

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
  ADD KEY `idx_reservation_requisition` (`requisition_id`);

--
-- Index pour la table `services_annexes`
--
ALTER TABLE `services_annexes`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `idx_users_role` (`role`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `formation_galerie`
--
ALTER TABLE `formation_galerie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT pour la table `observations`
--
ALTER TABLE `observations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `operations_requisition`
--
ALTER TABLE `operations_requisition`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `personnalites`
--
ALTER TABLE `personnalites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `remboursements`
--
ALTER TABLE `remboursements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `requisitions_ministerielles`
--
ALTER TABLE `requisitions_ministerielles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `services_annexes`
--
ALTER TABLE `services_annexes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  ADD CONSTRAINT `fk_reservation_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `tarifs`
--
ALTER TABLE `tarifs`
  ADD CONSTRAINT `fk_tarifs_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
