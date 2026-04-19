<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$txn_id  = (int)($_POST['txn_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');

if (!in_array($status, ['Approved', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

if ($txn_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction.']);
    exit;
}

// Only the seller of this transaction can approve/reject
$check = $conn->prepare("SELECT id FROM transactions WHERE id = ? AND seller_id = ?");
$check->bind_param("ii", $txn_id, $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$check->close();

$upd = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
$upd->bind_param("si", $status, $txn_id);

if ($upd->execute()) {
    $upd->close();
    echo json_encode(['success' => true, 'message' => 'Request ' . strtolower($status) . '.']);
} else {
    $err = $upd->error;
    $upd->close();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $err]);
}
?>