-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20260511.41911fadd5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 15, 2026 at 07:38 AM
-- Server version: 8.4.3
-- PHP Version: 8.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hnnw_care`
--

-- --------------------------------------------------------

--
-- Table structure for table `organisaties`
--

CREATE TABLE `organisaties` (
  `id` int NOT NULL,
  `naam` varchar(100) NOT NULL,
  `adres` varchar(150) NOT NULL,
  `stad` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `organisaties`
--

INSERT INTO `organisaties` (`id`, `naam`, `adres`, `stad`) VALUES
(1, 'TechBV Nederland', 'Stationsplein 1', 'Rotterdam'),
(2, 'Zorggroep Zuid', 'Kerkstraat 15', 'Amsterdam'),
(3, 'Tech Solutions BV', 'Industrieweg 20', 'Utrecht');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `organisaties`
--
ALTER TABLE `organisaties`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `organisaties`
--
ALTER TABLE `organisaties`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
