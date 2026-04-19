<?php
session_start();
require_once 'connect.php';

// Only allow admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$isSuperAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;

// Fetch all users for the admin panel
$users = [];
$result = $conn->query("SELECT id, first_name, last_name, email, student_number, created_at FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// ── Handle add workspace admin (super admin only) ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_workspace_admin'])) {
    // Permission enforced at backend — reject silently if not super admin
    if (!$isSuperAdmin) {
        header("Location: admin.php");
        exit;
    }

    $fn    = trim($_POST['admin_first_name']   ?? '');
    $ln    = trim($_POST['admin_last_name']    ?? '');
    $em    = trim($_POST['admin_email']        ?? '');
    $pw    = $_POST['admin_password']          ?? '';
    $cpw   = $_POST['admin_confirm_password']  ?? '';
    $en    = trim($_POST['admin_employee_number'] ?? '');

    $errors = [];
    if (empty($fn))  $errors[] = 'First name is required.';
    if (empty($ln))  $errors[] = 'Last name is required.';
    if (empty($em))  $errors[] = 'Email is required.';
    if (empty($pw))  $errors[] = 'Password is required.';
    if ($pw !== $cpw) $errors[] = 'Passwords do not match.';
    if (empty($en))  $errors[] = 'Employee number is required.';
    if (preg_match('/\d/', $fn))         $errors[] = 'Numbers not allowed in First Name.';
    if (preg_match('/\d/', $ln))         $errors[] = 'Numbers not allowed in Last Name.';
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

    if (empty($errors)) {
        // Check email uniqueness across users + workspace_admins
        $chk = $conn->prepare("SELECT id FROM workspace_admins WHERE email = ? UNION SELECT id FROM users WHERE email = ?");
        $chk->bind_param("ss", $em, $em);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $errors[] = 'Email is already registered.';
        }
        $chk->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO workspace_admins (first_name, last_name, email, password, employee_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $fn, $ln, $em, $hashed, $en);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?admin_added=1");
        exit;
    }
    // On error fall through — page reloads; errors shown via JS alert
    $addAdminErrors = $errors;
}

// ── Handle website name update (super admin only) ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_website_name'])) {
    if (!$isSuperAdmin) { header("Location: admin.php"); exit; }
    $wn = trim($_POST['website_name']);
    if (!empty($wn)) {
        $stmt = $conn->prepare("UPDATE admin_profile SET website_name = ? WHERE id = 1");
        $stmt->bind_param("s", $wn);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php");
        exit;
    }
}

// ── Handle settings save (language) ───────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_settings'])) {
    $lang = trim($_POST['language']);
    $validLangs = ['English', 'Filipino', 'Spanish', 'Japanese', 'Korean'];
    if (in_array($lang, $validLangs)) {
        $stmt = $conn->prepare("UPDATE admin_profile SET language = ? WHERE id = 1");
        $stmt->bind_param("s", $lang);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php");
    exit;
}

// ── Handle website logo upload (super admin only) ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_logo'])) {
    if (!$isSuperAdmin) { header("Location: admin.php"); exit; }
    if (isset($_FILES['website_logo']) && $_FILES['website_logo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['website_logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (in_array($mimeType, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = match($mimeType) {
                'image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/gif'  => 'gif', 'image/webp' => 'webp',
            };
            $newName   = 'website_logo_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/images/';
            $old = $conn->query("SELECT website_logo FROM admin_profile WHERE id = 1")->fetch_assoc();
            if ($old && !empty($old['website_logo'])) {
                $oldPath = $uploadDir . $old['website_logo'];
                if (file_exists($oldPath)) unlink($oldPath);
            }
            foreach (glob($uploadDir . 'website_logo_*') as $sf) unlink($sf);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $stmt = $conn->prepare("UPDATE admin_profile SET website_logo = ? WHERE id = 1");
                $stmt->bind_param("s", $newName);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header("Location: admin.php"); exit;
}

// ── Handle admin profile photo upload ─────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_profile_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['profile_photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (in_array($mimeType, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = match($mimeType) {
                'image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/gif'  => 'gif', 'image/webp' => 'webp',
            };
            $emailHash = md5(strtolower(trim($_SESSION['email'])));
            $newName   = 'profile_' . $emailHash . '.' . $ext;
            $uploadDir = __DIR__ . '/images/';
            foreach (glob($uploadDir . 'profile_' . $emailHash . '.*') as $of) unlink($of);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $adminEmail = $_SESSION['email'];
                $stmt = $conn->prepare("INSERT INTO admin_photos (email, photo) VALUES (?, ?) ON DUPLICATE KEY UPDATE photo = ?");
                $stmt->bind_param("sss", $adminEmail, $newName, $newName);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header("Location: admin.php"); exit;
}

// ── Handle remove profile photo ────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_profile_photo'])) {
    $emailHash = md5(strtolower(trim($_SESSION['email'])));
    $uploadDir = __DIR__ . '/images/';
    foreach (glob($uploadDir . 'profile_' . $emailHash . '.*') as $of) unlink($of);
    $stmt = $conn->prepare("DELETE FROM admin_photos WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $stmt->close();
    header("Location: admin.php"); exit;
}

// ── Handle remove website logo (super admin only) ──────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_website_logo'])) {
    if (!$isSuperAdmin) { header("Location: admin.php"); exit; }
    $uploadDir = __DIR__ . '/images/';
    foreach (glob($uploadDir . 'website_logo_*') as $sf) unlink($sf);
    $conn->query("UPDATE admin_profile SET website_logo = '' WHERE id = 1");
    header("Location: admin.php"); exit;
}

