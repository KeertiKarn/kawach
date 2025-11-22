-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2025 at 07:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kawach_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `complaint_id` varchar(20) NOT NULL,
  `location_qr` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `status` enum('Pending','Assigned','Completed') NOT NULL DEFAULT 'Pending',
  `assigned_worker_id` varchar(20) DEFAULT NULL,
  `completion_photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `complaint_id`, `location_qr`, `description`, `contact`, `status`, `assigned_worker_id`, `completion_photo_url`, `created_at`) VALUES
(1, 'C001', 'T0045A', 'Clogged drain, very wet floor.', '9876543210', 'Assigned', 'W-78', NULL, '2025-11-21 15:43:26'),
(2, 'C002', 'F0005G', 'No water supply in sinks.', '9876543210', 'Assigned', 'W-78', NULL, '2025-11-21 15:43:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('pilgrim','worker','official') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `phone_number`, `password_hash`, `role`) VALUES
('O-01', 'Official Admin', NULL, 'pass', 'official'),
('P-101', 'Sohan Lal', '9876543210', 'pass', 'pilgrim'),
('P-102', 'Abc', '9122244992', 'Keerti', 'pilgrim'),
('W-78', 'Ramesh Singh', NULL, 'pass', 'worker');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
