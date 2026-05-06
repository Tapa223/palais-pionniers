-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 07 mai 2026 à 01:12
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
  `nom` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `icone` varchar(10) DEFAULT NULL,
  `image_principale` varchar(255) DEFAULT NULL,
  `sous_titre` varchar(255) DEFAULT NULL,
  `missions` text DEFAULT NULL COMMENT 'JSON ou texte long pour la section réarmer moralement',
  `chiffres_cles` text DEFAULT NULL COMMENT 'JSON pour stocker les stats (ex: 1800 auditeurs)',
  `citation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activites`
--

INSERT INTO `activites` (`id`, `nom`, `slug`, `description`, `icone`, `image_principale`, `sous_titre`, `missions`, `chiffres_cles`, `citation`, `created_at`) VALUES
(9, 'à L\'ECOLE DE LA CITOYENNETE', '-l-ecole-de-la-citoyennete', 'Objectif National : Promouvoir l\'unité nationale et le respect des institutions républicaines dès le plus jeune âge. \r\n---\r\nFormation Civique : Apprentissage des symboles de l\'État, de l\'hymne national et de l\'organisation administrative du pays.\r\nEngagement Social : Encourager la participation active à la vie de la communauté à travers des projets d\'intérêt général et le bénévolat.', NULL, 'hero--l-ecole-de-la-citoyennete-69f5ad7d9ed9a.png', 'Forger le maliden koura', 'Objectif National : Promouvoir l\'unité nationale et le respect des institutions républicaines dès le plus jeune âge.\r\n---\r\nFormation Civique : Apprentissage des symboles de l\'État, de l\'hymne national et de l\'organisation administrative du pays.\r\nEngagement Social : Encourager la participation active à la vie de la communauté à travers des projets d\'intérêt général et le bénévolat.', '+500 Jeunes formés chaque année', NULL, '2026-05-02 07:53:33');

-- --------------------------------------------------------

--
-- Structure de la table `activite_galerie`
--

CREATE TABLE `activite_galerie` (
  `id` int(10) UNSIGNED NOT NULL,
  `activite_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `categorie` varchar(100) DEFAULT NULL,
  `legende` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activite_galerie`
--

INSERT INTO `activite_galerie` (`id`, `activite_id`, `image_path`, `categorie`, `legende`) VALUES
(14, 9, 'gal-69f5ad7db403c.jpeg', 'Echange avec des ministres', ''),
(15, 9, 'gal-69f5ad7db606a.jpeg', 'Echange avec des ministres', ''),
(16, 9, 'gal-69f5ad7db7484.jpeg', 'Echange avec des ministres', ''),
(17, 9, 'gal-69f5ad7dbf92d.jpeg', 'Echange avec des ministres', ''),
(18, 9, 'gal-69f5ad7dc0bfc.jpeg', 'Echange avec des ministres', ''),
(19, 9, 'gal-69f5ad7dc1f4b.jpeg', 'Echange avec des ministres', ''),
(20, 9, 'gal-69f5ad7dc33e3.jpeg', 'Echange avec des ministres', ''),
(21, 9, 'gal-69f5ad7dc499a.jpeg', 'Echange avec des ministres', ''),
(22, 9, 'gal-69f5ad7dc61f3.jpeg', 'Sorties', ''),
(23, 9, 'gal-69f5ad7dc76a6.jpeg', 'Sorties', ''),
(24, 9, 'gal-69f5ad7dc8db0.jpeg', 'Sorties', ''),
(25, 9, 'gal-69f5ad7dca111.jpg', 'Panels', ''),
(26, 9, 'gal-69f5ad7dcb5ba.jpg', 'Panels', ''),
(27, 9, 'gal-69f5ad7dcc780.jpg', 'Panels', ''),
(28, 9, 'gal-69f5ad7dcda72.jpeg', 'Panels', ''),
(29, 9, 'gal-69f5ad7dced07.jpg', 'Panels', ''),
(30, 9, 'gal-69f5ad7dd024d.jpg', 'Panels', ''),
(31, 9, 'gal-69f5ad7dd145f.jpg', 'Panels', ''),
(32, 9, 'gal-69f5ad7dd266c.jpeg', 'Panels', '');

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(60) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `slug`, `nom`) VALUES
(1, 'amphitheatre', 'Amphithéâtre'),
(2, 'conference', 'Salle de conférence'),
(3, 'sport', 'Sport'),
(4, 'hebergement', 'Hébergement'),
(5, 'loisir', 'Loisir'),
(6, 'restauration', 'Restauration');

-- --------------------------------------------------------

--
-- Structure de la table `espaces`
--

CREATE TABLE `espaces` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `categorie_id` int(10) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `capacite` varchar(100) NOT NULL,
  `tarif` decimal(10,2) DEFAULT 0.00,
  `equipements` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `espaces`
