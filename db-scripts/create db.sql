-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 18, 2026 at 11:00 AM
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
-- Database: `uesed_books`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_photos`
--

CREATE TABLE `admin_photos` (
  `email` varchar(255) NOT NULL,
  `photo` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_profile`
--

CREATE TABLE `admin_profile` (
  `id` int(11) NOT NULL DEFAULT 1,
  `first_name` varchar(100) NOT NULL DEFAULT 'Admin',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `website_name` varchar(255) NOT NULL DEFAULT 'UEsed Books',
  `language` varchar(20) NOT NULL DEFAULT 'English',
  `website_logo` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_profile`
--

INSERT INTO `admin_profile` (`id`, `first_name`, `last_name`, `website_name`, `language`, `website_logo`) VALUES
(1, 'Admin', '', 'UEsed Books', 'English', 'website_logo_1775999883.png');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `seller` varchar(255) DEFAULT '',
  `seller_id` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `description`, `seller`, `seller_id`, `stock`, `price`, `image`, `created_at`) VALUES
(9, 'The Alchemist', 'A philosophical story about following your dreams and listening to your heart.', 'vlad@gmail.com', 10, 4, 550.00, 'book_69e30a49a7bbb.jpg', '2026-04-17 15:04:47'),
(10, 'Atomic Habits', 'A practical guide on building good habits and breaking bad ones using small, consistent changes.', 'vlad@gmail.com', 10, 5, 650.00, 'book_69e30a1307ed4.jpg', '2026-04-17 15:09:03'),
(13, 'Rich Dad Poor Dad', 'Explains financial literacy and the mindset needed to build wealth.', 'vlad@gmail.com', 10, 0, 750.00, 'book_69e30a9e5d304.jpg', '2026-04-18 04:37:28'),
(14, '1984', 'A dystopian novel about surveillance, control, and loss of freedom.', 'jaymarkiellumbang@gmail.com', 8, 4, 480.00, 'book_69e30ae29b1e7.jpg', '2026-04-18 04:38:58'),
(15, 'The Subtle Art of Not Giving a F*ck', 'A self-help book that promotes focusing only on what truly matters in life.', 'jaymarkiellumbang@gmail.com', 8, 2, 620.00, 'book_69e30b714ab99.jfif', '2026-04-18 04:41:21');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT 0,
  `transaction_date` date NOT NULL,
  `meetup_place` varchar(255) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `book` varchar(255) NOT NULL,
  `buyer` varchar(255) NOT NULL,
  `buyer_id` int(11) DEFAULT 0,
  `buyer_email` varchar(255) DEFAULT '',
  `seller_email` varchar(255) NOT NULL,
  `seller_id` int(11) DEFAULT 0,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `book_id`, `transaction_date`, `meetup_place`, `message`, `book`, `buyer`, `buyer_id`, `buyer_email`, `seller_email`, `seller_id`, `amount`, `status`, `created_at`) VALUES
(1, 12, '2026-04-18', '21321', '12312', '2131', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 11.00, 'Approved', '2026-04-18 04:23:08'),
(2, 12, '2026-04-18', '312312', '3123123', '2131', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 11.00, 'Approved', '2026-04-18 04:24:27'),
(3, 12, '2026-04-18', 'adwdwa', 'awdwa', '2131', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 11.00, 'Approved', '2026-04-18 04:29:16'),
(4, 13, '2026-04-18', 'Canteen', '', 'Rich Dad Poor Dad', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 750.00, 'Approved', '2026-04-18 04:53:50'),
(5, 13, '2026-04-18', 'w', '', 'Rich Dad Poor Dad', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 750.00, 'Rejected', '2026-04-18 06:08:46'),
(6, 13, '2026-04-18', 'dwa', '', 'Rich Dad Poor Dad', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 750.00, 'Rejected', '2026-04-18 07:24:39'),
(7, 15, '2026-04-18', 'awdwa', '', 'The Subtle Art of Not Giving a F*ck', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 620.00, 'Rejected', '2026-04-18 07:31:07'),
(8, 13, '2026-04-18', 'Canteen', 'Please', 'Rich Dad Poor Dad', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 750.00, 'Rejected', '2026-04-18 07:39:24'),
(9, 15, '2026-04-18', 'Canteen', '', 'The Subtle Art of Not Giving a F*ck', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 620.00, 'Rejected', '2026-04-18 07:41:59'),
(10, 13, '2026-04-18', 'dwad', 'awdwad', 'Rich Dad Poor Dad', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 750.00, 'Rejected', '2026-04-18 07:47:32'),
(11, 14, '2026-04-18', 'Canteen', 'Hi', '1984', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 480.00, 'Rejected', '2026-04-18 08:14:01'),
(12, 15, '2026-08-12', 'dsadw', '123', 'The Subtle Art of Not Giving a F*ck', 'Vlad Alfon', 10, 'vlad@gmail.com', 'jaymarkiellumbang@gmail.com', 8, 620.00, 'Pending', '2026-04-18 08:23:18'),
(13, 9, '2032-06-03', 'Obando', '', 'The Alchemist', 'Jaymar Lumbang', 8, 'jaymarkiellumbang@gmail.com', 'vlad@gmail.com', 10, 550.00, 'Pending', '2026-04-18 08:38:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `profile_photo` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `student_number`, `password`, `created_at`, `bio`, `profile_photo`) VALUES
(2, 'Shai', 'Cruz', 'Shaina@gmail.com', '20241116390', '$2y$10$m5AWuwaUoRIlZfUzIZTkgu9mbeNWr3nJxyAaxgospkS8g1oAPGO9e', '2026-04-12 10:13:22', NULL, ''),
(6, 'Jaymar', 'Lumbang', 'admin@gmail.com', '2024', '$2y$10$l/sLgaFP5gfcYT2MOtO0muEnsfJXDgrKdgUAN9Tnk.OrN55S6JY0m', '2026-04-12 12:08:11', NULL, ''),
(7, 'Sam', 'Enriquez', 'Sam@gmail.com', '20241167676', '$2y$10$5kzPmkKWc74uiKvyQUkdZeIO6v.scDeands.k8ZlMXxVlSxBpTAB6', '2026-04-13 12:16:04', NULL, ''),
(8, 'Jaymar', 'Lumbang', 'jaymarkiellumbang@gmail.com', '20241128734', '$2y$10$lRD4vt/dXcvEYNiOSQvhz.g9RGg1dpMBx.e9xddmh40myWNWCWD/W', '2026-04-13 13:54:41', 'Ang Pogi ko naman', 'uploads/user_8_1776090268.png'),
(9, 'Phiben', 'Pogi', 'paobnial@gmail.com', '20241169696', '$2y$10$/pV2PURPVnTxf9Ix8P9pmef1gkZ1bh.CmXIhG5HPSol9vkbRlPLRi', '2026-04-15 05:51:22', NULL, ''),
(10, 'Vlad', 'Alfon', 'vlad@gmail.com', '2024123675', '$2y$10$XDc0ZucZaMGmApwZsKogR.GiP3tW/wUjBJZ2Rj6VHsAKETu9TwyuC', '2026-04-17 14:55:40', NULL, ''),
(11, 'phob', 'obni', 'phob@gmail.com', '202467', '$2y$10$oYbhm4bg1lELr94K6B3xpe5Y1Mm1uUqpYG1MlF9MbpaAMh1mbRdPK', '2026-04-18 08:36:13', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `workspace_admins`
--

CREATE TABLE `workspace_admins` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `employee_number` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_admins`
--

INSERT INTO `workspace_admins` (`id`, `first_name`, `last_name`, `student_number`, `email`, `password`, `employee_number`, `created_at`) VALUES
(10, 'Shaina', 'Cruz', '', 'Shai@gmail.com', '$2y$10$Vho/tklkNJk50N8om/e9He8aZVrfLig8FpCpkoIPvmz7JG11Ewbl6', '1', '2026-04-18 06:34:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_photos`
--
ALTER TABLE `admin_photos`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `admin_profile`
--
ALTER TABLE `admin_profile`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_number` (`student_number`);

--
-- Indexes for table `workspace_admins`
--
ALTER TABLE `workspace_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `workspace_admins`
--
ALTER TABLE `workspace_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
