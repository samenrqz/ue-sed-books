<?php
require_once 'connect.php';

header('Content-Type: application/json');

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    echo json_encode(['exists' => $stmt->num_rows > 0]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['error' => 'No email provided']);
}
?>