--

INSERT INTO `espaces` (`id`, `slug`, `nom`, `categorie_id`, `description`, `capacite`, `tarif`, `equipements`, `image`, `disponible`, `created_at`) VALUES
(1, 'salle-seydou-badian', 'Salle Seydou BADIAN', 1, 'Amphithéâtre prestigieux de 400 places. Idéal pour les conférences de haut niveau, les cérémonies officielles et les grands événements culturels du Mali.', '400', 0.00, 'Climatisation centrale, Sonorisation pro, Vidéoprojecteur, Loges VIP, Scène', NULL, 1, '2026-04-28 16:09:36'),
(2, 'salle-sory-ibrahim-koita-dit-bomba', 'Salle Sory Ibrahim KOITA dit Bomba', 1, 'Grand amphithéâtre de 420 places. Espace polyvalent conçu pour les projections cinématographiques, les spectacles vivants et les assemblées générales.', '420 places', 0.00, 'Écran géant, Éclairage scénique, Sonorisation, Climatisation', NULL, 1, '2026-04-28 16:09:36'),
(4, 'terrain-de-maracana', 'Terrain de Maracana', 3, 'Espace sportif extérieur dédié au football de petite surface. Accueille les séances d\'entraînement, les matchs de gala et les tournois inter-entreprises.', '22 joueurs', 0.00, 'Projecteurs nocturnes, Vestiaires, Bancs de touche', NULL, 1, '2026-04-28 16:09:36'),
(5, 'centre-tidiani-coulibaly', 'Centre Tidiani COULIBALY', 4, 'Hébergement de qualité pour délégations et stagiaires. Comprend des chambres climatisées confortables (Résidence Ely Abdoulaye DIALLO) et des chambres ventilées.', 'Multiples chambres', 0.00, 'Literie complète, Climatisation ou ventilateur, Petit-déjeuner inclus (confort)', NULL, 1, '2026-04-28 16:09:36'),
(6, 'piscine', 'Piscine', 5, 'Cadre relaxant pour les activités aquatiques et les événements sociaux (anniversaires, réceptions). Propose des cours de natation pour enfants et adultes.', 'Événementiel', 0.00, 'Bassin surveillé, Transats, Vestiaires, Espace buffet', NULL, 1, '2026-04-28 16:09:36'),
(7, 'terrain-de-basketball', 'Terrain de Basketball', 3, 'Terrain de basketball extérieur aux normes réglementaires. Disponible pour les entraînements de club, les matchs de compétition et les séances de loisir.', 'Equipes de club', 0.00, 'Paniers réglables, Marquage au sol, Éclairage de soirée', NULL, 1, '2026-05-01 10:12:24'),
(8, 'salle-daba-modibo-keita', 'Salle Daba Modibo KEITA', 3, 'Complexe sportif couvert incluant une salle de gymnastique, un dojo pour le Karaté/Taekwondo et un espace multifonction pour les arts martiaux.', 'Variable', 0.00, 'Tapis de sol, Équipements de gym, Gradins', NULL, 1, '2026-05-01 10:12:24');

-- --------------------------------------------------------

--
-- Structure de la table `espace_images`
--