// ── Fetch admin profile ────────────────────────────────────────────────────
$adminProfile   = $conn->query("SELECT first_name, last_name, website_name, language, website_logo FROM admin_profile WHERE id = 1")->fetch_assoc();
$adminFirstName = $adminProfile ? $adminProfile['first_name'] : $_SESSION['first_name'];
$adminLastName  = $adminProfile ? $adminProfile['last_name']  : $_SESSION['last_name'];
$websiteName    = $adminProfile && !empty($adminProfile['website_name']) ? $adminProfile['website_name'] : 'UEsed Books';
$currentLang    = $adminProfile && !empty($adminProfile['language'])     ? $adminProfile['language']     : 'English';
$websiteLogo    = $adminProfile && !empty($adminProfile['website_logo']) ? $adminProfile['website_logo'] : '';

// Fetch ALL admin photos
$allAdminPhotos = [];
$photoRows = $conn->query("SELECT email, photo FROM admin_photos");
if ($photoRows) {
    while ($pr = $photoRows->fetch_assoc()) {
        if (!empty($pr['photo'])) $allAdminPhotos[strtolower($pr['email'])] = $pr['photo'];
    }
}
$adminPhoto      = $allAdminPhotos[strtolower($_SESSION['email'])] ?? '';
$superAdminPhoto = $allAdminPhotos['admin@gmail.com'] ?? '';

