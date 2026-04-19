<?php
session_start();
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ── FIX 1: Normalize email — trim whitespace + lowercase so
    //           "Admin@Gmail.com" and "admin@gmail.com" always match.
    $email    = strtolower(trim($_POST["email"]));
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    // ══════════════════════════════════════════════════════════════════
    // STEP 1 — Super-admin check
    //   admin_profile has no password column, so credentials are
    //   verified against the workspace_admins table (super admin is
    //   stored there too) OR kept as the original hardcoded fallback.
    //   We query workspace_admins first; if not found, fall back to
    //   the original hardcoded 'admin' password so existing setups
    //   are never broken.
    // ══════════════════════════════════════════════════════════════════
    if ($email === 'admin@gmail.com') {
        // Try workspace_admins table first (password properly hashed)
        $saStmt = $conn->prepare(
            "SELECT first_name, last_name, password FROM workspace_admins WHERE email = ? LIMIT 1"
        );
        $saStmt->bind_param("s", $email);
        $saStmt->execute();
        $saRow = $saStmt->get_result()->fetch_assoc();
        $saStmt->close();

        $passwordOk = false;
        if ($saRow) {
            // Found in workspace_admins — verify hashed password
            $passwordOk = password_verify($password, $saRow['password']);
        } else {
            // Not in workspace_admins — use original hardcoded fallback
            // and fetch display name from admin_profile
            $passwordOk = ($password === 'admin');
        }

        if ($passwordOk) {
            $adminProfile = $conn->query(
                "SELECT first_name, last_name FROM admin_profile WHERE id = 1 LIMIT 1"
            )->fetch_assoc();
            $_SESSION['user_id']        = 0;
            $_SESSION['first_name']     = $adminProfile ? $adminProfile['first_name'] : 'Admin';
            $_SESSION['last_name']      = $adminProfile ? $adminProfile['last_name']  : '';
            $_SESSION['email']          = $email;
            $_SESSION['is_admin']       = true;
            $_SESSION['is_super_admin'] = true;
            header("Location: admin.php");
            exit;
        }

        // Wrong password for super-admin — do not fall through to other checks
        echo "<script>alert('Incorrect password.'); window.history.back();</script>";
        exit;
    }

    // ══════════════════════════════════════════════════════════════════
    // STEP 2 — Workspace admin check
    //   BUG (root cause of "account not found"):
    //   Original code ONLY accepted emails matching /^admin\d+@gmail\.com$/
    //   (e.g. admin1@gmail.com) AND checked a hardcoded plain-text 'admin'
    //   password — completely ignoring the password_hash() value stored in
    //   the database by admin.php.
    //
    //   Fixed: query workspace_admins by email (any format), then verify
    //   the stored hashed password with password_verify().
    // ══════════════════════════════════════════════════════════════════
    $stmt = $conn->prepare(
        "SELECT id, first_name, last_name, password FROM workspace_admins WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $waResult = $stmt->get_result();

    if ($waResult->num_rows === 1) {
        $waAdmin = $waResult->fetch_assoc();
        $stmt->close();

        if (password_verify($password, $waAdmin['password'])) {
            $_SESSION['user_id']        = $waAdmin['id'];
            $_SESSION['first_name']     = $waAdmin['first_name'];
            $_SESSION['last_name']      = $waAdmin['last_name'];
            $_SESSION['email']          = $email;
            $_SESSION['is_admin']       = true;
            $_SESSION['is_super_admin'] = false;
            header("Location: admin.php");
            exit;
        }

        // Email matched a workspace admin but password was wrong —
        // do not fall through to the users table.
        echo "<script>alert('Incorrect password.'); window.history.back();</script>";
        exit;
    }
    $stmt->close();

    // ══════════════════════════════════════════════════════════════════
    // STEP 3 — Regular user check (unchanged logic, same UX as before)
    // ══════════════════════════════════════════════════════════════════
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['email']      = $email;
            unset($_SESSION['is_admin']);
            unset($_SESSION['is_super_admin']);

            $firstName = htmlspecialchars($user['first_name']);
            $stmt->close();
            $conn->close();
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Login Successful</title>
                <link href="https://fonts.googleapis.com/css2?family=Rammetto+One&display=swap" rel="stylesheet">
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #1a1a2e; }
                    .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 9999; animation: fadeIn 0.3s ease; }
                    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                    @keyframes scaleIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
                    .success-card { background: #fff; border-radius: 20px; padding: 50px 40px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: scaleIn 0.4s ease; }
                    .checkmark { width: 80px; height: 80px; border-radius: 50%; background: #4CAF50; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; }
                    .checkmark svg { width: 40px; height: 40px; stroke: white; stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; }
                    .success-card h2 { font-family: 'Rammetto One', cursive; font-size: 1.5rem; color: #1a1a2e; margin-bottom: 10px; }
                    .success-card p { color: #555; font-size: 0.95rem; margin-bottom: 8px; }
                    .countdown { color: #888; font-size: 0.9rem; margin-bottom: 25px; }
                    .countdown span { font-weight: bold; color: #c0392b; }
                    .btn-home { display: inline-block; padding: 14px 40px; background: #c0392b; color: #fff; text-decoration: none; border-radius: 30px; font-size: 1rem; font-weight: 600; transition: background 0.3s ease, transform 0.2s ease; }
                    .btn-home:hover { background: #a93226; transform: translateY(-2px); }
                </style>
            </head>
            <body>
                <div class="overlay">
                    <div class="success-card">
                        <div class="checkmark">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <h2>Welcome back, <?php echo $firstName; ?>!</h2>
                        <p>Login successful.</p>
                        <p class="countdown">Redirecting to Home page in <span id="timer">5</span> seconds...</p>
                        <a href="home.php" class="btn-home">Go to Home Page</a>
                    </div>
                </div>
                <script>
                    let seconds = 5;
                    const timerEl = document.getElementById('timer');
                    const interval = setInterval(() => {
                        seconds--;
                        timerEl.textContent = seconds;
                        if (seconds <= 0) { clearInterval(interval); window.location.href = 'home.php'; }
                    }, 1000);
                </script>
            </body>
            </html>
            <?php
            exit;
        } else {
            echo "<script>alert('Incorrect password.'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('No account found with that email.'); window.history.back();</script>";
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UEsed Books</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rammetto+One&display=swap" rel="stylesheet">

    <!-- Original stylesheet — untouched -->
    <link rel="stylesheet" href="style.css">

    <!-- Original email-suggestions styles — untouched -->
    <style>
        .email-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            z-index: 999;
            max-height: 180px;
            overflow-y: auto;
            display: none;
        }
        .email-suggestion-item {
            padding: 0.65rem 1rem;
            font-size: 0.88rem;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .email-suggestion-item:hover { background: #fdf2f2; color: #a82c2c; }
        .email-suggestion-item svg { flex-shrink: 0; opacity: 0.45; }
    </style>

    <style>
        html { scroll-behavior: smooth; }

        .req-star {
            color: #c0392b;
            margin-left: 3px;
            font-weight: 700;
            font-size: 0.82em;
            vertical-align: super;
            line-height: 1;
            user-select: none;
        }

        .val-has-error {
            border-color: #c0392b !important;
            background-color: rgba(192, 57, 43, 0.035) !important;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .val-is-valid {
            border-color: #27ae60 !important;
            transition: border-color 0.2s ease;
        }

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
            transition:
                max-height 0.28s ease,
                opacity    0.28s ease,
                margin-top 0.28s ease;
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

        @keyframes vShake {
            0%, 100% { transform: translateX(0); }
            18%  { transform: translateX(-7px); }
            36%  { transform: translateX( 7px); }
            54%  { transform: translateX(-4px); }
            72%  { transform: translateX( 4px); }
        }
        .val-shake {
            animation: vShake 0.38s ease;
        }

        .btn-create-account:disabled {
            opacity: 0.68;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <!-- HEADER — original markup, zero changes -->
    <header class="header">
        <div class="header-container">
            <a href="index.html" class="logo">
                <img class="logo-icon" src="images/5.png" alt="UEsed Books Logo">
                <span>UEsed Books</span>
            </a>
            <button class="hamburger" id="hamburger">
                <span></span><span></span><span></span>
            </button>
            <nav class="nav" id="nav">
                <a href="login.php"><b>Home</b></a>
                <a href="login.php"><b>Listing</b></a>
                <a href="login.php"><b>About</b></a>
            </nav>
            <div class="header-actions">
                <a href="login.php" class="login">🔐 Login</a>
                <a href="register.html" class="btn-register" style="text-decoration:none;display:inline-block;">Register</a>
            </div>
        </div>
    </header>

    <section class="register-section">
        <div class="dots register-dots-top-left"></div>
        <div class="dots register-dots-right"></div>
        <div class="dots register-dots-bottom-left"></div>
        <div class="dots register-dots-bottom-right"></div>

        <div class="register-card">
            <h2 class="register-title">Login</h2>
            <p class="register-subtitle">Login to access your account</p>

            <form class="register-form" id="loginForm"
                  action="login.php" method="POST"
                  autocomplete="off"
                  novalidate>

                <!-- Email -->
                <fieldset class="form-group form-group-full" id="fg-loginEmail">
                    <legend>Email<span class="req-star" aria-hidden="true">*</span></legend>
                    <input type="email" name="email" id="loginEmailInput"
                           placeholder="johndoe@email.com" autocomplete="off">
                    <span class="val-error" id="loginEmailError"
                          role="alert" aria-live="polite"></span>
                </fieldset>

                <!-- Password -->
                <fieldset class="form-group form-group-full" id="fg-loginPassword">
                    <legend>Password<span class="req-star" aria-hidden="true">*</span></legend>
                    <div class="password-wrapper">
                        <input type="password" style="display:none;" aria-hidden="true" autocomplete="off">
                        <input
                            type="password"
                            id="passwordInput"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="new-password"
                            readonly
                            onfocus="this.removeAttribute('readonly')">
                        <button type="button" class="toggle-password" id="togglePassword">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <span class="val-error" id="loginPasswordError"
                          role="alert" aria-live="polite"></span>
                </fieldset>

                <button type="submit" class="btn-create-account" id="loginSubmitBtn">Login</button>
                <p class="login-link">Don't have an account? <a href="register.html" class="link-red">Register</a></p>

            </form>
        </div>
    </section>

    <!-- Original script.js (hamburger, eye-toggle) — untouched -->
    <script src="script.js"></script>

    <!-- Original email-autocomplete script — untouched -->
    <script>
        const emailField = document.querySelector('input[name="email"]');
        const form = document.getElementById('loginForm');
        const emailFieldset = emailField.closest('fieldset');
        emailFieldset.style.position = 'relative';

        const dropdown = document.createElement('div');
        dropdown.className = 'email-suggestions';
        emailFieldset.appendChild(dropdown);

        function getSavedEmails() {
            return JSON.parse(localStorage.getItem('savedEmails') || '[]');
        }

        function saveEmail(email) {
            if (!email) return;
            let emails = getSavedEmails();
            emails = [email, ...emails.filter(e => e !== email)].slice(0, 10);
            localStorage.setItem('savedEmails', JSON.stringify(emails));
        }

        function renderDropdown(query) {
            const emails = getSavedEmails();
            const filtered = query
                ? emails.filter(e => e.toLowerCase().includes(query.toLowerCase()))
                : emails;

            if (!filtered.length) { dropdown.style.display = 'none'; return; }

            dropdown.innerHTML = filtered.map(email => `
                <div class="email-suggestion-item" data-email="${email}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    ${email}
                </div>
            `).join('');

            dropdown.style.display = 'block';
            dropdown.querySelectorAll('.email-suggestion-item').forEach(item => {
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    emailField.value = this.dataset.email;
                    dropdown.style.display = 'none';
                    vLoginEmail(false);
                });
            });
        }

        window.addEventListener('load', function() {
            form.reset();
        });

        emailField.addEventListener('focus', function() { renderDropdown(this.value); });
        emailField.addEventListener('input', function() { renderDropdown(this.value); });
        emailField.addEventListener('blur', function() {
            setTimeout(() => { dropdown.style.display = 'none'; }, 150);
        });

        form.addEventListener('submit', function() {
            saveEmail(emailField.value.trim());
        });

        document.addEventListener('click', function(e) {
            if (!emailFieldset.contains(e.target)) dropdown.style.display = 'none';
        });
    </script>

    <!-- Login validation — self-contained IIFE, identical to original -->
    <script>
    (function () {
        'use strict';

        const form        = document.getElementById('loginForm');
        const submitBtn   = document.getElementById('loginSubmitBtn');
        const emailInput  = document.getElementById('loginEmailInput');
        const pwInput     = document.getElementById('passwordInput');

        const FIELDS = [
            { fgId: 'fg-loginEmail',    errId: 'loginEmailError'    },
            { fgId: 'fg-loginPassword', errId: 'loginPasswordError' },
        ];

        let isSubmitting = false;

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

        function triggerShake(fg) {
            fg.classList.remove('val-shake');
            void fg.offsetWidth;
            fg.classList.add('val-shake');
            fg.addEventListener('animationend',
                () => fg.classList.remove('val-shake'), { once: true });
        }

        function vLoginEmail(strict) {
            const fg  = document.getElementById('fg-loginEmail');
            const err = document.getElementById('loginEmailError');
            const val = emailInput.value.trim();
            if (!val) {
                strict ? showErr(err, fg, 'Please fill out this field.') : clearErr(err, fg);
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                showErr(err, fg, 'Please enter a valid email address.');
                return false;
            }
            clearErr(err, fg); markValid(fg); return true;
        }

        function vLoginPassword(strict) {
            const fg  = document.getElementById('fg-loginPassword');
            const err = document.getElementById('loginPasswordError');
            const val = pwInput.value;
            if (!val) {
                strict ? showErr(err, fg, 'Please fill out this field.') : clearErr(err, fg);
                return false;
            }
            clearErr(err, fg); markValid(fg); return true;
        }

        emailInput.addEventListener('input', () => vLoginEmail(false));
        pwInput   .addEventListener('input', () => vLoginPassword(false));

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting) return;

            const allOk = [
                vLoginEmail(true),
                vLoginPassword(true),
            ].every(Boolean);

            if (!allOk) {
                let firstBroken = null;
                FIELDS.forEach(({ fgId }) => {
                    const fg = document.getElementById(fgId);
                    if (fg.classList.contains('val-has-error')) {
                        triggerShake(fg);
                        if (!firstBroken) firstBroken = fg;
                    }
                });
                if (firstBroken) firstBroken.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            isSubmitting = true;
            submitBtn.disabled    = true;
            submitBtn.textContent = 'Logging in\u2026';
            form.submit();
        }, true);

    })();
    </script>

</body>
</html>