CREATE TABLE `espace_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `chemin` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `espace_images`
--

INSERT INTO `espace_images` (`id`, `espace_id`, `chemin`, `created_at`) VALUES
(6, 6, 'espace_6_69f3aa54361b5.JPG', '2026-04-30 19:15:32'),
(8, 8, 'esp_8_69f4ab2551cf9.jpeg', '2026-05-01 13:31:17'),
(9, 8, 'esp_8_69f4ab555e608.jpeg', '2026-05-01 13:32:05'),
(11, 8, 'esp_8_69f57f4113769.jpeg', '2026-05-02 04:36:17'),
(12, 8, 'esp_8_69f57f4116aab.jpeg', '2026-05-02 04:36:17'),
(13, 8, 'esp_8_69f57f4118fdd.jpeg', '2026-05-02 04:36:17'),
(14, 8, 'esp_8_69f57f411c2a6.jpeg', '2026-05-02 04:36:17'),
(16, 5, 'esp_5_69f57f971d63e.jpeg', '2026-05-02 04:37:43'),
(17, 5, 'esp_5_69f57f9722a20.jpeg', '2026-05-02 04:37:43'),
(18, 5, 'esp_5_69f57f9724f19.jpeg', '2026-05-02 04:37:43'),
(19, 5, 'esp_5_69f57f9726303.jpeg', '2026-05-02 04:37:43'),
(20, 2, 'esp_2_69f5810bdba3e.jpeg', '2026-05-02 04:43:55'),
(21, 7, 'esp_7_69f58b0eb5639.jpeg', '2026-05-02 05:26:38'),
(41, 1, 'esp_1_69f5914702d8d.jpeg', '2026-05-02 05:53:11'),
(42, 1, 'esp_1_69f591470578c.jpeg', '2026-05-02 05:53:11'),
(52, 4, 'esp_4_69f59a3cdda03.jpeg', '2026-05-02 06:31:24');

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `tarif_id` int(11) UNSIGNED DEFAULT NULL,
  `date_resa` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `motif` text NOT NULL,
  `statut` enum('en_attente','validee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  `note_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notification_vue` tinyint(1) DEFAULT 0
) ;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `espace_id`, `tarif_id`, `date_resa`, `heure_debut`, `heure_fin`, `motif`, `statut`, `note_admin`, `created_at`, `updated_at`, `notification_vue`) VALUES
(1, 2, 2, NULL, '2026-04-30', '07:00:00', '14:00:00', 'conference', 'validee', NULL, '2026-04-28 23:52:12', '2026-04-29 14:25:32', 0),
(2, 2, 1, NULL, '2026-05-07', '08:00:00', '18:00:00', 'panel', 'en_attente', NULL, '2026-04-30 21:41:30', '2026-04-30 21:41:30', 1),
(3, 2, 1, NULL, '2026-05-07', '08:00:00', '18:00:00', 'panel', 'en_attente', NULL, '2026-04-30 21:41:38', '2026-04-30 21:41:38', 1),
(4, 2, 1, NULL, '2026-05-07', '08:00:00', '18:00:00', 'panel', 'validee', NULL, '2026-04-30 21:42:32', '2026-04-30 21:51:55', 1),
(5, 5, 1, NULL, '2026-05-07', '08:00:00', '18:00:00', 'conf', 'refusee', NULL, '2026-04-30 21:50:31', '2026-04-30 21:51:52', 1),
(6, 2, 1, NULL, '2026-05-29', '08:00:00', '18:00:00', 'conf\r\n', 'en_attente', NULL, '2026-04-30 22:39:11', '2026-04-30 22:39:11', 1),
(7, 2, 4, NULL, '2026-05-02', '08:00:00', '18:00:00', 'foot entre amis', 'validee', NULL, '2026-05-01 00:38:53', '2026-05-01 00:40:01', 0),
(8, 6, 2, NULL, '2026-05-30', '08:00:00', '18:00:00', 'recreative', 'en_attente', NULL, '2026-05-01 08:02:02', '2026-05-01 08:02:02', 1),
(11, 7, 1, 2, '2026-05-15', '08:00:00', '18:00:00', '', 'validee', NULL, '2026-05-01 15:03:42', '2026-05-01 15:04:20', 0),
(12, 8, 8, 20, '2026-05-02', '08:00:00', '18:00:00', '', 'validee', NULL, '2026-05-01 17:50:30', '2026-05-01 17:51:13', 0),
(13, 9, 8, 20, '2026-05-02', '08:00:00', '18:00:00', '', 'validee', NULL, '2026-05-01 21:30:35', '2026-05-01 21:31:45', 0),
(14, 6, 2, 3, '2026-05-15', '08:00:00', '18:00:00', '', 'en_attente', NULL, '2026-05-02 04:30:19', '2026-05-02 04:30:19', 0),
(15, 6, 1, 2, '2026-05-22', '08:00:00', '18:00:00', '', 'en_attente', NULL, '2026-05-02 07:05:10', '2026-05-02 07:05:10', 0),
(16, 6, 1, 2, '2026-05-09', '08:00:00', '18:00:00', '', 'validee', NULL, '2026-05-02 09:59:55', '2026-05-02 10:00:41', 0);

-- --------------------------------------------------------

--
-- Structure de la table `tarifs`
--

CREATE TABLE `tarifs` (
  `id` int(10) UNSIGNED NOT NULL,
  `espace_id` int(10) UNSIGNED NOT NULL,
  `libelle` varchar(120) NOT NULL,
  `montant` int(10) UNSIGNED NOT NULL COMMENT 'Montant en F CFA',
  `unite` varchar(40) NOT NULL DEFAULT 'jour'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tarifs`
--