// Translations
$translations = [
    'English' => [
        'company' => 'Company', 'general' => 'General', 'books' => 'Books',
        'users' => 'Users', 'statistic' => 'Statistic', 'transaction' => 'Transaction',
        'sign_out' => 'Sign out', 'workspace_admins' => 'Workspace admins',
        'administrator' => 'Administrator', 'admin' => 'Admin', 'add_admin' => 'Add admin',
        'settings' => 'Settings', 'week_start' => 'Week start',
        'week_start_desc' => 'Choose which day your week begins',
        'language' => 'Language', 'language_desc' => 'Set your preferred display language',
        'email_alerts' => 'Email alerts',
        'email_alerts_desc' => 'Get notified via email for important updates',
        'save_changes' => 'Save Changes', 'book_coming' => 'Book management coming soon.',
        'users_overview' => 'Users Overview', 'select_submenu' => 'Select a sub-menu: Statistic or Users.',
        'total_users' => 'Total Users', 'total_books' => 'Total Books', 'transactions' => 'Transactions',
        'id' => 'ID', 'name' => 'Name', 'email' => 'Email',
        'student_number' => 'Student Number', 'registered' => 'Registered',
        'no_users' => 'No users registered yet.', 'transaction_coming' => 'Transaction management coming soon.',
        'edit_website' => 'Edit Website Name', 'edit_website_desc' => 'Update the displayed website name',
        'website_name_label' => 'Website Name', 'save' => 'Save',
        'add_workspace_admin' => 'Add Workspace Admin',
        'add_admin_desc' => 'Register a new admin account',
        'first_name' => 'First Name', 'last_name' => 'Last Name',
        'register_admin' => 'Register Admin',
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ],
    'Filipino' => [
        'company' => 'Kumpanya', 'general' => 'Pangkalahatan', 'books' => 'Mga Aklat',
        'users' => 'Mga Gumagamit', 'statistic' => 'Istatistika', 'transaction' => 'Transaksyon',
        'sign_out' => 'Mag-sign out', 'workspace_admins' => 'Mga admin ng workspace',
        'administrator' => 'Tagapangasiwa', 'admin' => 'Admin', 'add_admin' => 'Magdagdag ng admin',
        'settings' => 'Mga Setting', 'week_start' => 'Simula ng linggo',
        'week_start_desc' => 'Piliin kung anong araw magsisimula ang iyong linggo',
        'language' => 'Wika', 'language_desc' => 'Itakda ang iyong gustong wika',
        'email_alerts' => 'Mga alerto sa email',
        'email_alerts_desc' => 'Maabisuhan sa pamamagitan ng email para sa mahahalagang update',
        'save_changes' => 'I-save ang mga Pagbabago', 'book_coming' => 'Malapit nang dumating ang pamamahala ng aklat.',
        'users_overview' => 'Pangkalahatang-tanaw ng Gumagamit', 'select_submenu' => 'Pumili ng sub-menu: Istatistika o Mga Gumagamit.',
        'total_users' => 'Kabuuang Gumagamit', 'total_books' => 'Kabuuang Aklat', 'transactions' => 'Mga Transaksyon',
        'id' => 'ID', 'name' => 'Pangalan', 'email' => 'Email',
        'student_number' => 'Numero ng Estudyante', 'registered' => 'Petsa ng Pagrehistro',
        'no_users' => 'Wala pang nakarehistrong gumagamit.', 'transaction_coming' => 'Malapit nang dumating ang pamamahala ng transaksyon.',
        'edit_website' => 'I-edit ang Pangalan ng Website', 'edit_website_desc' => 'I-update ang ipinapakitang pangalan ng website',
        'website_name_label' => 'Pangalan ng Website', 'save' => 'I-save',
        'add_workspace_admin' => 'Magdagdag ng Workspace Admin',
        'add_admin_desc' => 'Magrehistro ng bagong admin account',
        'first_name' => 'Unang Pangalan', 'last_name' => 'Apelyido',
        'register_admin' => 'Irehistro ang Admin',
        'monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miyerkules',
        'thursday' => 'Huwebes', 'friday' => 'Biyernes', 'saturday' => 'Sabado', 'sunday' => 'Linggo',
    ],
    'Spanish' => [
        'company' => 'Empresa', 'general' => 'General', 'books' => 'Libros',
        'users' => 'Usuarios', 'statistic' => 'Estadística', 'transaction' => 'Transacción',
        'sign_out' => 'Cerrar sesión', 'workspace_admins' => 'Administradores del espacio',
        'administrator' => 'Administrador', 'admin' => 'Admin', 'add_admin' => 'Agregar admin',
        'settings' => 'Configuración', 'week_start' => 'Inicio de semana',
        'week_start_desc' => 'Elige qué día comienza tu semana',
        'language' => 'Idioma', 'language_desc' => 'Establece tu idioma preferido',
        'email_alerts' => 'Alertas por correo',
        'email_alerts_desc' => 'Recibe notificaciones por correo para actualizaciones importantes',
        'save_changes' => 'Guardar Cambios', 'book_coming' => 'Gestión de libros próximamente.',
        'users_overview' => 'Resumen de Usuarios', 'select_submenu' => 'Selecciona un sub-menú: Estadística o Usuarios.',
        'total_users' => 'Total Usuarios', 'total_books' => 'Total Libros', 'transactions' => 'Transacciones',
        'id' => 'ID', 'name' => 'Nombre', 'email' => 'Correo',
        'student_number' => 'Número de Estudiante', 'registered' => 'Registrado',
        'no_users' => 'No hay usuarios registrados aún.', 'transaction_coming' => 'Gestión de transacciones próximamente.',
        'edit_website' => 'Editar Nombre del Sitio', 'edit_website_desc' => 'Actualizar el nombre mostrado del sitio web',
        'website_name_label' => 'Nombre del Sitio', 'save' => 'Guardar',
        'add_workspace_admin' => 'Agregar Admin del Espacio',
        'add_admin_desc' => 'Registrar una nueva cuenta de admin',
        'first_name' => 'Nombre', 'last_name' => 'Apellido',
        'register_admin' => 'Registrar Admin',
        'monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles',
        'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado', 'sunday' => 'Domingo',
    ],
    'Japanese' => [
        'company' => '会社', 'general' => '一般', 'books' => '書籍',
        'users' => 'ユーザー', 'statistic' => '統計', 'transaction' => '取引',
        'sign_out' => 'サインアウト', 'workspace_admins' => 'ワークスペース管理者',
        'administrator' => '管理者', 'admin' => '管理者', 'add_admin' => '管理者を追加',
        'settings' => '設定', 'week_start' => '週の開始日',
        'week_start_desc' => '週の始まりの曜日を選択してください',
        'language' => '言語', 'language_desc' => '表示言語を設定してください',
        'email_alerts' => 'メール通知', 'email_alerts_desc' => '重要な更新をメールで受け取る',
        'save_changes' => '変更を保存', 'book_coming' => '書籍管理は近日公開予定です。',
        'users_overview' => 'ユーザー概要', 'select_submenu' => 'サブメニューを選択：統計またはユーザー',
        'total_users' => '合計ユーザー', 'total_books' => '合計書籍', 'transactions' => '取引数',
        'id' => 'ID', 'name' => '名前', 'email' => 'メール',
        'student_number' => '学籍番号', 'registered' => '登録日',
        'no_users' => 'まだ登録されたユーザーはいません。', 'transaction_coming' => '取引管理は近日公開予定です。',
        'edit_website' => 'ウェブサイト名を編集', 'edit_website_desc' => '表示されるウェブサイト名を更新',
        'website_name_label' => 'ウェブサイト名', 'save' => '保存',
        'add_workspace_admin' => 'ワークスペース管理者を追加',
        'add_admin_desc' => '新しい管理者アカウントを登録',
        'first_name' => '名', 'last_name' => '姓',
        'register_admin' => '管理者を登録',
        'monday' => '月曜日', 'tuesday' => '火曜日', 'wednesday' => '水曜日',
        'thursday' => '木曜日', 'friday' => '金曜日', 'saturday' => '土曜日', 'sunday' => '日曜日',
    ],
    'Korean' => [
        'company' => '회사', 'general' => '일반', 'books' => '도서',
        'users' => '사용자', 'statistic' => '통계', 'transaction' => '거래',
        'sign_out' => '로그아웃', 'workspace_admins' => '워크스페이스 관리자',
        'administrator' => '관리자', 'admin' => '관리자', 'add_admin' => '관리자 추가',
        'settings' => '설정', 'week_start' => '주 시작일',
        'week_start_desc' => '주가 시작되는 요일을 선택하세요',
        'language' => '언어', 'language_desc' => '표시 언어를 설정하세요',
        'email_alerts' => '이메일 알림', 'email_alerts_desc' => '중요한 업데이트를 이메일로 받기',
        'save_changes' => '변경 사항 저장', 'book_coming' => '도서 관리 기능이 곧 제공됩니다.',
        'users_overview' => '사용자 개요', 'select_submenu' => '하위 메뉴를 선택하세요: 통계 또는 사용자',
        'total_users' => '전체 사용자', 'total_books' => '전체 도서', 'transactions' => '거래 수',
        'id' => 'ID', 'name' => '이름', 'email' => '이메일',
        'student_number' => '학번', 'registered' => '등록일',
        'no_users' => '아직 등록된 사용자가 없습니다.', 'transaction_coming' => '거래 관리 기능이 곧 제공됩니다.',
        'edit_website' => '웹사이트 이름 편집', 'edit_website_desc' => '표시되는 웹사이트 이름 업데이트',
        'website_name_label' => '웹사이트 이름', 'save' => '저장',
        'add_workspace_admin' => '워크스페이스 관리자 추가',
        'add_admin_desc' => '새 관리자 계정 등록',
        'first_name' => '이름', 'last_name' => '성',
        'register_admin' => '관리자 등록',
        'monday' => '월요일', 'tuesday' => '화요일', 'wednesday' => '수요일',
        'thursday' => '목요일', 'friday' => '금요일', 'saturday' => '토요일', 'sunday' => '일요일',
    ],
];
$t = $translations[$currentLang] ?? $translations['English'];

