<?php
session_start();
require_once 'connect.php';

// Only allow admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$isSuperAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
$registerSuccess = false;
$registerError = '';
$deleteSuccess = false;
$editSuccess = false;
$editError = '';

// Handle edit user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_user'])) {
    $editId = intval($_POST['edit_user_id']);
    $editFn = trim($_POST['edit_first_name']);
    $editLn = trim($_POST['edit_last_name']);
    $editEm = trim($_POST['edit_email']);
    $editSn = trim($_POST['edit_student_number']);
    $editPw = $_POST['edit_password'];

    if (empty($editFn) || empty($editLn) || empty($editEm) || empty($editSn)) {
        $editError = 'First Name, Last Name, Email, and Student Number are required.';
    } elseif (!filter_var($editEm, FILTER_VALIDATE_EMAIL)) {
        $editError = 'Invalid email format.';
    } elseif (preg_match('/\d/', $editFn)) {
        $editError = 'Numbers are not allowed in First Name.';
    } elseif (preg_match('/\d/', $editLn)) {
        $editError = 'Numbers are not allowed in Last Name.';
    } elseif (preg_match('/[a-zA-Z]/', $editSn)) {
        $editError = 'Letters are not allowed in Student Number.';
    } else {
        // Check for duplicate email/student number (exclude current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR student_number = ?) AND id != ?");
        $stmt->bind_param("ssi", $editEm, $editSn, $editId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $editError = 'Email or Student Number already in use by another user.';
        } else {
            if (!empty($editPw)) {
                $hashed = password_hash($editPw, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, student_number=?, password=? WHERE id=?");
                $upd->bind_param("sssssi", $editFn, $editLn, $editEm, $editSn, $hashed, $editId);
            } else {
                $upd = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, student_number=? WHERE id=?");
                $upd->bind_param("ssssi", $editFn, $editLn, $editEm, $editSn, $editId);
            }
            if ($upd->execute()) {
                $editSuccess = true;
            } else {
                $editError = 'Failed to update user. Please try again.';
            }
            $upd->close();
        }
        $stmt->close();
    }
}

// Handle delete user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_user_id'])) {
    $delId = intval($_POST['delete_user_id']);
    if ($delId > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $deleteSuccess = true;
        }
        $stmt->close();
    }
}

// Handle add new user
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_new_user'])) {
    $fn = trim($_POST['new_first_name']);
    $ln = trim($_POST['new_last_name']);
    $em = trim($_POST['new_email']);
    $sn = trim($_POST['new_student_number']);
    $pw = $_POST['new_password'];

    if (empty($fn) || empty($ln) || empty($em) || empty($sn) || empty($pw)) {
        $registerError = 'All fields are required.';
    } elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Invalid email format.';
    } elseif (preg_match('/\d/', $fn)) {
        $registerError = 'Numbers are not allowed in First Name.';
    } elseif (preg_match('/\d/', $ln)) {
        $registerError = 'Numbers are not allowed in Last Name.';
    } elseif (preg_match('/[a-zA-Z]/', $sn)) {
        $registerError = 'Letters are not allowed in Student Number.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR student_number = ?");
        $stmt->bind_param("ss", $em, $sn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $registerError = 'Email or Student Number already registered.';
        } else {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (first_name, last_name, email, student_number, password) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sssss", $fn, $ln, $em, $sn, $hashed);
            if ($ins->execute()) {
                $registerSuccess = true;
            } else {
                $registerError = 'Registration failed. Please try again.';
            }
            $ins->close();
        }
        $stmt->close();
    }
}

// Fetch admin profile
$adminProfile = $conn->query("SELECT first_name, last_name, website_name, language, website_logo FROM admin_profile WHERE id = 1")->fetch_assoc();
$adminFirstName = $adminProfile ? $adminProfile['first_name'] : $_SESSION['first_name'];
$adminLastName = $adminProfile ? $adminProfile['last_name'] : $_SESSION['last_name'];
$websiteName = $adminProfile && !empty($adminProfile['website_name']) ? $adminProfile['website_name'] : 'UEsed Books';
$websiteLogo = $adminProfile && !empty($adminProfile['website_logo']) ? $adminProfile['website_logo'] : '';

// Fetch admin photo
$adminPhoto = '';
$photoRow = $conn->query("SELECT photo FROM admin_photos WHERE email = '" . $conn->real_escape_string($_SESSION['email']) . "'");
if ($photoRow && $row = $photoRow->fetch_assoc()) {
    $adminPhoto = $row['photo'];
}

