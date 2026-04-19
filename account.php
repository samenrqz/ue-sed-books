<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: admin.php");
    exit;
}

$userId = $_SESSION['user_id'];
$updateSuccess = false;
$updateError = '';
$photoSuccess = false;

// Handle profile photo upload
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['profile_photo']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $updateError = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $updateError = 'Image must be under 5MB.';
        } else {
            $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $safeName = 'user_' . $userId . '_' . time() . '.' . $ext;
            $dest = 'uploads/' . $safeName;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dest)) {
                $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmt->bind_param("si", $dest, $userId);
                $stmt->execute();
                $stmt->close();
                $photoSuccess = true;
            } else {
                $updateError = 'Failed to upload photo.';
            }
        }
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $studentNumber = trim($_POST['student_number']);
    $bio = trim($_POST['bio']);
    $newPassword = $_POST['new_password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';

    // Fetch user's current password hash for verification
    $pwCheckStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwCheckStmt->bind_param("i", $userId);
    $pwCheckStmt->execute();
    $pwCheckResult = $pwCheckStmt->get_result();
    $userPwData = $pwCheckResult->fetch_assoc();
    $pwCheckStmt->close();

    if (empty($firstName) || empty($lastName) || empty($email) || empty($studentNumber)) {
        $updateError = 'First name, last name, email, and student number are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $updateError = 'Invalid email format.';
    } elseif (preg_match('/\d/', $firstName)) {
        $updateError = 'Numbers are not allowed in first name.';
    } elseif (preg_match('/\d/', $lastName)) {
        $updateError = 'Numbers are not allowed in last name.';
    } elseif (preg_match('/[a-zA-Z]/', $studentNumber)) {
        $updateError = 'Letters are not allowed in student number.';
    } elseif (!empty($newPassword) && (empty($currentPassword) || !password_verify($currentPassword, $userPwData['password']))) {
        $updateError = 'To change your password, you must enter your correct current password.';
    } else {
        // Check duplicates excluding self
        $dup = $conn->prepare("SELECT id FROM users WHERE (email = ? OR student_number = ?) AND id != ?");
        $dup->bind_param("ssi", $email, $studentNumber, $userId);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $updateError = 'Email or student number already in use by another account.';
        } else {
            if (!empty($newPassword)) {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, student_number=?, bio=?, password=? WHERE id=?");
                $upd->bind_param("ssssssi", $firstName, $lastName, $email, $studentNumber, $bio, $hashed, $userId);
            } else {
                $upd = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, student_number=?, bio=? WHERE id=?");
                $upd->bind_param("sssssi", $firstName, $lastName, $email, $studentNumber, $bio, $userId);
            }
            if ($upd->execute()) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $email;
                $updateSuccess = true;
            } else {
                $updateError = 'Failed to update profile. Please try again.';
            }
            $upd->close();
        }
        $dup->close();
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT first_name, last_name, email, student_number, bio, profile_photo, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userInitial = strtoupper(mb_substr($user['first_name'], 0, 1));
$memberSince = date('F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - UEsed Books</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rammetto+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ─── Reset & Base ─────────────────────────────────────── */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        /* ─── STICKY FOOTER FIX ─────────────────────────────────
           The full flex chain must be unbroken:
           html (100%) → body (flex, column, min-height:100vh)
             → .account-page (flex:1, flex column)
               → .account-container (flex:1)
               → footer (flex-shrink:0, stays at bottom)
        ─────────────────────────────────────────────────────── */
        body.account-body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* This wrapper must grow to fill all remaining space so
           the footer is always pushed to the very bottom. */
        .account-page {
            flex: 1;                  /* grow to fill body */
            display: flex;
            flex-direction: column;
            animation: accountFadeIn 0.5s cubic-bezier(.22,.61,.36,1) both;
        }

        /* Navbar (reuse home styles) */
        .home-header {
            background: #fff;
            padding: 0.8rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .home-header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .home-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .home-logo img {
            width: 36px; height: 36px;
            object-fit: contain;
            border-radius: 8px;
        }
        .home-logo span {
            font-family: 'Rammetto One', cursive;
            font-size: 1.3rem;
            color: #8b2e2e;
        }
        .home-nav {
            display: flex; align-items: center; gap: 2.2rem;
        }
        .home-nav a {
            text-decoration: none; color: #555;
            font-size: 0.95rem; font-weight: 500;
            transition: color 0.2s, font-weight 0.2s;
        }
        .home-nav a:hover { color: #333; font-weight: 700; }
        .home-nav a.active { color: #333; }
        /* ─── Account Dropdown ────────────────────────────────── */
        .account-dropdown {
            position: relative;
            display: inline-block;
        }
        .home-account-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #a82c2c;
            color: #fff;
            text-decoration: none;
            padding: 0.6rem 1.4rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            position: relative;
            z-index: 2;
        }
        .home-account-btn svg {
            transition: transform 0.25s ease;
        }
        .account-dropdown:hover .home-account-btn {
            background: #8b2e2e;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(168,44,44,0.35);
            border-radius: 25px 25px 0 0;
        }
        .account-dropdown:hover .home-account-btn svg {
            transform: rotate(180deg);
        }
        .account-dropdown-menu {
            position: absolute;
            top: calc(100% - 4px);
            right: 0;
            min-width: 100%;
            background: #8b2e2e;
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(168,44,44,0.28);
            /* hidden by default */
            opacity: 0;
            pointer-events: none;
            transform: translateY(-6px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 1;
        }
        .account-dropdown:hover .account-dropdown-menu {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        .account-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.4rem;
            color: #fff;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            white-space: nowrap;
            transition: background 0.15s, padding-left 0.15s;
        }
        .account-dropdown-menu a:hover {
            background: rgba(255,255,255,0.15);
            padding-left: 1.7rem;
        }
        .account-dropdown-menu a svg {
            flex-shrink: 0;
            opacity: 0.85;
        }

        /* ─── Page Content ──────────────────────────────────────── */
        @keyframes accountFadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* flex:1 makes this section grow and push the footer down */
        .account-container {
            flex: 1;                  /* KEY: grows to fill .account-page */
            max-width: 820px;
            width: 100%;
            margin: 2.5rem auto 2.5rem;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 2rem;
            align-content: start;     /* grid rows start at top, not stretched */
            box-sizing: border-box;
        }

        /* ─── Profile Card (left) ─────────────────────────────── */
        .profile-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            position: relative;
            height: fit-content;
        }
        .profile-avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .profile-avatar {
            width: 100px; height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f0e6e6;
            box-shadow: 0 4px 16px rgba(168,44,44,0.12);
        }
        .profile-avatar-placeholder {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #a82c2c, #c0392b);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem; font-weight: 700;
            border: 4px solid #f0e6e6;
            box-shadow: 0 4px 16px rgba(168,44,44,0.12);
        }
        .profile-camera-btn {
            position: absolute;
            bottom: 2px; right: 2px;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #a82c2c;
            color: #fff;
            border: 3px solid #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .profile-camera-btn:hover {
            background: #8b2e2e;
            transform: scale(1.1);
        }
        .profile-name {
            font-size: 1.15rem; font-weight: 700;
            color: #1a1a2e; margin-bottom: 0.2rem;
        }
        .profile-email {
            font-size: 0.82rem; color: #888; margin-bottom: 0.5rem;
        }
        .profile-member {
            font-size: 0.75rem; color: #bbb;
            margin-bottom: 1rem;
        }
        .profile-member svg {
            vertical-align: -2px; margin-right: 3px;
        }
        .profile-bio {
            font-size: 0.83rem; color: #666;
            line-height: 1.6; background: #faf8f6;
            border-radius: 12px; padding: 0.8rem 1rem;
            text-align: left; min-height: 48px;
            border: 1px dashed #e8e0dc;
        }
        .profile-bio-empty {
            color: #bbb; font-style: italic;
        }
        .profile-logout {
            display: inline-block; margin-top: 1.2rem;
            color: #a82c2c; font-size: 0.82rem;
            font-weight: 600; text-decoration: none;
            transition: color 0.2s;
        }
        .profile-logout:hover { color: #8b2e2e; }

        /* ─── Edit Form (right) ───────────────────────────────── */
        .edit-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem 2rem 1.5rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            height: fit-content;
        }
        .edit-card h2 {
            font-family: 'Rammetto One', cursive;
            font-size: 1.2rem; color: #a82c2c;
            margin-bottom: 0.3rem;
        }
        .edit-card .edit-subtitle {
            font-size: 0.82rem; color: #999;
            margin-bottom: 1.5rem;
        }
        .edit-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .edit-form-group {
            display: flex; flex-direction: column;
        }
        .edit-form-group.full {
            grid-column: 1 / -1;
        }
        .edit-form-group label {
            font-size: 0.78rem; font-weight: 600;
            color: #666; margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .edit-form-group input,
        .edit-form-group textarea {
            padding: 0.65rem 0.85rem;
            border: 1.5px solid #e8e0dc;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: #333;
            background: #fdfbfa;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .edit-form-group input:focus,
        .edit-form-group textarea:focus {
            border-color: #a82c2c;
            box-shadow: 0 0 0 3px rgba(168,44,44,0.08);
            background: #fff;
        }
        .edit-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .edit-form-group .pw-hint {
            font-size: 0.72rem; color: #bbb;
            margin-top: 0.25rem;
        }
        .edit-pw-wrap {
            position: relative;
        }
        .edit-pw-wrap input {
            width: 100%;
            padding-right: 2.5rem;
            box-sizing: border-box;
        }
        .edit-pw-toggle {
            position: absolute;
            right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #aaa; padding: 2px;
            transition: color 0.2s;
        }
        .edit-pw-toggle:hover { color: #a82c2c; }

        .edit-save-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: #a82c2c; color: #fff;
            border: none; padding: 0.7rem 2rem;
            border-radius: 12px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            margin-top: 0.5rem;
        }
        .edit-save-btn:hover {
            background: #8b2e2e;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(168,44,44,0.3);
        }

        /* ─── Success / Error Toast ───────────────────────────── */
        .account-toast {
            position: fixed;
            top: 80px; right: 24px;
            padding: 0.85rem 1.4rem;
            border-radius: 12px;
            font-size: 0.88rem; font-weight: 600;
            z-index: 999;
            display: flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 6px 24px rgba(0,0,0,0.12);
            animation: toastSlideIn 0.4s ease, toastFadeOut 0.4s ease 3s forwards;
        }
        .account-toast.success {
            background: #e8f8e8; color: #27ae60;
            border: 1px solid #c3e8c3;
        }
        .account-toast.error {
            background: #fde8e8; color: #c0392b;
            border: 1px solid #f0c3c3;
        }
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes toastFadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }

        /* ─── Footer ──────────────────────────────────────────── */
        /* flex-shrink:0 prevents footer from squishing;
           it naturally lands at the bottom because .account-page
           and .account-container above it expand via flex:1    */
        .home-footer {
            flex-shrink: 0;
            background: #a82c2c;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 60px;
            color: #fff;
            font-family: 'Rammetto One', cursive;
            font-size: 0.85rem;
        }
        .home-footer-left {
            font-family: 'Rammetto One', cursive;
            color: #fff;
            font-size: 0.85rem;
        }
        .home-footer-right { display: flex; gap: 1.8rem; }
        .home-footer-right a {
            color: #fff; text-decoration: none;
            font-size: 0.85rem; font-weight: 500;
            transition: opacity 0.2s;
        }
        .home-footer-right a:hover { opacity: 0.8; }

        /* ─── Responsive ──────────────────────────────────────── */
        @media (max-width: 768px) {
            .account-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            .home-nav { gap: 1.2rem; }
            .home-footer {
                flex-direction: column;
                gap: 0.8rem;
                text-align: center;
            }
        }
        /* ── ICON + ACCOUNT ALIGNMENT ── */
.account-actions-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-bag-icon {
    cursor: pointer;
    transition: filter 0.25s ease;
    filter: brightness(0);
}

.nav-bag-icon:hover {
    filter: brightness(0) saturate(100%) invert(17%) sepia(86%) saturate(7496%) hue-rotate(353deg) brightness(90%) contrast(120%);
}
    </style>
</head>
<body class="account-body">
<div class="account-page">

    <!-- Navbar -->
    <header class="home-header">
        <div class="home-header-inner">
            <a href="home.php" class="home-logo">
                <img src="images/5.png" alt="UEsed Books Logo">
                <span>UEsed Books</span>
            </a>
            <nav class="home-nav">
                <a href="home.php">Home</a>
                <a href="listing.php">Listing</a>
                <a href="about.php">About</a>
            </nav>
            <div class="account-actions-group">
                <a href="history.php" title="Transactions" style="display:inline-block;vertical-align:middle;margin-right:0.5rem;">
    <svg class="nav-bag-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:0.5rem;">
        <rect x="3" y="5" width="18" height="14" rx="3"/>
        <path d="M16 3v4"/>
        <path d="M8 3v4"/>
        <path d="M3 9h18"/>
    </svg>
</a>

    <div class="account-dropdown">
        <a href="account.php" class="home-account-btn">
            My Account
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </a>
        <div class="account-dropdown-menu">
            <a href="logout.php">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Sign Out
            </a>
        </div>
    </div>
</div>
    </header>

    <!-- Toasts -->
    <?php if ($updateSuccess || $photoSuccess): ?>
    <div class="account-toast success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?php echo $photoSuccess ? 'Photo updated!' : 'Profile saved successfully!'; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($updateError)): ?>
    <div class="account-toast error">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo htmlspecialchars($updateError); ?>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="account-container">

        <!-- Profile Card -->
        <div class="profile-card">
            <form method="POST" enctype="multipart/form-data" id="photoForm">
                <input type="hidden" name="upload_photo" value="1">
                <input type="file" name="profile_photo" id="photoInput" accept="image/*" style="display:none;">
            </form>
            <div class="profile-avatar-wrap">
                <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                    <img class="profile-avatar" src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile">
                <?php else: ?>
                    <div class="profile-avatar-placeholder"><?php echo $userInitial; ?></div>
                <?php endif; ?>
                <button class="profile-camera-btn" id="cameraBtn" title="Change photo">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </button>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
            <div class="profile-member">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Member since <?php echo $memberSince; ?>
            </div>
            <div class="profile-bio">
                <?php if (!empty($user['bio'])): ?>
                    <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                <?php else: ?>
                    <span class="profile-bio-empty">No bio yet — tell us about yourself!</span>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="profile-logout">Sign Out</a>
        </div>

        <!-- Edit Form -->
        <div class="edit-card">
            <h2>Edit Profile</h2>
            <p class="edit-subtitle">Update your personal info and bio below.</p>
            <form method="POST" id="editForm" autocomplete="off">
                <input type="hidden" name="update_profile" value="1">
                <div class="edit-form-row">
                    <div class="edit-form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="edit-form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="edit-form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" value="<?php echo htmlspecialchars($user['student_number']); ?>" required>
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-group full">
                        <label>Bio</label>
                        <textarea name="bio" placeholder="Write a little about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-group full">
                        <label>Current Password</label>
                        <div class="edit-pw-wrap">
                            <input type="password" name="current_password" id="currentPwInput" placeholder="Enter current password" autocomplete="current-password">
                            <button type="button" class="edit-pw-toggle" id="currentPwToggle" title="Show password">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <span class="pw-hint">Enter your current password to change your password.</span>
                    </div>
                </div>
                <div class="edit-form-row">
                    <div class="edit-form-group full">
                        <label>New Password</label>
                        <div class="edit-pw-wrap">
                            <input type="password" name="new_password" id="newPwInput" placeholder="Leave blank to keep current" autocomplete="new-password">
                            <button type="button" class="edit-pw-toggle" id="pwToggle" title="Show password">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <span class="pw-hint">Leave blank to keep your current password.</span>
                    </div>
                </div>
                <button type="submit" class="edit-save-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Changes
                </button>
            </form>
        </div>

    </div><!-- /.account-container -->

    <!-- Footer -->
    <footer class="home-footer">
        <div class="home-footer-left">2026 UEsed Books</div>
        <div class="home-footer-right">
            <a href="listing.php">Shop</a>
            <a href="about.php">About</a>
        </div>
    </footer>

</div><!-- /.account-page -->

<script>
// Photo upload
document.getElementById('cameraBtn').addEventListener('click', function() {
    document.getElementById('photoInput').click();
});
document.getElementById('photoInput').addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('photoForm').submit();
    }
});

// Password toggles
function setupPwToggle(toggleId, inputId) {
    var toggle = document.getElementById(toggleId);
    if (toggle) {
        toggle.addEventListener('click', function() {
            var inp = document.getElementById(inputId);
            var isHidden = inp.type === 'password';
            inp.type = isHidden ? 'text' : 'password';
            this.style.opacity = isHidden ? '0.5' : '1';
        });
    }
}
setupPwToggle('pwToggle', 'newPwInput');
setupPwToggle('currentPwToggle', 'currentPwInput');

// Auto-dismiss toast
var toast = document.querySelector('.account-toast');
if (toast) {
    setTimeout(function() { toast.remove(); }, 3600);
}
</script>
</body>
</html>