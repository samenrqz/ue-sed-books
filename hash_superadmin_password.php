<?php
/**
 * hash_superadmin_password.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Run this ONCE in your browser or via CLI after uploading login.php.
 * It checks whether the super-admin password in admin_profile is still stored
 * as plain text and, if so, hashes it with password_hash().
 *
 * DELETE THIS FILE from your server after running it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
require_once 'connect.php';

$row = $conn->query("SELECT password FROM admin_profile WHERE id = 1 LIMIT 1")->fetch_assoc();

if (!$row) {
    die("❌  No row found in admin_profile with id = 1.");
}

$stored = $row['password'];
$info   = password_get_info($stored);

if ($info['algo'] !== 0) {
    echo "✅  Password is already hashed (" . $info['algoName'] . "). Nothing to do.";
    exit;
}

// Plain-text detected — hash it now.
$hashed = password_hash($stored, PASSWORD_DEFAULT);
$stmt   = $conn->prepare("UPDATE admin_profile SET password = ? WHERE id = 1");
$stmt->bind_param("s", $hashed);

if ($stmt->execute()) {
    echo "✅  Password hashed successfully. You can now delete this file.";
} else {
    echo "❌  Update failed: " . $stmt->error;
}

$stmt->close();
$conn->close();