-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 07:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bd_final`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `categorie` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `categorie`) VALUES
(1, 'administrative'),
(2, 'technique');

-- --------------------------------------------------------

--
-- Table structure for table `commentaires`
--

CREATE TABLE `commentaires` (
  `id` int(11) NOT NULL,
  `reclamation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `date_commentaire` date NOT NULL,
  `lu` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commentaires`
--

INSERT INTO `commentaires` (`id`, `reclamation_id`, `user_id`, `message`, `date_commentaire`, `lu`) VALUES
(2, 5, 9, 'iefgiehgu', '2025-12-10', 1),
(3, 9, 11, 'envois icon de ne pas connecte a internet', '2025-12-12', 1),
(4, 9, 11, 'envoi capture de ecran de pc', '2025-12-12', 1),
(5, 9, 11, 'rrr', '2025-12-12', 1),
(6, 9, 11, 'envoi', '2025-12-12', 1);

-- --------------------------------------------------------

--
-- Table structure for table `gestionnaires`
--

CREATE TABLE `gestionnaires` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gestionnaires`
--

INSERT INTO `gestionnaires` (`id`, `user_id`, `categorie_id`) VALUES
(1, 9, 1),
(3, 11, 2);

-- --------------------------------------------------------

--
-- Table structure for table `pieces_jointes`
--

CREATE TABLE `pieces_jointes` (
  `id` int(11) NOT NULL,
  `reclamation_id` int(11) NOT NULL,
  `chemin_fichier` varchar(250) NOT NULL,
  `type_mime` varchar(10) DEFAULT NULL,
  `date_ajout` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pieces_jointes`
--

INSERT INTO `pieces_jointes` (`id`, `reclamation_id`, `chemin_fichier`, `type_mime`, `date_ajout`) VALUES
(3, 5, 'uploads/reclamations/pj_693841c0404d41.83015485.png', 'image/png', '2025-12-09'),
(4, 9, 'uploads/reclamations/pj_693c66f63f8c77.93455549.png', 'image/png', '2025-12-12');

-- --------------------------------------------------------

--
-- Table structure for table `reclamations`
--

CREATE TABLE `reclamations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `objet` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `date_soumission` date NOT NULL,
  `statut_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reclamations`
--

INSERT INTO `reclamations` (`id`, `user_id`, `categorie_id`, `objet`, `description`, `date_soumission`, `statut_id`) VALUES
(5, 1, 1, 'test', 'hhhh', '2025-12-09', 4),
(6, 1, 1, 'test', 'setsetet', '2025-12-10', 1),
(7, 1, 2, 'hfieofize', 'egsgesg', '2025-12-10', 1),
(8, 1, 1, 'kesopk fkopezk', 'egegrthrt', '2025-12-10', 3),
(9, 1, 2, 'wifi ne pas travail', 'wifi dans salle 3 ne pas travail et aussi cable port rj45 dans la tere', '2025-12-12', 1);

-- --------------------------------------------------------

--
-- Table structure for table `statuts`
--

CREATE TABLE `statuts` (
  `id` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `statuts`
--

INSERT INTO `statuts` (`id`, `statut`) VALUES
(1, 'Acceptée'),
(2, 'Fermée'),
(3, 'En cours de traitement'),
(4, 'En attente d’informations');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','Réclamant','agent','') NOT NULL,
  `mot_de_passe` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `role`, `mot_de_passe`) VALUES
(1, 'reclamant', 'reclamant@email.com', 'Réclamant', '$2y$10$iIcAHnS8qJxXVEXesneiUOpIVpC72NBKx7LGfb/ee355zfmCOjH1m'),
(2, 'admin', 'admin@email.com', 'admin', '$2a$12$Mmi9v/iF0Dkz9U52SQpqzu1vpPlImbT5Nu/qUvy0ICRdvOrDNkGlG'),
(9, 'gestion', 'gestion@email.com', 'agent', '$2y$10$BribPiMnGc4qEWaernE0SOehyAskYgU2s8prWp5bqxC8Sxe7C4GGi'),
(11, 'anas', 'anas@gmail.com', 'agent', '$2y$10$8TWAY2aMDkeMjAVntBsTru7DpVoNDkQK5QPPpVWzFaJ18tPpY4nhS');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commentaires`
--
ALTER TABLE `commentaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comment_reclamation` (`reclamation_id`),
  ADD KEY `fk_comment_user` (`user_id`);

--
-- Indexes for table `gestionnaires`
--
ALTER TABLE `gestionnaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gestionnaires_user` (`user_id`),
  ADD KEY `fk_gestionnaires_categorie` (`categorie_id`);

--
-- Indexes for table `pieces_jointes`
--
ALTER TABLE `pieces_jointes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pj_reclamation` (`reclamation_id`);

--
-- Indexes for table `reclamations`
--
ALTER TABLE `reclamations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reclamations_user` (`user_id`),
  ADD KEY `fk_reclamations_categorie` (`categorie_id`),
  ADD KEY `fk_reclamations_statut` (`statut_id`);

--
-- Indexes for table `statuts`
--
ALTER TABLE `statuts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `commentaires`
--
ALTER TABLE `commentaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gestionnaires`
--
ALTER TABLE `gestionnaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pieces_jointes`
--
ALTER TABLE `pieces_jointes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reclamations`
--
ALTER TABLE `reclamations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `statuts`
--
ALTER TABLE `statuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `commentaires`
--
ALTER TABLE `commentaires`
  ADD CONSTRAINT `fk_comment_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `gestionnaires`
--
ALTER TABLE `gestionnaires`
  ADD CONSTRAINT `fk_gestionnaires_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_gestionnaires_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pieces_jointes`
--
ALTER TABLE `pieces_jointes`
  ADD CONSTRAINT `fk_pj_reclamation` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reclamations`
--
ALTER TABLE `reclamations`
  ADD CONSTRAINT `fk_reclamations_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_reclamations_statut` FOREIGN KEY (`statut_id`) REFERENCES `statuts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reclamations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