INSERT INTO `tarifs` (`id`, `espace_id`, `libelle`, `montant`, `unite`) VALUES
(1, 1, 'Location Journée complète', 400000, 'jour'),
(2, 1, 'Salle VVIP (Seydou Badian)', 150000, 'jour'),
(3, 2, 'Location Journée complète', 400000, 'jour'),
(6, 4, 'Séance d\'entraînement', 10000, 'heure'),
(7, 4, 'Match de Gala', 20000, 'match'),
(8, 4, 'Location en bail mensuel', 500000, 'mois'),
(9, 7, 'Séance d\'entraînement', 10000, 'séance'),
(10, 7, 'Match de Gala', 50000, 'match'),
(11, 7, 'Location en bail mensuel', 300000, 'mois'),
(12, 5, 'Chambre unique ventilée sans douche', 5000, 'nuitée'),
(13, 5, 'Chambre unique avec douche intérieure', 10000, 'nuitée'),
(14, 5, 'Chambre climatisée (Résidence Ely Diallo)', 30000, 'nuitée'),
(15, 6, 'Location pour événement/dîner', 750000, 'jour'),
(16, 6, 'Natation Enfant (par jour)', 1000, 'jour'),
(17, 6, 'Natation Adulte (par jour)', 1500, 'jour'),
(18, 6, 'Inscription mensuelle', 10000, 'mois'),
(19, 8, 'Location Salle de Gymnastique', 50000, 'mois'),
(20, 8, 'Salle Karaté/Taekwondo (Inscription)', 15000, 'mois');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom_complet` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','superadmin','admin_espaces','admin_activites') NOT NULL DEFAULT 'user',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom_complet`, `email`, `telephone`, `password_hash`, `role`, `actif`, `created_at`) VALUES
(1, 'Administrateur Palais', 'admin@palaisdespionniers.ml', NULL, '$2b$10$NVW.VDw/e5qWPuM//9yN2OIjE6Bvz/5tWNwqToAQL/N/THe17rsmy', 'superadmin', 1, '2026-04-28 16:09:36'),
(2, 'Coulibaly Tapa', 'coulibalytapa260@gmail.com', '90921120', '$2y$10$dlq0WD5tznXvehezeWcepuH2tBFPi1hQWeAIiv4Ans1ocMrtF3REa', '', 0, '2026-04-28 16:42:50'),
(3, 'ladytapa', 'tapacoulibaly260@gmail.com', '67666777', '$2y$10$xcae6bPWeIhc.ZrEPsIhuuzKkMzk6j6eX2N.Mw5MBo3jZItHZtAPy', 'user', 0, '2026-04-29 15:15:54'),
(4, 'Mariam CISSE', 'cissmaria223@gmail.com', '77169916', '$2y$10$TfO/H6WhPtrG04JkCuR14OL4afd.WXR67OrwhS/DcqPvzKnRRjuSi', 'user', 1, '2026-04-30 21:25:08'),
(5, 'Mariam CISSE', 'mirachou223@gmail.com', '77169916', '$2y$10$pH8lZ7gofVCmzqgKbIBOZ.IZd526o3hTp/rR7i86yFc0iA1ZrGTdK', 'user', 1, '2026-04-30 21:50:01'),
(6, 'Mariam CISSE', 'awiche223@gmail.com', '77169916', '$2y$10$uMiOOxp46V0V8HoTT4EweOB6.AXQmLSQ2DDxJ/P80PQD52W9y.xzy', 'user', 1, '2026-05-01 08:01:16'),
(7, 'Awa Samake', 'awa@gmail.com', '90921120', '$2y$10$hhhHMBUPWMIM3xjzPlzbOukrV3ZQrxptbdYrInaU6Xta74BHcLxKW', 'user', 1, '2026-05-01 15:02:54'),
(8, 'Tapa Coulibaly', 'coulibaly@gmail.com', '90921120', '$2y$10$c33atIcPznawKqK.m/yfW.iWAzC9z3VkO7Bl9M8fFPShZfZZToh8a', 'user', 1, '2026-05-01 17:48:59'),
(9, 'Hamid Seynou', 'hamid@gmail.com', '67666777', '$2y$10$r.Rtd8nKGDDrNWbyaqfF1u1qM/ib3AQSz7oG3kIveCsU41zOCLXme', 'user', 1, '2026-05-01 21:29:43'),
(10, 'Adminisatreur Activité', 'admin_activite@palaisdespionniers.ml', '90921120', '$2y$10$6DbMirb3r8vcZWizmC/zRuBLbztq8kfU3tO1iGBHcOTnur19TspUG', 'admin_activites', 1, '2026-05-02 00:03:30');

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
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

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
-- Index pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resa_user` (`user_id`),
  ADD KEY `idx_resa_espace` (`espace_id`),
  ADD KEY `idx_resa_date` (`date_resa`),
  ADD KEY `idx_resa_statut` (`statut`),
  ADD KEY `fk_reservation_tarif` (`tarif_id`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `activite_galerie`
--
ALTER TABLE `activite_galerie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `espaces`
--
ALTER TABLE `espaces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `espace_images`
--
ALTER TABLE `espace_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activite_galerie`
--
ALTER TABLE `activite_galerie`
  ADD CONSTRAINT `fk_activite_galerie_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `espaces`
--
ALTER TABLE `espaces`
  ADD CONSTRAINT `fk_espaces_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`);

--
-- Contraintes pour la table `espace_images`
--
ALTER TABLE `espace_images`
  ADD CONSTRAINT `fk_images_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_resa_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
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
