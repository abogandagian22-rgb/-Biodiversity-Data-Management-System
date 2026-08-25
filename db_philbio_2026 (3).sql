-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2026 at 09:04 AM
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
-- Database: `db_philbio_2026`
--

-- --------------------------------------------------------

--
-- Table structure for table `archive_bat_species`
--

CREATE TABLE `archive_bat_species` (
  `species_id` int(11) NOT NULL,
  `species_code` varchar(50) DEFAULT NULL,
  `common_name` varchar(150) DEFAULT NULL,
  `scientific_name` varchar(150) DEFAULT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `iucn_status` varchar(50) DEFAULT NULL,
  `denr_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archive_bat_species`
--

INSERT INTO `archive_bat_species` (`species_id`, `species_code`, `common_name`, `scientific_name`, `classification`, `iucn_status`, `denr_status`) VALUES
(1, 'q', 'qq', 'q', 'E', 'CR', 'EN'),
(2, 'fse', 'Philippine Pygmy Woodpecker', 'Yungipicus maculatus', 'Chiroptera', 'EN', 'EN'),
(5, 'awdawd', 'awdadad', 'dada', 'Pteropodidae', 'NT', 'CR');

-- --------------------------------------------------------

--
-- Table structure for table `archive_bird_species`
--

CREATE TABLE `archive_bird_species` (
  `species_id` int(11) NOT NULL COMMENT 'Unique ID for each bird species',
  `species_code` varchar(50) DEFAULT NULL COMMENT 'Official species code used in surveys',
  `common_name` varchar(255) DEFAULT NULL COMMENT 'Common/local name of bird',
  `scientific_name` varchar(255) DEFAULT NULL COMMENT 'Scientific name of species',
  `classification` enum('RR','E','M','I','NSS') DEFAULT NULL COMMENT 'RR=Restricted-range, E=Endemic, M=Migratory, I=Non, NSS=Not Species-specific',
  `classification_text` varchar(255) DEFAULT NULL,
  `iucn_status` enum('EX','EW','CR','EN','VU','NT','LC','DD','NE') DEFAULT NULL COMMENT 'IUCN Red List conservation status',
  `denr_status` enum('CR','EN','VU','OTS','NL') DEFAULT NULL COMMENT 'DENR DAO conservation status'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master list of bird species used in all surveys';

--
-- Dumping data for table `archive_bird_species`
--

INSERT INTO `archive_bird_species` (`species_id`, `species_code`, `common_name`, `scientific_name`, `classification`, `classification_text`, `iucn_status`, `denr_status`) VALUES
(1, 'ewfef', 'ef', 'efwf', '', NULL, '', ''),
(2, 'wdwa', 'wda', 'wdd', '', NULL, '', ''),
(3, 'fse', 'efs', 'efs', 'NSS', 'E', 'DD', 'EN'),
(4, 'ad', 'da', 'a', 'NSS', NULL, 'VU', 'VU'),
(5, 'fea', 'ae', 'ef', 'NSS', NULL, 'DD', 'EN'),
(6, 'xzz', 'xz', 'ssfz', 'NSS', 'Picidae', 'DD', 'EN'),
(7, 'vc', 'vcv', 'cv', 'NSS', 'Aves', 'LC', 'VU'),
(8, 'ess', 'sefsfs', 'fsfsf', 'NSS', 'Piciformes', 'NT', 'OTS'),
(9, 'sfsf', 'fsfs', 'sefsfs', 'NSS', 'Piciformes', 'EN', 'EN'),
(10, 'fsf', 'fsafs', 'sfsf', 'NSS', 'Picidae', 'NT', 'VU'),
(11, 'ess', 'hbbibbi', 'bgyguy', 'NSS', 'Piciformes', 'NT', 'VU'),
(12, 'awda', 'awda', 'awdad', 'NSS', 'Picidae', 'VU', 'VU'),
(13, 'wadadad', 'dadad', 'dadad', 'NSS', 'Aves', 'NT', 'EN'),
(16, 'awdad', 'dadad', 'dada', 'NSS', 'Piciformes', 'VU', 'EN');

-- --------------------------------------------------------

--
-- Table structure for table `archive_flora`
--

CREATE TABLE `archive_flora` (
  `record_id` int(11) NOT NULL,
  `local_name` varchar(255) DEFAULT NULL COMMENT 'Local/common name',
  `scientific_name` varchar(255) NOT NULL COMMENT 'Scientific name',
  `family_name` varchar(255) DEFAULT NULL COMMENT 'Family name',
  `iucn_status` enum('EX','EW','CR','EN','VU','NT','LC','DD','NE') NOT NULL COMMENT 'IUCN Red List Status',
  `denr_status` enum('CR','EN','VU','OTS','NL') NOT NULL COMMENT 'DENR DAO Status',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes or observations'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archive_flora`
--

INSERT INTO `archive_flora` (`record_id`, `local_name`, `scientific_name`, `family_name`, `iucn_status`, `denr_status`, `remarks`) VALUES
(1, 'da', 'aw', 'w', 'EN', 'EN', 'awd'),
(2, 'vx', 'xc', 'd', '', '', 'cefsfs'),
(3, 'cvvc', 'vc', 'vc', 'VU', 'VU', '');

-- --------------------------------------------------------

--
-- Table structure for table `archive_transects`
--

CREATE TABLE `archive_transects` (
  `transect_id` int(11) NOT NULL COMMENT 'Unique ID for each transect survey',
  `transect_name` varchar(255) DEFAULT NULL COMMENT 'Name or label of transect',
  `location` varchar(255) DEFAULT NULL COMMENT 'Survey location',
  `survey_date` date DEFAULT NULL COMMENT 'Date when transect was conducted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores bird survey transect events';

--
-- Dumping data for table `archive_transects`
--

INSERT INTO `archive_transects` (`transect_id`, `transect_name`, `location`, `survey_date`) VALUES
(1, 'dadadawd', 'dwa', '1000-10-10'),
(3, 'sefsf', 'fsfsf', '0011-11-11');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL COMMENT 'Unique log entry ID',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID of the user who performed the action',
  `action_type` varchar(50) DEFAULT NULL COMMENT 'Type of action: INSERT, UPDATE, DELETE',
  `table_name` varchar(100) DEFAULT NULL COMMENT 'Name of the affected table',
  `record_id` int(11) DEFAULT NULL COMMENT 'ID of the affected record',
  `action_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the action occurred',
  `old_data` text DEFAULT NULL COMMENT 'Data before change (for UPDATE/DELETE)',
  `new_data` text DEFAULT NULL COMMENT 'Data after change (for INSERT/UPDATE)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks all changes made in the database for auditing purposes';

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `table_name`, `record_id`, `action_time`, `old_data`, `new_data`) VALUES
(3, NULL, 'ARCHIVE', 'bird_species', 7, '2026-03-23 11:27:12', NULL, NULL),
(4, NULL, 'ARCHIVE', 'bird_species', 9, '2026-05-10 21:53:58', NULL, NULL),
(5, NULL, 'ARCHIVE', 'bird_transects', 3, '2026-05-10 21:57:45', NULL, NULL),
(6, NULL, 'ARCHIVE', 'flora_tawi', 3, '2026-05-10 22:21:08', NULL, NULL),
(7, NULL, 'INSERT', 'bird_species', 12, '2026-05-10 22:21:24', NULL, NULL),
(8, NULL, 'INSERT', 'bird_species', 13, '2026-05-10 22:23:51', NULL, NULL),
(9, NULL, 'INSERT', 'bird_species', 14, '2026-05-10 22:26:27', NULL, NULL),
(10, NULL, 'INSERT', 'bird_species', 15, '2026-05-10 22:27:11', NULL, NULL),
(11, NULL, 'INSERT', 'bird_species', 16, '2026-05-10 22:27:20', NULL, NULL),
(12, NULL, 'INSERT', 'bird_species', 17, '2026-05-10 22:27:29', NULL, NULL),
(13, NULL, 'INSERT', 'bat_species', 4, '2026-05-10 22:30:08', NULL, NULL),
(14, NULL, 'INSERT', 'bat_species', 5, '2026-05-10 22:32:00', NULL, NULL),
(15, NULL, 'INSERT', 'bird_species', 18, '2026-05-10 22:36:00', NULL, NULL),
(16, NULL, 'INSERT', 'bird_species', 19, '2026-05-10 22:36:10', NULL, NULL),
(17, NULL, 'ARCHIVE', 'bird_species', 10, '2026-05-10 22:38:50', NULL, NULL),
(18, NULL, 'ARCHIVE', 'bird_species', 13, '2026-05-10 22:39:00', NULL, NULL),
(19, NULL, 'ARCHIVE', 'bird_species', 16, '2026-05-10 22:39:06', NULL, NULL),
(20, NULL, 'ARCHIVE', 'bird_species', 8, '2026-05-10 22:39:18', NULL, NULL),
(21, NULL, 'ARCHIVE', 'flora_tawi', 2, '2026-05-10 22:39:25', NULL, NULL),
(22, NULL, 'ARCHIVE', 'bat_species', 5, '2026-05-10 22:39:42', NULL, NULL),
(23, NULL, 'INSERT', 'bird_species', 20, '2026-05-10 22:52:26', NULL, NULL),
(24, NULL, 'INSERT', 'bird_species', 21, '2026-05-10 22:52:35', NULL, NULL),
(25, NULL, 'ARCHIVE', 'bird_species', 11, '2026-05-10 22:55:09', NULL, NULL),
(26, NULL, 'INSERT', 'bird_species', 22, '2026-05-10 22:56:45', NULL, NULL),
(27, NULL, 'INSERT', 'bird_species', 23, '2026-05-10 22:56:52', NULL, NULL),
(28, NULL, 'ARCHIVE', 'bird_species', 12, '2026-05-10 22:57:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bats_measurements`
--

CREATE TABLE `bats_measurements` (
  `bat_id` int(11) NOT NULL,
  `species_id` int(11) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `age` varchar(20) DEFAULT NULL,
  `forearm` float DEFAULT NULL,
  `hindfoot` float DEFAULT NULL,
  `ear` float DEFAULT NULL,
  `tail` float DEFAULT NULL,
  `total_length` float DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `net_line` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bats_measurements`
--

INSERT INTO `bats_measurements` (`bat_id`, `species_id`, `sex`, `age`, `forearm`, `hindfoot`, `ear`, `tail`, `total_length`, `weight`, `net_line`, `remarks`) VALUES
(1, NULL, '', '', 45, 4545, 45, 44, 2, 454, '45', 'afe');

-- --------------------------------------------------------

--
-- Table structure for table `bat_species`
--

CREATE TABLE `bat_species` (
  `species_id` int(11) NOT NULL,
  `species_code` varchar(50) DEFAULT NULL,
  `common_name` varchar(150) DEFAULT NULL,
  `scientific_name` varchar(150) DEFAULT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `iucn_status` varchar(50) DEFAULT NULL,
  `denr_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bat_species`
--

INSERT INTO `bat_species` (`species_id`, `species_code`, `common_name`, `scientific_name`, `classification`, `iucn_status`, `denr_status`) VALUES
(3, 'sefsf', 'sefsef', 'fsefsefsfsef', 'Pteropodidae', 'NT', 'OTS'),
(4, 'dawdawd', 'adad', 'adad', 'Chiroptera', 'NT', 'EN');

-- --------------------------------------------------------

--
-- Table structure for table `bird_observations`
--

CREATE TABLE `bird_observations` (
  `observation_id` int(11) NOT NULL COMMENT 'Unique observation record ID',
  `transect_id` int(11) DEFAULT NULL COMMENT 'Links to transect where observation was recorded',
  `species_id` int(11) DEFAULT NULL COMMENT 'Links to bird species table',
  `number_of_individuals` int(11) DEFAULT NULL COMMENT 'Count of birds observed',
  `distance` varchar(100) DEFAULT NULL COMMENT 'Distance from observer',
  `time_observed` time DEFAULT NULL COMMENT 'Time of sighting',
  `sex` varchar(50) DEFAULT NULL COMMENT 'Sex of individual (if identified)',
  `age` varchar(50) DEFAULT NULL COMMENT 'Age class (juvenile/adult)',
  `activity` varchar(100) DEFAULT NULL COMMENT 'Behavior observed (feeding, flying, etc.)',
  `food_species` varchar(255) DEFAULT NULL COMMENT 'Food or plant species associated',
  `remarks` text DEFAULT NULL COMMENT 'Additional field notes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Raw bird observation data collected during transects';

--
-- Dumping data for table `bird_observations`
--

INSERT INTO `bird_observations` (`observation_id`, `transect_id`, `species_id`, `number_of_individuals`, `distance`, `time_observed`, `sex`, `age`, `activity`, `food_species`, `remarks`) VALUES
(1, 1, 5, 1, 'c0', '00:05:00', 'male', '12', 'g', 'c', 'cvc');

-- --------------------------------------------------------

--
-- Table structure for table `bird_species`
--

CREATE TABLE `bird_species` (
  `species_id` int(11) NOT NULL COMMENT 'Unique ID for each bird species',
  `species_code` varchar(50) DEFAULT NULL COMMENT 'Official species code used in surveys',
  `common_name` varchar(255) DEFAULT NULL COMMENT 'Common/local name of bird',
  `scientific_name` varchar(255) DEFAULT NULL COMMENT 'Scientific name of species',
  `classification` enum('RR','E','M','I','NSS') DEFAULT NULL COMMENT 'RR=Restricted-range, E=Endemic, M=Migratory, I=Non, NSS=Not Species-specific',
  `classification_text` varchar(255) DEFAULT NULL,
  `iucn_status` enum('EX','EW','CR','EN','VU','NT','LC','DD','NE') DEFAULT NULL COMMENT 'IUCN Red List conservation status',
  `denr_status` enum('CR','EN','VU','OTS','NL') DEFAULT NULL COMMENT 'DENR DAO conservation status'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master list of bird species used in all surveys';

--
-- Dumping data for table `bird_species`
--

INSERT INTO `bird_species` (`species_id`, `species_code`, `common_name`, `scientific_name`, `classification`, `classification_text`, `iucn_status`, `denr_status`) VALUES
(5, 'fea', 'ae', 'ef', 'NSS', NULL, 'DD', 'EN'),
(14, 'sefs', 'sfsf', 'sfsf', 'NSS', 'Piciformes', 'VU', 'VU'),
(15, 'adwad', 'da', 'fsfsf', 'NSS', 'Piciformes', 'VU', 'VU'),
(17, 'adad', 'dada', 'dada', 'NSS', 'Picidae', 'DD', 'OTS'),
(18, 'sfsf', 'adad', 'adad', 'NSS', 'Piciformes', 'VU', 'VU'),
(19, 'adad', 'ad', 'fsfsf', 'NSS', 'Piciformes', 'VU', 'VU'),
(20, 'wdada', 'adad', 'dad', 'NSS', 'Aves', 'VU', 'OTS'),
(21, 'dwa', 'ada', 'awdad', 'NSS', 'Piciformes', 'EN', 'EN'),
(22, 'fgd', 'gssf', 'sfsfsfs', 'NSS', 'Aves', 'LC', 'NL'),
(23, 'sfesf', 'fsf', 'fse', 'NSS', 'Aves', 'LC', 'NL');

-- --------------------------------------------------------

--
-- Table structure for table `bird_transects`
--

CREATE TABLE `bird_transects` (
  `transect_id` int(11) NOT NULL COMMENT 'Unique ID for each transect survey',
  `transect_name` varchar(255) DEFAULT NULL COMMENT 'Name or label of transect',
  `location` varchar(255) DEFAULT NULL COMMENT 'Survey location',
  `survey_date` date DEFAULT NULL COMMENT 'Date when transect was conducted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores bird survey transect events';

--
-- Dumping data for table `bird_transects`
--

INSERT INTO `bird_transects` (`transect_id`, `transect_name`, `location`, `survey_date`) VALUES
(1, 'dadadawd', 'dwa', '1000-10-10'),
(2, 'fefsfsefdad', 'esfsffsfse', '2026-11-11');

-- --------------------------------------------------------

--
-- Table structure for table `flora_tawi`
--

CREATE TABLE `flora_tawi` (
  `record_id` int(11) NOT NULL,
  `local_name` varchar(255) DEFAULT NULL COMMENT 'Local/common name',
  `scientific_name` varchar(255) NOT NULL COMMENT 'Scientific name',
  `family_name` varchar(255) DEFAULT NULL COMMENT 'Family name',
  `iucn_status` enum('EX','EW','CR','EN','VU','NT','LC','DD','NE') NOT NULL COMMENT 'IUCN Red List Status',
  `denr_status` enum('CR','EN','VU','OTS','NL') NOT NULL COMMENT 'DENR DAO Status',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes or observations'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL COMMENT 'Unique ID for each user',
  `username` varchar(100) NOT NULL COMMENT 'Username of the person using the system',
  `email` varchar(255) DEFAULT NULL COMMENT 'User email address',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date and time when the user was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores system users who can perform actions in PhilBio';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archive_bat_species`
--
ALTER TABLE `archive_bat_species`
  ADD PRIMARY KEY (`species_id`);

--
-- Indexes for table `archive_bird_species`
--
ALTER TABLE `archive_bird_species`
  ADD PRIMARY KEY (`species_id`);

--
-- Indexes for table `archive_flora`
--
ALTER TABLE `archive_flora`
  ADD PRIMARY KEY (`record_id`);

--
-- Indexes for table `archive_transects`
--
ALTER TABLE `archive_transects`
  ADD PRIMARY KEY (`transect_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bats_measurements`
--
ALTER TABLE `bats_measurements`
  ADD PRIMARY KEY (`bat_id`),
  ADD KEY `fk_bats_species` (`species_id`);

--
-- Indexes for table `bat_species`
--
ALTER TABLE `bat_species`
  ADD PRIMARY KEY (`species_id`);

--
-- Indexes for table `bird_observations`
--
ALTER TABLE `bird_observations`
  ADD PRIMARY KEY (`observation_id`),
  ADD KEY `fk_bird_species` (`species_id`),
  ADD KEY `fk_bird_transect` (`transect_id`);

--
-- Indexes for table `bird_species`
--
ALTER TABLE `bird_species`
  ADD PRIMARY KEY (`species_id`);

--
-- Indexes for table `bird_transects`
--
ALTER TABLE `bird_transects`
  ADD PRIMARY KEY (`transect_id`);

--
-- Indexes for table `flora_tawi`
--
ALTER TABLE `flora_tawi`
  ADD PRIMARY KEY (`record_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archive_bat_species`
--
ALTER TABLE `archive_bat_species`
  MODIFY `species_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `archive_bird_species`
--
ALTER TABLE `archive_bird_species`
  MODIFY `species_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each bird species', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `archive_flora`
--
ALTER TABLE `archive_flora`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `archive_transects`
--
ALTER TABLE `archive_transects`
  MODIFY `transect_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each transect survey', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique log entry ID', AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `bats_measurements`
--
ALTER TABLE `bats_measurements`
  MODIFY `bat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bat_species`
--
ALTER TABLE `bat_species`
  MODIFY `species_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bird_observations`
--
ALTER TABLE `bird_observations`
  MODIFY `observation_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique observation record ID', AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bird_species`
--
ALTER TABLE `bird_species`
  MODIFY `species_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each bird species', AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `bird_transects`
--
ALTER TABLE `bird_transects`
  MODIFY `transect_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each transect survey', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `flora_tawi`
--
ALTER TABLE `flora_tawi`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique ID for each user';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `bats_measurements`
--
ALTER TABLE `bats_measurements`
  ADD CONSTRAINT `fk_bats_species` FOREIGN KEY (`species_id`) REFERENCES `bat_species` (`species_id`) ON DELETE SET NULL;

--
-- Constraints for table `bird_observations`
--
ALTER TABLE `bird_observations`
  ADD CONSTRAINT `bird_observations_ibfk_1` FOREIGN KEY (`transect_id`) REFERENCES `bird_transects` (`transect_id`),
  ADD CONSTRAINT `bird_observations_ibfk_2` FOREIGN KEY (`species_id`) REFERENCES `bird_species` (`species_id`),
  ADD CONSTRAINT `fk_bird_species` FOREIGN KEY (`species_id`) REFERENCES `bird_species` (`species_id`),
  ADD CONSTRAINT `fk_bird_transect` FOREIGN KEY (`transect_id`) REFERENCES `bird_transects` (`transect_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