// Fetch workspace admins (now includes email, employee_number, password)
$workspace_admins = [];
$result2 = $conn->query("SELECT id, first_name, last_name, email, employee_number, created_at FROM workspace_admins ORDER BY created_at ASC");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $workspace_admins[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - UEsed Books</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rammetto+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!--
        Minimal validation styles — same classes as register.html / login.php.
        No existing style.css rules are overridden.
    -->
    <style>
        .val-error {
            display: block;
            font-size: 0.775rem;
            color: #c0392b;
            font-weight: 500;
            font-family: 'Segoe UI', system-ui, sans-serif;
            letter-spacing: 0.01em;
            padding-left: 1px;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            margin-top: 0;
            transition: max-height 0.28s ease, opacity 0.28s ease, margin-top 0.28s ease;
        }
        .val-error.val-error--show {
            max-height: 36px;
            opacity: 1;
            margin-top: 5px;
        }
        .val-error.val-error--show::before {
            content: "\26A0\00A0";
            font-size: 0.72rem;
        }
        .val-has-error {
            border-color: #c0392b !important;
            background-color: rgba(192, 57, 43, 0.035) !important;
        }
        .val-is-valid {
            border-color: #27ae60 !important;
        }
        /* Employee number tag shown on admin cards */
        .admin-card-empnum {
            font-size: 0.72rem;
            color: #aaa;
            margin-top: 2px;
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
                <a href="#general" class="sidebar-link active" data-section="general">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <?php echo $t['general']; ?>
                </a>
                <a href="books.php" class="sidebar-link" data-section="books">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <?php echo $t['books']; ?>
                </a>
                <a href="users.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <?php echo $t['users']; ?>
                </a>
                <a href="transaction.php" class="sidebar-link" data-section="transaction">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <?php echo $t['transaction']; ?>
                </a>
            </nav>

            <a href="login.php" class="sidebar-signout">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <?php echo $t['sign_out']; ?>
            </a>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- General Section -->
            <section class="admin-section active" id="section-general">
                <div class="admin-workspace-header">
                    <?php if ($isSuperAdmin): ?>
                    <form method="POST" action="admin.php" enctype="multipart/form-data" id="logoUploadForm" style="margin:0;">
                        <input type="hidden" name="upload_logo" value="1">
                        <input type="file" name="website_logo" id="logoFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    </form>
                    <form method="POST" action="admin.php" id="removeWebsiteLogoForm" style="margin:0;">
                        <input type="hidden" name="remove_website_logo" value="1">
                    </form>
                    <div class="workspace-avatar workspace-avatar-clickable" id="avatarClickable" title="Change photo">
                        <?php if ($websiteLogo && file_exists(__DIR__ . '/images/' . $websiteLogo)): ?>
                            <img src="images/<?php echo htmlspecialchars($websiteLogo); ?>" alt="Website Logo" class="workspace-avatar-img">
                            <button type="button" class="avatar-remove-btn avatar-remove-btn-lg" id="removeWebsiteLogoBtn" title="Remove logo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <?php endif; ?>
                        <div class="workspace-avatar-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Sub-admin: logo visible but not clickable / changeable -->
                    <div class="workspace-avatar">
                        <?php if ($websiteLogo && file_exists(__DIR__ . '/images/' . $websiteLogo)): ?>
                            <img src="images/<?php echo htmlspecialchars($websiteLogo); ?>" alt="Website Logo" class="workspace-avatar-img">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="workspace-name-wrap">
                        <h1 class="workspace-name"><?php echo htmlspecialchars($websiteName); ?></h1>
                        <!-- Edit button: super admin only -->
                        <?php if ($isSuperAdmin): ?>
                        <button class="workspace-edit-btn" id="editProfileBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <h2 class="admin-section-title"><?php echo $t['workspace_admins']; ?></h2>
                <div class="admin-cards-row">
                    <!-- Super-admin card (always first) -->
                    <div class="admin-card">
                        <div class="admin-card-avatar">
                            <?php if ($superAdminPhoto && file_exists(__DIR__ . '/images/' . $superAdminPhoto)): ?>
                                <img src="images/<?php echo htmlspecialchars($superAdminPhoto); ?>" alt="" class="admin-card-avatar-img">
                            <?php else: ?>
                                <?php echo strtoupper(substr($adminFirstName, 0, 1) . substr($adminLastName, 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <p class="admin-card-name"><?php echo htmlspecialchars($adminFirstName . ' ' . $adminLastName); ?></p>
                        <p class="admin-card-role"><?php echo $t['administrator']; ?></p>
                    </div>

                    <!-- Workspace admin cards -->
                    <?php foreach ($workspace_admins as $wa): ?>
                    <div class="admin-card">
                        <?php if ($isSuperAdmin): ?>
                        <button class="admin-card-remove"
                            onclick="event.preventDefault(); showConfirm('Remove Admin', 'Are you sure you want to remove this admin?', () => { window.location.href='admin_remove.php?id=<?php echo (int)$wa['id']; ?>'; })">&times;</button>
                        <?php endif; ?>
                        <?php $waPhoto = $allAdminPhotos[strtolower($wa['email'])] ?? ''; ?>
                        <div class="admin-card-avatar">
                            <?php if ($waPhoto && file_exists(__DIR__ . '/images/' . $waPhoto)): ?>
                                <img src="images/<?php echo htmlspecialchars($waPhoto); ?>" alt="" class="admin-card-avatar-img">
                            <?php else: ?>
                                <?php echo strtoupper(substr($wa['first_name'], 0, 1) . substr($wa['last_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <p class="admin-card-name"><?php echo htmlspecialchars($wa['first_name'] . ' ' . $wa['last_name']); ?></p>
                        <p class="admin-card-role"><?php echo $t['admin']; ?></p>
                        <p class="admin-card-email" style="font-size:0.75rem;color:#888;margin-top:2px;"><?php echo htmlspecialchars($wa['email']); ?></p>
                        <?php if (!empty($wa['employee_number'])): ?>
                        <p class="admin-card-empnum">Emp #: <?php echo htmlspecialchars($wa['employee_number']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- "Add admin" card: super admin only -->
                    <?php if ($isSuperAdmin): ?>
                    <div class="admin-card admin-card-add" id="addAdminBtn">
                        <div class="admin-card-plus">+</div>
                        <p class="admin-card-role"><?php echo $t['add_admin']; ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <h2 class="admin-section-title"><?php echo $t['settings']; ?></h2>
                <form method="POST" action="admin.php" id="settingsForm">
                <input type="hidden" name="save_settings" value="1">
                <input type="hidden" name="language" id="languageInput" value="<?php echo htmlspecialchars($currentLang); ?>">
                <div class="admin-settings">
                    <div class="settings-row">
                        <div class="settings-left">
                            <div class="settings-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="settings-text">
                                <span class="settings-label"><?php echo $t['week_start']; ?></span>
                                <span class="settings-desc"><?php echo $t['week_start_desc']; ?></span>
                            </div>
                        </div>
                        <div class="custom-dropdown" id="weekStartDropdown">
                            <button type="button" class="custom-dropdown-btn">
                                <span class="custom-dropdown-value"><?php echo $t['monday']; ?></span>
                                <svg class="custom-dropdown-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <ul class="custom-dropdown-list">
                                <li class="custom-dropdown-item active" data-value="monday"><?php echo $t['monday']; ?></li>
                                <li class="custom-dropdown-item" data-value="tuesday"><?php echo $t['tuesday']; ?></li>
                                <li class="custom-dropdown-item" data-value="wednesday"><?php echo $t['wednesday']; ?></li>
                                <li class="custom-dropdown-item" data-value="thursday"><?php echo $t['thursday']; ?></li>
                                <li class="custom-dropdown-item" data-value="friday"><?php echo $t['friday']; ?></li>
                                <li class="custom-dropdown-item" data-value="saturday"><?php echo $t['saturday']; ?></li>
                                <li class="custom-dropdown-item" data-value="sunday"><?php echo $t['sunday']; ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-left">
                            <div class="settings-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            </div>
                            <div class="settings-text">
                                <span class="settings-label"><?php echo $t['language']; ?></span>
                                <span class="settings-desc"><?php echo $t['language_desc']; ?></span>
                            </div>
                        </div>
                        <div class="custom-dropdown" id="languageDropdown">
                            <button type="button" class="custom-dropdown-btn">
                                <span class="custom-dropdown-value"><?php echo htmlspecialchars($currentLang); ?></span>
                                <svg class="custom-dropdown-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <ul class="custom-dropdown-list">
                                <?php $langs = ['English', 'Filipino', 'Spanish', 'Japanese', 'Korean']; ?>
                                <?php foreach ($langs as $lang): ?>
                                <li class="custom-dropdown-item<?php echo $lang === $currentLang ? ' active' : ''; ?>" data-value="<?php echo $lang; ?>"><?php echo $lang; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-left">
                            <div class="settings-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 0 2 2z"/></svg>
                            </div>
                            <div class="settings-text">
                                <span class="settings-label"><?php echo $t['email_alerts']; ?></span>
                                <span class="settings-desc"><?php echo $t['email_alerts_desc']; ?></span>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="admin-save-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <?php echo $t['save_changes']; ?>
                </button>
                </form>
            </section>

            <!-- Books Section -->
            <section class="admin-section" id="section-books">
                <h2 class="admin-section-title"><?php echo $t['books']; ?></h2>
                <p style="color:#888;"><?php echo $t['book_coming']; ?></p>
            </section>

            <!-- Users Section -->
            <section class="admin-section" id="section-users">
                <h2 class="admin-section-title"><?php echo $t['users_overview']; ?></h2>
                <p style="color:#888;"><?php echo $t['select_submenu']; ?></p>
            </section>

            <!-- Statistic Section -->
            <section class="admin-section" id="section-statistic">
                <h2 class="admin-section-title"><?php echo $t['statistic']; ?></h2>
                <div class="admin-stats-grid">
                    <div class="stat-card">
                        <h3><?php echo count($users); ?></h3>
                        <p><?php echo $t['total_users']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>0</h3>
                        <p><?php echo $t['total_books']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>0</h3>
                        <p><?php echo $t['transactions']; ?></p>
                    </div>
                </div>
            </section>

            <!-- User List Section -->
            <section class="admin-section" id="section-userlist">
                <h2 class="admin-section-title"><?php echo $t['users']; ?></h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo $t['id']; ?></th>
                                <th><?php echo $t['name']; ?></th>
                                <th><?php echo $t['email']; ?></th>
                                <th><?php echo $t['student_number']; ?></th>
                                <th><?php echo $t['registered']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['student_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;color:#888;"><?php echo $t['no_users']; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Transaction Section -->
            <section class="admin-section" id="section-transaction">
                <h2 class="admin-section-title"><?php echo $t['transaction']; ?></h2>
                <p style="color:#888;"><?php echo $t['transaction_coming']; ?></p>
            </section>
        </main>
    </div>

    <!-- Edit Website Name Modal (super admin only) -->
    <?php if ($isSuperAdmin): ?>
    <div class="admin-modal-overlay" id="editProfileModal">
        <div class="admin-modal">
            <button class="admin-modal-close" id="closeProfileModal">&times;</button>
            <h2 class="register-title" style="font-size:1.4rem;margin-bottom:0.5rem;"><?php echo $t['edit_website']; ?></h2>
            <p class="register-subtitle" style="margin-bottom:1.5rem;"><?php echo $t['edit_website_desc']; ?></p>
            <form class="register-form" method="POST" action="admin.php" id="editProfileForm">
                <input type="hidden" name="update_website_name" value="1">
                <fieldset class="form-group form-group-full">
                    <legend><?php echo $t['website_name_label']; ?></legend>
                    <input type="text" name="website_name" id="websiteNameInput" value="<?php echo htmlspecialchars($websiteName); ?>" required>
                    <span class="field-error" id="websiteNameError"></span>
                </fieldset>
                <button type="submit" class="btn-create-account"><?php echo $t['save']; ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Admin Modal (super admin only) -->
    <?php if ($isSuperAdmin): ?>
    <div class="admin-modal-overlay" id="addAdminModal">
        <div class="admin-modal">
            <button class="admin-modal-close" id="closeAdminModal">&times;</button>
            <h2 class="register-title" style="font-size:1.4rem;margin-bottom:0.5rem;"><?php echo $t['add_workspace_admin']; ?></h2>
            <p class="register-subtitle" style="margin-bottom:1.5rem;"><?php echo $t['add_admin_desc']; ?></p>
            <form class="register-form" method="POST" action="admin.php" id="addAdminForm" novalidate>
                <input type="hidden" name="add_workspace_admin" value="1">

                <!-- Row 1: First Name / Last Name -->
                <div class="form-row">
                    <fieldset class="form-group" id="fg-modalFirstName">
                        <legend><?php echo $t['first_name']; ?> <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                        <input type="text" name="admin_first_name" id="modalFirstName" placeholder="John" autocomplete="off">
                        <span class="val-error" id="modalFirstNameError" role="alert" aria-live="polite"></span>
                    </fieldset>
                    <fieldset class="form-group" id="fg-modalLastName">
                        <legend><?php echo $t['last_name']; ?> <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                        <input type="text" name="admin_last_name" id="modalLastName" placeholder="Doe" autocomplete="off">
                        <span class="val-error" id="modalLastNameError" role="alert" aria-live="polite"></span>
                    </fieldset>
                </div>

                <!-- Email -->
                <fieldset class="form-group form-group-full" id="fg-modalEmail">
                    <legend>Email <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                    <input type="email" name="admin_email" id="modalEmail" placeholder="admin@example.com" autocomplete="off">
                    <span class="val-error" id="modalEmailError" role="alert" aria-live="polite"></span>
                </fieldset>

                <!-- Employee Number -->
                <fieldset class="form-group form-group-full" id="fg-modalEmployeeNum">
                    <legend>Employee Number <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                    <input type="text" name="admin_employee_number" id="modalEmployeeNum" placeholder="EMP-001" autocomplete="off">
                    <span class="val-error" id="modalEmployeeNumError" role="alert" aria-live="polite"></span>
                </fieldset>

                <!-- Password -->
                <fieldset class="form-group form-group-full" id="fg-modalPassword">
                    <legend>Password <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                    <div class="password-wrapper">
                        <input type="password" name="admin_password" id="modalPassword" placeholder="••••••••••••••••••••" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleModalPassword">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <span class="val-error" id="modalPasswordError" role="alert" aria-live="polite"></span>
                </fieldset>

                <!-- Confirm Password -->
                <fieldset class="form-group form-group-full" id="fg-modalConfirmPassword">
                    <legend>Re-enter Password <span style="color:#c0392b;font-size:0.82em;vertical-align:super;">*</span></legend>
                    <div class="password-wrapper">
                        <input type="password" name="admin_confirm_password" id="modalConfirmPassword" placeholder="••••••••••••••••••••" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleModalConfirmPassword">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <span class="val-error" id="modalConfirmPasswordError" role="alert" aria-live="polite"></span>
                </fieldset>

                <button type="submit" class="btn-create-account" id="addAdminSubmitBtn"><?php echo $t['register_admin']; ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Custom Confirm Modal -->
    <div class="confirm-modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="confirm-modal-title" id="confirmModalTitle">Are you sure?</h3>
            <p class="confirm-modal-msg" id="confirmModalMsg">This action cannot be undone.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-btn confirm-modal-cancel" id="confirmModalCancel">Cancel</button>
                <button type="button" class="confirm-modal-btn confirm-modal-ok" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    // ── Sidebar navigation ────────────────────────────────────────────────
    const sidebarLinks = document.querySelectorAll('.sidebar-link, .sidebar-sublink');
    const sections = document.querySelectorAll('.admin-section');

    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const section = link.dataset.section;
            if (!section) return;
            document.querySelectorAll('.sidebar-link, .sidebar-sublink').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            sections.forEach(s => s.classList.remove('active'));
            const target = document.getElementById('section-' + section);
            if (target) target.classList.add('active');
        });
    });

    // ── Custom Confirm Modal ──────────────────────────────────────────────
    function showConfirm(title, message, onConfirm) {
        const modal = document.getElementById('confirmModal');
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalMsg').textContent = message;
        modal.classList.add('open');
        const okBtn     = document.getElementById('confirmModalOk');
        const cancelBtn = document.getElementById('confirmModalCancel');
        const cleanup = () => {
            okBtn.replaceWith(okBtn.cloneNode(true));
            cancelBtn.replaceWith(cancelBtn.cloneNode(true));
        };
        const closeModal = () => { modal.classList.remove('open'); cleanup(); };
        document.getElementById('confirmModalOk')    .addEventListener('click', () => { closeModal(); onConfirm(); }, { once: true });
        document.getElementById('confirmModalCancel').addEventListener('click', () => { closeModal(); }, { once: true });
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); }, { once: true });
    }

    // ── Website logo upload ───────────────────────────────────────────────
    const avatarClickable = document.getElementById('avatarClickable');
    const logoFileInput   = document.getElementById('logoFileInput');
    const logoUploadForm  = document.getElementById('logoUploadForm');
    if (avatarClickable && logoFileInput && logoUploadForm) {
        avatarClickable.addEventListener('click', () => logoFileInput.click());
        logoFileInput.addEventListener('change', () => { if (logoFileInput.files.length > 0) logoUploadForm.submit(); });
    }

    // ── Sidebar profile photo upload ──────────────────────────────────────
    const sidebarAvatarClickable = document.getElementById('sidebarAvatarClickable');
    const profilePhotoInput      = document.getElementById('profilePhotoInput');
    const profilePhotoForm       = document.getElementById('profilePhotoForm');
    if (sidebarAvatarClickable && profilePhotoInput && profilePhotoForm) {
        sidebarAvatarClickable.addEventListener('click', () => profilePhotoInput.click());
        profilePhotoInput.addEventListener('change', () => { if (profilePhotoInput.files.length > 0) profilePhotoForm.submit(); });
    }

    // ── Remove profile photo ──────────────────────────────────────────────
    const removeProfilePhotoBtn  = document.getElementById('removeProfilePhotoBtn');
    const removeProfilePhotoForm = document.getElementById('removeProfilePhotoForm');
    if (removeProfilePhotoBtn && removeProfilePhotoForm) {
        removeProfilePhotoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showConfirm('Remove Photo', 'Are you sure you want to remove your profile photo?', () => removeProfilePhotoForm.submit());
        });
    }

    // ── Remove website logo ───────────────────────────────────────────────
    const removeWebsiteLogoBtn  = document.getElementById('removeWebsiteLogoBtn');
    const removeWebsiteLogoForm = document.getElementById('removeWebsiteLogoForm');
    if (removeWebsiteLogoBtn && removeWebsiteLogoForm) {
        removeWebsiteLogoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showConfirm('Remove Logo', 'Are you sure you want to remove the website logo?', () => removeWebsiteLogoForm.submit());
        });
    }

    // ── Edit Website Name Modal ───────────────────────────────────────────
    const editProfileBtn   = document.getElementById('editProfileBtn');
    const editProfileModal = document.getElementById('editProfileModal');
    const closeProfileModal = document.getElementById('closeProfileModal');
    const editProfileForm  = document.getElementById('editProfileForm');
    const websiteNameInput = document.getElementById('websiteNameInput');
    const websiteNameError = document.getElementById('websiteNameError');

    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', () => editProfileModal.classList.add('open'));
    }
    if (closeProfileModal) {
        closeProfileModal.addEventListener('click', () => editProfileModal.classList.remove('open'));
    }
    if (editProfileModal) {
        editProfileModal.addEventListener('click', (e) => { if (e.target === editProfileModal) editProfileModal.classList.remove('open'); });
    }
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', function(e) {
            if (!websiteNameInput.value.trim()) {
                websiteNameError.textContent = 'Website name cannot be empty';
                websiteNameError.style.display = 'block';
                websiteNameInput.closest('.form-group').style.borderColor = '#c0392b';
                e.preventDefault();
            }
        });
        websiteNameInput && websiteNameInput.addEventListener('input', function() {
            websiteNameError.textContent = '';
            websiteNameError.style.display = 'none';
            websiteNameInput.closest('.form-group').style.borderColor = '';
        });
    }

    // ── Add Admin Modal — validation (IIFE, no global leaks) ─────────────
    (function () {
        const addAdminBtn   = document.getElementById('addAdminBtn');
        const addAdminModal = document.getElementById('addAdminModal');
        const closeAdminModal = document.getElementById('closeAdminModal');
        if (!addAdminBtn || !addAdminModal) return;   // not super admin

        // Open / close
        addAdminBtn.addEventListener('click', () => addAdminModal.classList.add('open'));
        closeAdminModal.addEventListener('click', () => addAdminModal.classList.remove('open'));
        addAdminModal.addEventListener('click', (e) => { if (e.target === addAdminModal) addAdminModal.classList.remove('open'); });

        // Field refs
        const fn   = document.getElementById('modalFirstName');
        const ln   = document.getElementById('modalLastName');
        const em   = document.getElementById('modalEmail');
        const en   = document.getElementById('modalEmployeeNum');
        const pw   = document.getElementById('modalPassword');
        const cpw  = document.getElementById('modalConfirmPassword');

        const fgs = {
            fn:  document.getElementById('fg-modalFirstName'),
            ln:  document.getElementById('fg-modalLastName'),
            em:  document.getElementById('fg-modalEmail'),
            en:  document.getElementById('fg-modalEmployeeNum'),
            pw:  document.getElementById('fg-modalPassword'),
            cpw: document.getElementById('fg-modalConfirmPassword'),
        };
        const errs = {
            fn:  document.getElementById('modalFirstNameError'),
            ln:  document.getElementById('modalLastNameError'),
            em:  document.getElementById('modalEmailError'),
            en:  document.getElementById('modalEmployeeNumError'),
            pw:  document.getElementById('modalPasswordError'),
            cpw: document.getElementById('modalConfirmPasswordError'),
        };

        function showErr(errEl, fg, msg) {
            errEl.textContent = msg;
            errEl.classList.add('val-error--show');
            fg.classList.add('val-has-error');
            fg.classList.remove('val-is-valid');
        }
        function clearErr(errEl, fg) {
            errEl.textContent = '';
            errEl.classList.remove('val-error--show');
            fg.classList.remove('val-has-error');
        }
        function markValid(fg) {
            fg.classList.remove('val-has-error');
            fg.classList.add('val-is-valid');
        }

        // Individual validators
        function vFn(strict) {
            const v = fn.value.trim();
            if (!v)              { strict ? showErr(errs.fn, fgs.fn, 'Please fill out this field.') : clearErr(errs.fn, fgs.fn); return false; }
            if (/\d/.test(v))   { showErr(errs.fn, fgs.fn, 'Numbers are not allowed in First Name.'); return false; }
            clearErr(errs.fn, fgs.fn); markValid(fgs.fn); return true;
        }
        function vLn(strict) {
            const v = ln.value.trim();
            if (!v)              { strict ? showErr(errs.ln, fgs.ln, 'Please fill out this field.') : clearErr(errs.ln, fgs.ln); return false; }
            if (/\d/.test(v))   { showErr(errs.ln, fgs.ln, 'Numbers are not allowed in Last Name.'); return false; }
            clearErr(errs.ln, fgs.ln); markValid(fgs.ln); return true;
        }
        function vEm(strict) {
            const v = em.value.trim();
            if (!v)              { strict ? showErr(errs.em, fgs.em, 'Please fill out this field.') : clearErr(errs.em, fgs.em); return false; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { showErr(errs.em, fgs.em, 'Please enter a valid email address.'); return false; }
            clearErr(errs.em, fgs.em); markValid(fgs.em); return true;
        }
        function vEn(strict) {
            const v = en.value.trim();
            if (!v) { strict ? showErr(errs.en, fgs.en, 'Please fill out this field.') : clearErr(errs.en, fgs.en); return false; }
            clearErr(errs.en, fgs.en); markValid(fgs.en); return true;
        }
        function vPw(strict) {
            const v = pw.value;
            if (!v) { strict ? showErr(errs.pw, fgs.pw, 'Please fill out this field.') : clearErr(errs.pw, fgs.pw); return false; }
            clearErr(errs.pw, fgs.pw); markValid(fgs.pw);
            // Re-check confirm live when password itself changes
            if (cpw.value) vCpw(false);
            return true;
        }
        function vCpw(strict) {
            const v = cpw.value;
            if (!v)                 { strict ? showErr(errs.cpw, fgs.cpw, 'Please fill out this field.') : clearErr(errs.cpw, fgs.cpw); return false; }
            if (v !== pw.value)     { showErr(errs.cpw, fgs.cpw, 'Passwords do not match.'); return false; }
            clearErr(errs.cpw, fgs.cpw); markValid(fgs.cpw); return true;
        }

        // Live feedback
        fn .addEventListener('input', () => vFn(false));
        ln .addEventListener('input', () => vLn(false));
        em .addEventListener('input', () => vEm(false));
        en .addEventListener('input', () => vEn(false));
        pw .addEventListener('input', () => vPw(false));
        cpw.addEventListener('input', () => vCpw(false));

        // Eye-toggle for modal password fields
        function wireEye(toggleId, inputEl) {
            const btn = document.getElementById(toggleId);
            if (!btn) return;
            btn.addEventListener('click', function () {
                const show = inputEl.type === 'password';
                inputEl.type = show ? 'text' : 'password';
                const svg = this.querySelector('svg');
                if (svg) {
                    svg.innerHTML = show
                        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
                        : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
                }
            });
        }
        wireEye('toggleModalPassword',        pw);
        wireEye('toggleModalConfirmPassword', cpw);

        // Submit
        document.getElementById('addAdminForm').addEventListener('submit', function (e) {
            const allOk = [vFn(true), vLn(true), vEm(true), vEn(true), vPw(true), vCpw(true)].every(Boolean);
            if (!allOk) {
                e.preventDefault();
                // Scroll to first broken field
                const firstBroken = addAdminModal.querySelector('.val-has-error');
                if (firstBroken) firstBroken.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Show admin-added notification
        <?php if (isset($_GET['admin_added'])): ?>
        (function() {
            const n = document.createElement('div');
            n.style.cssText = 'position:fixed;top:20px;right:20px;background:#27ae60;color:#fff;padding:16px 24px;border-radius:8px;z-index:10000;font-family:Segoe UI,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,0.3);max-width:400px;animation:fadeIn 0.3s ease;';
            n.innerHTML = '<strong>Admin Created Successfully!</strong>';
            document.body.appendChild(n);
            setTimeout(() => { n.style.opacity = '0'; n.style.transition = 'opacity 0.5s'; setTimeout(() => n.remove(), 500); }, 6000);
        })();
        <?php endif; ?>
    })();

    // ── Custom dropdowns ──────────────────────────────────────────────────
    document.querySelectorAll('.custom-dropdown').forEach(dropdown => {
        const btn     = dropdown.querySelector('.custom-dropdown-btn');
        const list    = dropdown.querySelector('.custom-dropdown-list');
        const valueEl = dropdown.querySelector('.custom-dropdown-value');
        const items   = dropdown.querySelectorAll('.custom-dropdown-item');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.custom-dropdown.open').forEach(d => { if (d !== dropdown) d.classList.remove('open'); });
            dropdown.classList.toggle('open');
        });

        items.forEach(item => {
            item.addEventListener('click', () => {
                items.forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                valueEl.textContent = item.dataset.value;
                dropdown.classList.remove('open');
                if (dropdown.id === 'languageDropdown') {
                    document.getElementById('languageInput').value = item.dataset.value;
                }
            });
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-dropdown.open').forEach(d => d.classList.remove('open'));
    });

    // ── Topbar Clock & Weather ────────────────────────────────────────────
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