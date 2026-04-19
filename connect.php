<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "uesed_books";


$conn = new mysqli($host, $username, $password, $database);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-create workspace_admins table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `workspace_admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `student_number` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
// Add email column if it doesn't exist (for existing tables)
$conn->query("ALTER TABLE `workspace_admins` ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NOT NULL DEFAULT '' AFTER `student_number`");

// Auto-create admin_profile table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `admin_profile` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `first_name` VARCHAR(100) NOT NULL DEFAULT 'Admin',
    `last_name` VARCHAR(100) NOT NULL DEFAULT '',
    `website_name` VARCHAR(255) NOT NULL DEFAULT 'UEsed Books',
    `language` VARCHAR(20) NOT NULL DEFAULT 'English',
    `website_logo` VARCHAR(255) NOT NULL DEFAULT ''
)");
// Add columns if they don't exist (for existing tables)
$conn->query("ALTER TABLE `admin_profile` ADD COLUMN IF NOT EXISTS `website_name` VARCHAR(255) NOT NULL DEFAULT 'UEsed Books'");
$conn->query("ALTER TABLE `admin_profile` ADD COLUMN IF NOT EXISTS `language` VARCHAR(20) NOT NULL DEFAULT 'English'");
$conn->query("ALTER TABLE `admin_profile` ADD COLUMN IF NOT EXISTS `website_logo` VARCHAR(255) NOT NULL DEFAULT ''");
// Insert default row if empty
$conn->query("INSERT IGNORE INTO `admin_profile` (id, first_name, last_name, website_name, language, website_logo) VALUES (1, 'Admin', '', 'UEsed Books', 'English', '')");

// Auto-create admin_photos table for per-admin profile pictures
$conn->query("CREATE TABLE IF NOT EXISTS `admin_photos` (
    `email` VARCHAR(255) PRIMARY KEY,
    `photo` VARCHAR(255) NOT NULL DEFAULT ''
)");

// Auto-create books table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `books` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `image` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add bio and profile_photo columns to users table if they don't exist
$conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `bio` TEXT DEFAULT NULL");
$conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) NOT NULL DEFAULT ''");

// Auto-create transactions table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `meetup_place` VARCHAR(255) NOT NULL DEFAULT '',
    `book` VARCHAR(255) NOT NULL,
    `buyer` VARCHAR(255) NOT NULL,
    `seller_email` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>