// Fetch all users
$users = [];
$result = $conn->query("SELECT id, first_name, last_name, email, student_number, created_at FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - UEsed Books</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rammetto+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Page Entry Animation */
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .admin-main {
            animation: pageFadeIn 0.45s cubic-bezier(0.4, 0, 0.2, 1) both;
        }

        /* Users Page Specific Styles */
        .users-search-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .users-search-wrap {
            width: 50%;
            position: relative;
            flex-shrink: 0;
        }

        .users-addnew-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0.7rem 1.25rem;
            background: linear-gradient(135deg, #a82c2c 0%, #8b2e2e 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.88rem;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: transform 0.2s ease, box-shadow 0.3s ease;
        }

        .users-addnew-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(168, 44, 44, 0.35);
        }

        .users-addnew-btn:active {
            transform: translateY(0);
        }

        .users-addnew-btn svg {
            stroke: #fff;
        }

        .users-search-wrap svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .users-search-input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.6rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Segoe UI', sans-serif;
            color: #333;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .users-search-input:focus {
            outline: none;
            border-color: #a82c2c;
            box-shadow: 0 0 0 3px rgba(168, 44, 44, 0.1);
        }

        .users-search-input::placeholder {
            color: #bbb;
        }

        .users-filters {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .users-filter-dropdown {
            position: relative;
        }

        .users-filter-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 1rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            background: #fff;
            font-size: 0.85rem;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 500;
            color: #444;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .users-filter-btn:hover {
            border-color: #c9c9c9;
        }

        .users-filter-btn.active {
            border-color: #a82c2c;
            color: #a82c2c;
            background: #fdf2f2;
        }

        .users-filter-btn svg {
            color: #999;
            transition: transform 0.3s ease;
        }

        .users-filter-btn.active svg {
            color: #a82c2c;
        }

        .filter-dropdown-list {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 180px;
            background: #fff;
            border: 1.5px solid #e8e8e8;
            border-radius: 12px;
            padding: 6px;
            list-style: none;
            margin: 0;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1), 0 2px 8px rgba(0, 0, 0, 0.04);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px) scale(0.98);
            transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.2s ease;
        }

        .filter-dropdown-list.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .filter-dropdown-item {
            padding: 0.55rem 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: #444;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
            font-family: 'Segoe UI', sans-serif;
        }

        .filter-dropdown-item:hover {
            background: #fdf2f2;
            color: #a82c2c;
        }

        .filter-dropdown-item.active {
            background: linear-gradient(135deg, #a82c2c 0%, #c0392b 100%);
            color: #fff;
        }

        .filter-dropdown-item.active:hover {
            background: linear-gradient(135deg, #8b2e2e 0%, #a82c2c 100%);
            color: #fff;
        }

        /* Users Table */
        .users-table-wrap {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 0.8rem 1rem;
            text-align: left;
            font-size: 0.85rem;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            border-bottom: 1px solid #f0f0f0;
        }

        .users-table th {
            background: #fafafa;
            font-weight: 600;
            color: #888;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .users-table tbody tr {
            transition: background 0.2s ease;
        }

        .users-table tbody tr:hover {
            background: #faf5f5;
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .users-table tbody tr.selected {
            background: #fdf2f2;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #fce4e4;
            color: #a82c2c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-name {
            font-weight: 600;
            color: #1a1a2e;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
            font-weight: 500;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: #27ae60;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #27ae60;
        }

        .row-actions {
            text-align: center;
        }

        .row-edit-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: none;
            border: 1.5px solid #e0e0e0;
            cursor: pointer;
            color: #999;
            font-size: 0.78rem;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .row-edit-btn:hover {
            color: #2980b9;
            border-color: #2980b9;
            background: #eaf2f8;
        }

        .row-edit-btn svg {
            flex-shrink: 0;
        }

        .row-delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: none;
            border: 1.5px solid #e0e0e0;
            cursor: pointer;
            color: #999;
            font-size: 0.78rem;
            font-family: 'Segoe UI', sans-serif;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .row-delete-btn:hover {
            color: #a82c2c;
            border-color: #a82c2c;
            background: #fdf2f2;
        }

        .row-delete-btn svg {
            flex-shrink: 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .users-table th:nth-child(4),
            .users-table td:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .users-search-bar {
                flex-direction: column;
            }

            .users-table th:nth-child(3),
            .users-table td:nth-child(3),
            .users-table th:nth-child(5),
            .users-table td:nth-child(5) {
                display: none;
            }
        }
    </style>
</head>
<body class="admin-body">
    <!-- Admin Top Bar -->
    <div class="admin-topbar">
        <div class="logo">
            <img class="logo-icon" src="images/5.png" alt="UEsed Books Logo">
            <a href="admin.php" class="logo-link">UEsed Books</a>
        </div>
        <div class="topbar-widget">
            <div class="tw-section">
                <div class="tw-icon tw-clock">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="tw-info">
                    <span class="tw-primary" id="twTime">--:-- --</span>
                    <span class="tw-secondary" id="twDate">Loading...</span>
                </div>
            </div>
            <div class="tw-divider"></div>
            <div class="tw-section">
                <div class="tw-icon tw-weather" id="twWeatherIcon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </div>
                <div class="tw-info">
                    <span class="tw-primary" id="twTemp">--°C</span>
                    <span class="tw-secondary" id="twDesc">Loading...</span>
                    <span class="tw-location"><svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg> Manila, PH</span>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-profile">
                <form method="POST" action="admin.php" enctype="multipart/form-data" id="profilePhotoForm" style="margin:0;">
                    <input type="hidden" name="upload_profile_photo" value="1">
                    <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                </form>
                <form method="POST" action="admin.php" id="removeProfilePhotoForm" style="margin:0;">
                    <input type="hidden" name="remove_profile_photo" value="1">
                </form>
                <div class="sidebar-avatar sidebar-avatar-clickable" id="sidebarAvatarClickable" title="Change profile photo">
                    <?php if ($adminPhoto && file_exists(__DIR__ . '/images/' . $adminPhoto)): ?>
                        <img src="images/<?php echo htmlspecialchars($adminPhoto); ?>" alt="Profile" class="sidebar-avatar-img">
                        <button type="button" class="avatar-remove-btn" id="removeProfilePhotoBtn" title="Remove photo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php endif; ?>
                    <div class="sidebar-avatar-overlay">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </div>
                </div>
                <span class="sidebar-company"><?php echo htmlspecialchars($adminFirstName . ' ' . $adminLastName); ?></span>
            </div>

            <nav class="sidebar-nav">
                <a href="admin.php#general" class="sidebar-link" data-section="general">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    General
                </a>
                <a href="books.php" class="sidebar-link" data-section="books">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Books
                </a>
                <a href="users.php" class="sidebar-link active" data-section="users">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
                <a href="transaction.php" class="sidebar-link" data-section="transaction">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Transaction
                </a>
            </nav>

            <a href="login.php" class="sidebar-signout">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign out
            </a>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Search Bar + Filter -->
            <div class="users-search-bar">
                <div class="users-search-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="users-search-input" id="usersSearch" placeholder="Search">
                </div>
                <button class="users-addnew-btn" id="addNewUserBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New
                </button>
                <div style="flex:1;"></div>
                <div class="users-filter-dropdown">
                    <button class="users-filter-btn" id="alphaFilterBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="M3 17l3 3 3-3"/><path d="M6 18V4"/></svg>
                        Filter
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <ul class="filter-dropdown-list" id="alphaFilterList">
                        <li class="filter-dropdown-item active" data-sort="az">Ascending Order</li>
                        <li class="filter-dropdown-item" data-sort="za">Descending Order</li>
                        <li class="filter-dropdown-item" data-sort="oldest">Oldest</li>
                        <li class="filter-dropdown-item" data-sort="newest">Newest</li>
                    </ul>
                </div>
            </div>

            <!-- Users Table -->
            <div class="users-table-wrap">
                <table class="users-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Student Number</th>
                            <th>Email</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr data-created="<?php echo htmlspecialchars($user['created_at']); ?>">
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge">
                                        <span class="status-dot"></span>
                                        Active
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                <td style="color:#a82c2c;font-weight:600;"><?php echo htmlspecialchars($user['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="row-actions">
                                    <button class="row-edit-btn" onclick="openEditUser(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['first_name'])); ?>', '<?php echo htmlspecialchars(addslashes($user['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($user['email'])); ?>', '<?php echo htmlspecialchars(addslashes($user['student_number'])); ?>')" title="Edit user">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </button>
                                    <button class="row-delete-btn" onclick="confirmDeleteUser(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['first_name'] . ' ' . $user['last_name'])); ?>')" title="Delete user">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">No users registered yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add New User Modal -->
    <div class="admin-modal-overlay" id="addNewUserModal">
        <div class="admin-modal" style="max-width:520px;">
            <button class="admin-modal-close" id="closeNewUserModal">&times;</button>
            <h2 class="register-title" style="font-size:1.4rem;margin-bottom:0.5rem;">Register New User</h2>
            <p class="register-subtitle" style="margin-bottom:1.5rem;">Fill in the details to create a new user account.</p>
            <form class="register-form" method="POST" action="users.php" id="addNewUserForm">
                <input type="hidden" name="add_new_user" value="1">
                <div class="form-row">
                    <fieldset class="form-group">
                        <legend>First Name</legend>
                        <input type="text" name="new_first_name" id="newFirstName" placeholder="John" required>
                        <span class="field-error" id="newFirstNameError"></span>
                    </fieldset>
                    <fieldset class="form-group">
                        <legend>Last Name</legend>
                        <input type="text" name="new_last_name" id="newLastName" placeholder="Doe" required>
                        <span class="field-error" id="newLastNameError"></span>
                    </fieldset>
                </div>
                <div class="form-row">
                    <fieldset class="form-group">
                        <legend>Email</legend>
                        <input type="email" name="new_email" id="newEmail" placeholder="john.doe@gmail.com" required>
                        <span class="field-error" id="newEmailError"></span>
                    </fieldset>
                    <fieldset class="form-group">
                        <legend>Student Number</legend>
                        <input type="text" name="new_student_number" id="newStudentNum" placeholder="20241128734" required>
                        <span class="field-error" id="newStudentNumError"></span>
                    </fieldset>
                </div>
                <fieldset class="form-group form-group-full">
                    <legend>Password</legend>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="newPassword" placeholder="••••••••••••••••••••" required>
                        <button type="button" class="toggle-password" id="toggleNewPassword">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </fieldset>
                <button type="submit" class="btn-create-account">Create Account</button>
            </form>
        </div>
    </div>

    <!-- Success Notification Overlay -->
    <div class="confirm-modal-overlay" id="successModal" <?php if ($registerSuccess): ?>style="opacity:1;visibility:visible;"<?php endif; ?>>
        <div class="confirm-modal" <?php if ($registerSuccess): ?>style="transform:translateY(0) scale(1);opacity:1;"<?php endif; ?>>
            <div class="confirm-modal-icon" style="background:linear-gradient(135deg,#e8f8e8 0%,#d0f0d0 100%);color:#27ae60;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="confirm-modal-title">Registered Successfully!</h3>
            <p class="confirm-modal-msg">The new user account has been created and is now active.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="successModalOk" style="min-width:160px;">OK</button>
            </div>
        </div>
    </div>

    <!-- Error Notification Overlay -->
    <?php if (!empty($registerError)): ?>
    <div class="confirm-modal-overlay" id="errorModal" style="opacity:1;visibility:visible;">
        <div class="confirm-modal" style="transform:translateY(0) scale(1);opacity:1;">
            <div class="confirm-modal-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="confirm-modal-title">Registration Failed</h3>
            <p class="confirm-modal-msg"><?php echo htmlspecialchars($registerError); ?></p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="errorModalOk" style="min-width:160px;">OK</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit User Modal -->
    <div class="admin-modal-overlay" id="editUserModal">
        <div class="admin-modal" style="max-width:520px;">
            <button class="admin-modal-close" id="closeEditUserModal">&times;</button>
            <h2 class="register-title" style="font-size:1.4rem;margin-bottom:0.5rem;">Edit User</h2>
            <p class="register-subtitle" style="margin-bottom:1.5rem;">Update the user's credentials below. Leave password blank to keep it unchanged.</p>
            <form class="register-form" method="POST" action="users.php" id="editUserForm">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="edit_user_id" id="editUserId" value="">
                <div class="form-row">
                    <fieldset class="form-group">
                        <legend>First Name</legend>
                        <input type="text" name="edit_first_name" id="editFirstName" placeholder="John" required>
                        <span class="field-error" id="editFirstNameError"></span>
                    </fieldset>
                    <fieldset class="form-group">
                        <legend>Last Name</legend>
                        <input type="text" name="edit_last_name" id="editLastName" placeholder="Doe" required>
                        <span class="field-error" id="editLastNameError"></span>
                    </fieldset>
                </div>
                <div class="form-row">
                    <fieldset class="form-group">
                        <legend>Email</legend>
                        <input type="email" name="edit_email" id="editEmail" placeholder="john.doe@gmail.com" required>
                        <span class="field-error" id="editEmailError"></span>
                    </fieldset>
                    <fieldset class="form-group">
                        <legend>Student Number</legend>
                        <input type="text" name="edit_student_number" id="editStudentNum" placeholder="20241128734" required>
                        <span class="field-error" id="editStudentNumError"></span>
                    </fieldset>
                </div>
                <fieldset class="form-group form-group-full">
                    <legend>New Password (optional)</legend>
                    <div class="password-wrapper">
                        <input type="password" name="edit_password" id="editPassword" placeholder="Leave blank to keep current">
                        <button type="button" class="toggle-password" id="toggleEditPassword">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </fieldset>
                <button type="submit" class="btn-create-account">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Edit Success Modal -->
    <div class="confirm-modal-overlay" id="editSuccessModal" <?php if ($editSuccess): ?>style="opacity:1;visibility:visible;"<?php endif; ?>>
        <div class="confirm-modal" <?php if ($editSuccess): ?>style="transform:translateY(0) scale(1);opacity:1;"<?php endif; ?>>
            <div class="confirm-modal-icon" style="background:linear-gradient(135deg,#e8f8e8 0%,#d0f0d0 100%);color:#27ae60;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="confirm-modal-title">User Updated Successfully!</h3>
            <p class="confirm-modal-msg">The user credentials have been updated.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="editSuccessOk" style="min-width:160px;">OK</button>
            </div>
        </div>
    </div>

    <!-- Edit Error Modal -->
    <?php if (!empty($editError)): ?>
    <div class="confirm-modal-overlay" id="editErrorModal" style="opacity:1;visibility:visible;">
        <div class="confirm-modal" style="transform:translateY(0) scale(1);opacity:1;">
            <div class="confirm-modal-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="confirm-modal-title">Update Failed</h3>
            <p class="confirm-modal-msg"><?php echo htmlspecialchars($editError); ?></p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="editErrorOk" style="min-width:160px;">OK</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Confirm Modal -->
    <div class="confirm-modal-overlay" id="deleteModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </div>
            <h3 class="confirm-modal-title">Delete this account?</h3>
            <p class="confirm-modal-msg" id="deleteModalMsg">This action cannot be undone.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-cancel" id="deleteCancelBtn">Cancel</button>
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="deleteConfirmBtn">Delete</button>
            </div>
            <form method="POST" action="users.php" id="deleteUserForm" style="display:none;">
                <input type="hidden" name="delete_user_id" id="deleteUserId" value="">
            </form>
        </div>
    </div>

    <!-- Delete Success Modal -->
    <div class="confirm-modal-overlay" id="deleteSuccessModal" <?php if ($deleteSuccess): ?>style="opacity:1;visibility:visible;"<?php endif; ?>>
        <div class="confirm-modal" <?php if ($deleteSuccess): ?>style="transform:translateY(0) scale(1);opacity:1;"<?php endif; ?>>
            <div class="confirm-modal-icon" style="background:linear-gradient(135deg,#e8f8e8 0%,#d0f0d0 100%);color:#27ae60;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="confirm-modal-title">Account Deleted</h3>
            <p class="confirm-modal-msg">The user account has been permanently removed.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="deleteSuccessOk" style="min-width:160px;">OK</button>
            </div>
        </div>
    </div>

    <script>
        // Delete user confirm
        function confirmDeleteUser(userId, userName) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('deleteModalMsg').textContent = 'Are you sure you want to delete the account of "' + userName + '"? This action cannot be undone.';
            document.getElementById('deleteUserId').value = userId;
            modal.classList.add('open');
        }

        const deleteCancelBtn = document.getElementById('deleteCancelBtn');
        const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        const deleteModal = document.getElementById('deleteModal');

        if (deleteCancelBtn) {
            deleteCancelBtn.addEventListener('click', () => deleteModal.classList.remove('open'));
        }
        if (deleteModal) {
            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) deleteModal.classList.remove('open');
            });
        }
        if (deleteConfirmBtn) {
            deleteConfirmBtn.addEventListener('click', () => {
                document.getElementById('deleteUserForm').submit();
            });
        }

        // Delete success modal
        const deleteSuccessModal = document.getElementById('deleteSuccessModal');
        const deleteSuccessOk = document.getElementById('deleteSuccessOk');
        if (deleteSuccessOk) {
            deleteSuccessOk.addEventListener('click', () => {
                deleteSuccessModal.style.opacity = '0';
                deleteSuccessModal.style.visibility = 'hidden';
                window.location.href = 'users.php';
            });
            if (deleteSuccessModal) {
                deleteSuccessModal.addEventListener('click', (e) => {
                    if (e.target === deleteSuccessModal) {
                        deleteSuccessModal.style.opacity = '0';
                        deleteSuccessModal.style.visibility = 'hidden';
                        window.location.href = 'users.php';
                    }
                });
            }
        }

        // Edit User Modal
        function openEditUser(id, fn, ln, email, sn) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFirstName').value = fn;
            document.getElementById('editLastName').value = ln;
            document.getElementById('editEmail').value = email;
            document.getElementById('editStudentNum').value = sn;
            document.getElementById('editPassword').value = '';
            // Clear any previous errors
            ['editFirstNameError','editLastNameError','editEmailError','editStudentNumError'].forEach(function(eid) {
                var el = document.getElementById(eid);
                if (el) { el.textContent = ''; el.style.display = 'none'; }
            });
            document.getElementById('editUserModal').classList.add('open');
        }

        const editUserModal = document.getElementById('editUserModal');
        const closeEditUserModal = document.getElementById('closeEditUserModal');
        if (closeEditUserModal && editUserModal) {
            closeEditUserModal.addEventListener('click', () => editUserModal.classList.remove('open'));
            editUserModal.addEventListener('click', (e) => {
                if (e.target === editUserModal) editUserModal.classList.remove('open');
            });
        }

        // Toggle edit password visibility
        const toggleEditPassword = document.getElementById('toggleEditPassword');
        const editPasswordInput = document.getElementById('editPassword');
        if (toggleEditPassword && editPasswordInput) {
            toggleEditPassword.addEventListener('click', () => {
                const type = editPasswordInput.type === 'password' ? 'text' : 'password';
                editPasswordInput.type = type;
                toggleEditPassword.querySelector('.eye-icon').innerHTML = type === 'password'
                    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
                    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            });
        }

        // Edit form validation
        const editUserForm = document.getElementById('editUserForm');
        const editFirstName = document.getElementById('editFirstName');
        const editLastName = document.getElementById('editLastName');
        const editStudentNum = document.getElementById('editStudentNum');
        const editFirstNameError = document.getElementById('editFirstNameError');
        const editLastNameError = document.getElementById('editLastNameError');
        const editStudentNumError = document.getElementById('editStudentNumError');

        function validateEditName(input, errorEl, label) {
            if (/\d/.test(input.value.trim())) {
                errorEl.textContent = 'Numbers are not allowed in ' + label;
                errorEl.style.display = 'block';
                input.closest('.form-group').style.borderColor = '#c0392b';
                return false;
            }
            errorEl.textContent = '';
            errorEl.style.display = 'none';
            input.closest('.form-group').style.borderColor = '';
            return true;
        }

        function validateEditStudentNum() {
            if (/[a-zA-Z]/.test(editStudentNum.value.trim())) {
                editStudentNumError.textContent = 'Letters are not allowed in Student Number';
                editStudentNumError.style.display = 'block';
                editStudentNum.closest('.form-group').style.borderColor = '#c0392b';
                return false;
            }
            editStudentNumError.textContent = '';
            editStudentNumError.style.display = 'none';
            editStudentNum.closest('.form-group').style.borderColor = '';
            return true;
        }

        if (editFirstName) editFirstName.addEventListener('input', () => validateEditName(editFirstName, editFirstNameError, 'First Name'));
        if (editLastName) editLastName.addEventListener('input', () => validateEditName(editLastName, editLastNameError, 'Last Name'));
        if (editStudentNum) editStudentNum.addEventListener('input', () => validateEditStudentNum());

        if (editUserForm) {
            editUserForm.addEventListener('submit', function(e) {
                const fnOk = validateEditName(editFirstName, editFirstNameError, 'First Name');
                const lnOk = validateEditName(editLastName, editLastNameError, 'Last Name');
                const snOk = validateEditStudentNum();
                if (!fnOk || !lnOk || !snOk) e.preventDefault();
            });
        }

        // Edit success modal
        const editSuccessModal = document.getElementById('editSuccessModal');
        const editSuccessOk = document.getElementById('editSuccessOk');
        if (editSuccessOk) {
            editSuccessOk.addEventListener('click', () => {
                editSuccessModal.style.opacity = '0';
                editSuccessModal.style.visibility = 'hidden';
                window.location.href = 'users.php';
            });
            if (editSuccessModal) {
                editSuccessModal.addEventListener('click', (e) => {
                    if (e.target === editSuccessModal) {
                        editSuccessModal.style.opacity = '0';
                        editSuccessModal.style.visibility = 'hidden';
                        window.location.href = 'users.php';
                    }
                });
            }
        }

        // Edit error modal
        const editErrorModal = document.getElementById('editErrorModal');
        const editErrorOk = document.getElementById('editErrorOk');
        if (editErrorOk) {
            editErrorOk.addEventListener('click', () => {
                editErrorModal.style.opacity = '0';
                editErrorModal.style.visibility = 'hidden';
            });
            if (editErrorModal) {
                editErrorModal.addEventListener('click', (e) => {
                    if (e.target === editErrorModal) {
                        editErrorModal.style.opacity = '0';
                        editErrorModal.style.visibility = 'hidden';
                    }
                });
            }
        }

        // Add New User Modal
        const addNewUserBtn = document.getElementById('addNewUserBtn');
        const addNewUserModal = document.getElementById('addNewUserModal');
        const closeNewUserModal = document.getElementById('closeNewUserModal');

        if (addNewUserBtn && addNewUserModal) {
            addNewUserBtn.addEventListener('click', () => addNewUserModal.classList.add('open'));
            closeNewUserModal.addEventListener('click', () => addNewUserModal.classList.remove('open'));
            addNewUserModal.addEventListener('click', (e) => {
                if (e.target === addNewUserModal) addNewUserModal.classList.remove('open');
            });
        }

        // Toggle password visibility
        const toggleNewPassword = document.getElementById('toggleNewPassword');
        const newPasswordInput = document.getElementById('newPassword');
        if (toggleNewPassword && newPasswordInput) {
            toggleNewPassword.addEventListener('click', () => {
                const type = newPasswordInput.type === 'password' ? 'text' : 'password';
                newPasswordInput.type = type;
                toggleNewPassword.querySelector('.eye-icon').innerHTML = type === 'password'
                    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
                    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            });
        }

        // Modal form validation
        const addNewUserForm = document.getElementById('addNewUserForm');
        const newFirstName = document.getElementById('newFirstName');
        const newLastName = document.getElementById('newLastName');
        const newStudentNum = document.getElementById('newStudentNum');
        const newFirstNameError = document.getElementById('newFirstNameError');
        const newLastNameError = document.getElementById('newLastNameError');
        const newStudentNumError = document.getElementById('newStudentNumError');

        function validateNewName(input, errorEl, label) {
            if (/\d/.test(input.value.trim())) {
                errorEl.textContent = 'Numbers are not allowed in ' + label;
                errorEl.style.display = 'block';
                input.closest('.form-group').style.borderColor = '#c0392b';
                return false;
            }
            errorEl.textContent = '';
            errorEl.style.display = 'none';
            input.closest('.form-group').style.borderColor = '';
            return true;
        }

        function validateNewStudentNum() {
            if (/[a-zA-Z]/.test(newStudentNum.value.trim())) {
                newStudentNumError.textContent = 'Letters are not allowed in Student Number';
                newStudentNumError.style.display = 'block';
                newStudentNum.closest('.form-group').style.borderColor = '#c0392b';
                return false;
            }
            newStudentNumError.textContent = '';
            newStudentNumError.style.display = 'none';
            newStudentNum.closest('.form-group').style.borderColor = '';
            return true;
        }

        if (newFirstName) newFirstName.addEventListener('input', () => validateNewName(newFirstName, newFirstNameError, 'First Name'));
        if (newLastName) newLastName.addEventListener('input', () => validateNewName(newLastName, newLastNameError, 'Last Name'));
        if (newStudentNum) newStudentNum.addEventListener('input', () => validateNewStudentNum());

        if (addNewUserForm) {
            addNewUserForm.addEventListener('submit', function(e) {
                const fnOk = validateNewName(newFirstName, newFirstNameError, 'First Name');
                const lnOk = validateNewName(newLastName, newLastNameError, 'Last Name');
                const snOk = validateNewStudentNum();
                if (!fnOk || !lnOk || !snOk) e.preventDefault();
            });
        }

        // Success modal
        const successModal = document.getElementById('successModal');
        const successModalOk = document.getElementById('successModalOk');
        if (successModalOk) {
            successModalOk.addEventListener('click', () => {
                successModal.style.opacity = '0';
                successModal.style.visibility = 'hidden';
                window.location.href = 'users.php';
            });
            if (successModal) {
                successModal.addEventListener('click', (e) => {
                    if (e.target === successModal) {
                        successModal.style.opacity = '0';
                        successModal.style.visibility = 'hidden';
                        window.location.href = 'users.php';
                    }
                });
            }
        }

        // Error modal
        const errorModal = document.getElementById('errorModal');
        const errorModalOk = document.getElementById('errorModalOk');
        if (errorModalOk) {
            errorModalOk.addEventListener('click', () => {
                errorModal.style.opacity = '0';
                errorModal.style.visibility = 'hidden';
            });
            if (errorModal) {
                errorModal.addEventListener('click', (e) => {
                    if (e.target === errorModal) {
                        errorModal.style.opacity = '0';
                        errorModal.style.visibility = 'hidden';
                    }
                });
            }
        }

        // Sidebar profile photo upload
        const sidebarAvatarClickable = document.getElementById('sidebarAvatarClickable');
        const profilePhotoInput = document.getElementById('profilePhotoInput');
        const profilePhotoForm = document.getElementById('profilePhotoForm');
        if (sidebarAvatarClickable && profilePhotoInput && profilePhotoForm) {
            sidebarAvatarClickable.addEventListener('click', () => {
                profilePhotoInput.click();
            });
            profilePhotoInput.addEventListener('change', () => {
                if (profilePhotoInput.files.length > 0) {
                    profilePhotoForm.submit();
                }
            });
        }

        // Remove profile photo button
        const removeProfilePhotoBtn = document.getElementById('removeProfilePhotoBtn');
        const removeProfilePhotoForm = document.getElementById('removeProfilePhotoForm');
        if (removeProfilePhotoBtn && removeProfilePhotoForm) {
            removeProfilePhotoBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (confirm('Are you sure you want to remove your profile photo?')) {
                    removeProfilePhotoForm.submit();
                }
            });
        }

        // Search functionality
        const searchInput = document.getElementById('usersSearch');
        const table = document.getElementById('usersTable');
        const tbody = table ? table.querySelector('tbody') : null;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase();
                const rows = tbody.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }

        // Alphabetical filter dropdown
        const alphaBtn = document.getElementById('alphaFilterBtn');
        const alphaList = document.getElementById('alphaFilterList');
        const filterItems = alphaList ? alphaList.querySelectorAll('.filter-dropdown-item') : [];

        if (alphaBtn && alphaList) {
            alphaBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                alphaList.classList.toggle('open');
                alphaBtn.classList.toggle('active');
            });

            filterItems.forEach(item => {
                item.addEventListener('click', function () {
                    filterItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    const sort = this.dataset.sort;
                    const label = sort === 'az' ? 'Ascending' : sort === 'za' ? 'Descending' : sort === 'newest' ? 'Newest' : 'Oldest';
                    alphaBtn.childNodes.forEach(n => {
                        if (n.nodeType === 3 && n.textContent.trim()) n.textContent = ' ' + label + ' ';
                    });

                    sortTable(sort);
                    alphaList.classList.remove('open');
                    alphaBtn.classList.remove('active');
                });
            });

            document.addEventListener('click', function () {
                alphaList.classList.remove('open');
                alphaBtn.classList.remove('active');
            });
        }

        function sortTable(mode) {
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length <= 1 && rows[0] && rows[0].querySelector('td[colspan]')) return;

            rows.sort((a, b) => {
                if (mode === 'az' || mode === 'za') {
                    const nameA = (a.querySelector('.user-name') || {}).textContent || '';
                    const nameB = (b.querySelector('.user-name') || {}).textContent || '';
                    return mode === 'az'
                        ? nameA.localeCompare(nameB)
                        : nameB.localeCompare(nameA);
                }
                if (mode === 'newest' || mode === 'oldest') {
                    const dateA = new Date(a.dataset.created || 0).getTime();
                    const dateB = new Date(b.dataset.created || 0).getTime();
                    return mode === 'newest' ? dateB - dateA : dateA - dateB;
                }
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        // ── Topbar Clock & Weather ──
        (function() {
            function updateClock() {
                var now = new Date();
                document.getElementById('twTime').textContent = now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true, timeZone:'Asia/Manila'});
                document.getElementById('twDate').textContent = now.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric', year:'numeric', timeZone:'Asia/Manila'});
            }
            updateClock();
            setInterval(updateClock, 1000);

            var wMap = {0:'Clear Sky',1:'Mainly Clear',2:'Partly Cloudy',3:'Overcast',45:'Foggy',48:'Rime Fog',51:'Light Drizzle',53:'Drizzle',55:'Heavy Drizzle',61:'Light Rain',63:'Rain',65:'Heavy Rain',71:'Light Snow',73:'Snow',75:'Heavy Snow',80:'Light Showers',81:'Showers',82:'Heavy Showers',95:'Thunderstorm',96:'Hail Storm',99:'Severe Storm'};

            function setIcon(code) {
                var el = document.getElementById('twWeatherIcon'), s, b;
                if (code <= 1) { b='linear-gradient(135deg,#f39c12,#e67e22)'; s='<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'; }
                else if (code <= 3) { b='linear-gradient(135deg,#74b9ff,#0984e3)'; s='<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>'; }
                else if (code <= 55 || (code >= 61 && code <= 65) || (code >= 80 && code <= 82)) { b='linear-gradient(135deg,#636e72,#2d3436)'; s='<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="16" y1="13" x2="16" y2="21"/><line x1="8" y1="13" x2="8" y2="21"/><line x1="12" y1="15" x2="12" y2="23"/><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"/></svg>'; }
                else if (code >= 95) { b='linear-gradient(135deg,#a29bfe,#6c5ce7)'; s='<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 16.9A5 5 0 0 0 18 7h-1.26a8 8 0 1 0-11.62 9"/><polyline points="13 11 9 17 15 17 11 23"/></svg>'; }
                else { b='linear-gradient(135deg,#dfe6e9,#b2bec3)'; s='<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>'; }
                el.style.background = b; el.style.boxShadow = '0 3px 10px rgba(0,0,0,0.15)'; el.innerHTML = s;
            }

            function fetchWeather() {
                fetch('https://api.open-meteo.com/v1/forecast?latitude=14.5995&longitude=120.9842&current=temperature_2m,weather_code&timezone=Asia%2FManila')
                    .then(function(r){return r.json();})
                    .then(function(d){
                        document.getElementById('twTemp').textContent = Math.round(d.current.temperature_2m) + '\u00b0C';
                        document.getElementById('twDesc').textContent = wMap[d.current.weather_code] || 'Unknown';
                        setIcon(d.current.weather_code);
                    }).catch(function(){
                        document.getElementById('twTemp').textContent = '--\u00b0C';
                        document.getElementById('twDesc').textContent = 'Unavailable';
                    });
            }
            fetchWeather();
            setInterval(fetchWeather, 600000);
        })();
    </script>
</body>
</html>
