<?php
require_once 'connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $email = trim($_POST["email"]);
    $student_number = trim($_POST["student_number"]);
    $password = $_POST["password"];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($student_number) || empty($password)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format.'); window.history.back();</script>";
        exit;
    }

    if (preg_match('/\d/', $first_name)) {
        echo "<script>alert('Numbers are not allowed in First Name.'); window.history.back();</script>";
        exit;
    }

    if (preg_match('/\d/', $last_name)) {
        echo "<script>alert('Numbers are not allowed in Last Name.'); window.history.back();</script>";
        exit;
    }

    if (preg_match('/[a-zA-Z]/', $student_number)) {
        echo "<script>alert('Letters are not allowed in Student Number.'); window.history.back();</script>";
        exit;
    }

    // Check if email or student number already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR student_number = ?");
    $stmt->bind_param("ss", $email, $student_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('Email or Student Number already registered.'); window.history.back();</script>";
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, student_number, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $first_name, $last_name, $email, $student_number, $hashed_password);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registration Successful</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Rammetto+One&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }

                body {
                    font-family: 'Segoe UI', sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #1a1a2e;
                }

                .overlay {
                    position: fixed;
                    top: 0; left: 0;
                    width: 100%; height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    backdrop-filter: blur(8px);
                    -webkit-backdrop-filter: blur(8px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes scaleIn {
                    from { transform: scale(0.8); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }

                .success-card {
                    background: #ffffff;
                    border-radius: 20px;
                    padding: 50px 40px;
                    text-align: center;
                    max-width: 420px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    animation: scaleIn 0.4s ease;
                }

                .checkmark {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: #4CAF50;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 25px;
                }

                .checkmark svg {
                    width: 40px;
                    height: 40px;
                    stroke: white;
                    stroke-width: 3;
                    fill: none;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }

                .success-card h2 {
                    font-family: 'Rammetto One', cursive;
                    font-size: 1.5rem;
                    color: #1a1a2e;
                    margin-bottom: 10px;
                }

                .success-card p {
                    color: #555;
                    font-size: 0.95rem;
                    margin-bottom: 8px;
                }

                .countdown {
                    color: #888;
                    font-size: 0.9rem;
                    margin-bottom: 25px;
                }

                .countdown span {
                    font-weight: bold;
                    color: #c0392b;
                }

                .btn-home {
                    display: inline-block;
                    padding: 14px 40px;
                    background: #c0392b;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 30px;
                    font-size: 1rem;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: background 0.3s ease, transform 0.2s ease;
                }

                .btn-home:hover {
                    background: #a93226;
                    transform: translateY(-2px);
                }
            </style>
        </head>
        <body>
            <div class="overlay">
                <div class="success-card">
                    <div class="checkmark">
                        <svg viewBox="0 0 24 24">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h2>Registered Successfully!</h2>
                    <p>Your account has been created.</p>
                    <p class="countdown">Redirecting to Login page in <span id="timer">5</span> seconds...</p>
                    <a href="login.php" class="btn-home">Go to Login Page</a>
                </div>
            </div>

            <script>
                let seconds = 5;
                const timerEl = document.getElementById('timer');
                const interval = setInterval(() => {
                    seconds--;
                    timerEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            </script>
        </body>
        </html>
        <?php
    } else {
        echo "<script>alert('Registration failed. Please try again.'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: register.html");
    exit;
}
?>
