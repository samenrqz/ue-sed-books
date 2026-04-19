<?php
session_start();
require_once 'connect.php';

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to purchase.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$buyer_id     = (int)$_SESSION['user_id'];
$buyer_name   = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$buyer_email  = $_SESSION['email'] ?? '';
$book_id      = (int)($_POST['book_id'] ?? 0);
$meetup_place = trim($_POST['meetup_place'] ?? '');
$message      = trim($_POST['message'] ?? '');

if ($book_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid book.']);
    exit;
}

// Fetch book
$stmt = $conn->prepare("SELECT id, title, price, stock, seller_id, seller FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    echo json_encode(['success' => false, 'message' => 'Book not found.']);
    exit;
}

if ((int)$book['seller_id'] === $buyer_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot buy your own listing.']);
    exit;
}

if ((int)$book['stock'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'This book is out of stock.']);
    exit;
}

// No duplicate pending
$dup = $conn->prepare("SELECT id FROM transactions WHERE book_id = ? AND buyer_id = ? AND status = 'Pending'");
$dup->bind_param("ii", $book_id, $buyer_id);
$dup->execute();
$dup->store_result();
$isDup = $dup->num_rows > 0;
$dup->close();

if ($isDup) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending request for this book.']);
    exit;
}

$seller_id    = (int)$book['seller_id'];
$price        = (float)$book['price'];
$title        = $book['title'];
$seller_email = $book['seller'];

// ── FIX: read the clean YYYY-MM-DD value sent as 'transaction_date' ──
// listing.php sends: fd.append('transaction_date', dateVal) where dateVal = buyModalDateRaw.value (YYYY-MM-DD)
$raw_date = trim($_POST['transaction_date'] ?? '');
if (!empty($raw_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
    $meetup_date = $raw_date; // already clean YYYY-MM-DD
} else {
    // fallback: try parsing whatever was sent
    $parsed = strtotime($raw_date);
    $meetup_date = $parsed ? date('Y-m-d', $parsed) : date('Y-m-d');
}

// Check which columns exist
$colCheck  = $conn->query("SHOW COLUMNS FROM transactions LIKE 'message'");
$hasMessage = $colCheck && $colCheck->num_rows > 0;

$colCheck2 = $conn->query("SHOW COLUMNS FROM transactions LIKE 'book_id'");
$hasBookId  = $colCheck2 && $colCheck2->num_rows > 0;

$colCheck3 = $conn->query("SHOW COLUMNS FROM transactions LIKE 'buyer_id'");
$hasBuyerId = $colCheck3 && $colCheck3->num_rows > 0;

if ($hasBookId && $hasBuyerId && $hasMessage) {
    $ins = $conn->prepare(
        "INSERT INTO transactions
         (book_id, book, buyer_id, buyer, buyer_email, seller_id, seller_email, amount, meetup_place, message, transaction_date, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())"
    );
    $ins->bind_param(
        "isissisd" . "sss",
        $book_id, $title, $buyer_id, $buyer_name,
        $buyer_email, $seller_id, $seller_email, $price,
        $meetup_place, $message, $meetup_date
    );
} else {
    $ins = $conn->prepare(
        "INSERT INTO transactions
         (book, buyer, seller_email, amount, meetup_place, transaction_date, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())"
    );
    $ins->bind_param(
        "sssdss",
        $title, $buyer_name, $seller_email, $price, $meetup_place, $meetup_date
    );
}

if ($ins->execute()) {
    $ins->close();

    // Decrease stock by 1
    $upd = $conn->prepare("UPDATE books SET stock = stock - 1 WHERE id = ? AND stock > 0");
    $upd->bind_param("i", $book_id);
    $upd->execute();
    $upd->close();

    // Check remaining stock
    $chk = $conn->prepare("SELECT stock FROM books WHERE id = ?");
    $chk->bind_param("i", $book_id);
    $chk->execute();
    $remaining = $chk->get_result()->fetch_assoc()['stock'];
    $chk->close();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase request sent! The seller will be notified.',
        'stock'   => (int)$remaining
    ]);
} else {
    $err = $ins->error;
    $ins->close();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $err]);
}
?>