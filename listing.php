<?php
session_start();
require_once 'connect.php';

function isBookOwner($conn, $bookId) {
    if (!isset($_SESSION['user_id'])) return false;
    $stmt = $conn->prepare("SELECT id FROM books WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $bookId, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();
    $owned = $stmt->num_rows > 0;
    $stmt->close();
    return $owned;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_book') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    $seller      = $_SESSION['username'] ?? $_SESSION['email'] ?? '';
    $seller_id   = $_SESSION['user_id'] ?? 0;
    $image       = '';
    if (!empty($_FILES['image']['name'])) {
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('book_') . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/images/' . $filename);
        $image = $filename;
    }
    $stmt = $conn->prepare("INSERT INTO books (title, description, seller, seller_id, stock, price, image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssiids", $title, $description, $seller, $seller_id, $stock, $price, $image);
    $stmt->execute();
    $stmt->close();
    header("Location: listing.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_book') {
    $id = intval($_POST['book_id']);
    if (isBookOwner($conn, $id)) {
        $imgStmt = $conn->prepare("SELECT image FROM books WHERE id = ?");
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        if ($imgRow = $imgResult->fetch_assoc()) {
            if (!empty($imgRow['image']) && file_exists(__DIR__ . '/images/' . $imgRow['image'])) {
                unlink(__DIR__ . '/images/' . $imgRow['image']);
            }
        }
        $imgStmt->close();
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: listing.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_book') {
    $id          = intval($_POST['book_id']);
    $title       = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $price       = floatval($_POST['price']);
    $stock       = intval($_POST['stock']);
    if (!empty($_FILES['image']['name'])) {
        $ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('book_') . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/images/' . $filename);
        $stmt = $conn->prepare("UPDATE books SET title=?, description=?, stock=?, price=?, image=? WHERE id=?");
        $stmt->bind_param("ssidsi", $title, $description, $stock, $price, $filename, $id);
    } else {
        $stmt = $conn->prepare("UPDATE books SET title=?, description=?, stock=?, price=? WHERE id=?");
        $stmt->bind_param("ssidi", $title, $description, $stock, $price, $id);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: listing.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Rammetto+One&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --red: #a82c2c; --red-dark: #8b2e2e; --red-light: #fdf2f2;
    --black: #263238; --gray: #555; --light: #f7f8fa;
    --white: #ffffff; --border: #e0e0e0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--white); color: var(--black); min-height: 100vh; display: flex; flex-direction: column; }

  .home-header { background: #fff; padding: 0.8rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
  .home-header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
  .home-logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
  .home-logo span { font-family: 'Rammetto One', cursive; font-size: 1.3rem; color: var(--red-dark); }
  .home-nav { display: flex; align-items: center; gap: 2.2rem; }
  .home-nav a { text-decoration: none; color: #555; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
  .home-nav a:hover { color: #333; font-weight: 700; }
  .home-nav a.active { color: #333; font-weight: 600; }
  .account-actions-group { display: flex; align-items: center; gap: 8px; }
  .nav-bag-icon { cursor: pointer; transition: filter 0.25s ease; filter: brightness(0); display: inline-block; vertical-align: middle; margin-right: 0.5rem; }
  .nav-bag-icon:hover { filter: brightness(0) saturate(100%) invert(17%) sepia(86%) saturate(7496%) hue-rotate(353deg) brightness(90%) contrast(120%); }
  .account-dropdown { position: relative; display: inline-block; }
  .home-account-btn { display: inline-flex; align-items: center; gap: 0.4rem; background: var(--red); color: #fff; text-decoration: none; padding: 0.6rem 1.4rem; border-radius: 25px; font-size: 0.9rem; font-weight: 600; transition: background 0.2s, transform 0.2s, box-shadow 0.2s, border-radius 0.2s; position: relative; z-index: 2; border: none; cursor: pointer; font-family: 'Inter', sans-serif; }
  .home-account-btn svg { transition: transform 0.25s ease; }
  .account-dropdown:hover .home-account-btn { background: var(--red-dark); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(168,44,44,0.35); border-radius: 25px 25px 0 0; }
  .account-dropdown:hover .home-account-btn svg { transform: rotate(180deg); }
  .account-dropdown-menu { position: absolute; top: calc(100% - 4px); right: 0; min-width: 100%; background: var(--red-dark); border-radius: 0 0 16px 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(168,44,44,0.28); opacity: 0; pointer-events: none; transform: translateY(-6px); transition: opacity 0.2s ease, transform 0.2s ease; z-index: 1; }
  .account-dropdown:hover .account-dropdown-menu { opacity: 1; pointer-events: auto; transform: translateY(0); }
  .account-dropdown-menu a { display: flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.4rem; color: #fff; text-decoration: none; font-size: 0.88rem; font-weight: 600; white-space: nowrap; transition: background 0.15s, padding-left 0.15s; }
  .account-dropdown-menu a:hover { background: rgba(255,255,255,0.15); padding-left: 1.7rem; }
  .account-dropdown-menu a svg { flex-shrink: 0; opacity: 0.85; }

  .hero { text-align: center; padding: 2.5rem 1rem 1rem; }
  .hero h1 { font-size: 2rem; line-height: 1.2; color: var(--black); font-weight: 700; }
  .hero h1 .red { color: var(--red); }
  .hero p { margin-top: 0.5rem; font-size: 0.9rem; color: #888; }

  .search-bar-wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; max-width: 900px; margin: 1.25rem auto 0; padding: 0 2rem; width: 100%; }
  .search-input-wrap { flex: 1; position: relative; }
  .search-input-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999; pointer-events: none; }
  .search-input { width: 100%; padding: 0.65rem 1rem 0.65rem 2.6rem; border: 1.5px solid var(--border); border-radius: 30px; font-size: 0.9rem; font-family: 'Inter', sans-serif; color: var(--black); background: var(--white); transition: border-color 0.2s, box-shadow 0.2s; }
  .search-input:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(168,44,44,0.08); }
  .search-input::placeholder { color: #bbb; }
  .sort-wrap { display: flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; color: #555; font-weight: 500; flex-shrink: 0; }
  .sort-pill { background: var(--red); color: white; border: none; border-radius: 20px; padding: 0.32rem 0.9rem; font-size: 0.78rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: background 0.2s; }
  .sort-pill:hover { background: var(--red-dark); }
  .sort-dropdown { position: relative; }
  .sort-dropdown-menu { display: none; position: absolute; top: calc(100% + 6px); right: 0; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 100; min-width: 130px; overflow: hidden; }
  .sort-dropdown-menu.open { display: block; }
  .sort-option { display: block; width: 100%; padding: 8px 14px; background: none; border: none; text-align: left; cursor: pointer; font-size: 13px; color: #333; }
  .sort-option:hover { background: #f5f5f5; }
  .sort-option.active { font-weight: 600; color: #8B1A1A; }

  .sell-banner { max-width: 900px; margin: 1.25rem auto 0; width: calc(100% - 4rem); background: #fff5f5; border: 1.5px dashed #e8b4b4; border-radius: 12px; padding: 0.85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
  .sell-banner-text { font-size: 0.87rem; font-weight: 700; color: var(--red-dark); }
  .sell-banner-text span { display: block; font-size: 0.78rem; font-weight: 400; color: #888; margin-top: 2px; }
  .sell-btn { background: var(--red); color: white; border: none; border-radius: 8px; padding: 0.5rem 1.1rem; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 5px; transition: background 0.2s, transform 0.15s; }
  .sell-btn:hover { background: var(--red-dark); transform: translateY(-1px); }

  .hide-mine-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; background: var(--white); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; color: var(--gray); cursor: pointer; white-space: nowrap; transition: all 0.2s; }
  .hide-mine-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }
  .hide-mine-btn.active { background: var(--red); color: #fff; border-color: var(--red); }
  .hide-mine-btn.active svg { stroke: #fff; }

  .books-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; max-width: 900px; margin: 1.25rem auto 0; padding: 0 2rem 2.5rem; width: 100%; }
  .book-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow 0.25s, transform 0.2s; animation: cardIn 0.4s ease both; }
  .book-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.12); transform: translateY(-3px); }
  @keyframes cardIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes cardFadeOut { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.92); } }
  .book-card.removing { animation: cardFadeOut 0.3s ease forwards; pointer-events: none; }
  .book-card:nth-child(1){animation-delay:.05s} .book-card:nth-child(2){animation-delay:.10s}
  .book-card:nth-child(3){animation-delay:.15s} .book-card:nth-child(4){animation-delay:.20s}
  .book-card:nth-child(5){animation-delay:.25s} .book-card:nth-child(6){animation-delay:.30s}
  .book-card:nth-child(7){animation-delay:.35s}
  .book-cover-placeholder { width: 100%; height: 155px; background: linear-gradient(135deg, #dde1ea, #c8cdd8); display: flex; align-items: center; justify-content: center; overflow: hidden; }
  .book-cover-placeholder img { width: 100%; height: 100%; object-fit: cover; }
  .book-cover-placeholder svg { color: #a0a8b8; }
  .book-info { padding: 0.75rem 0.85rem 0; flex: 1; }
  .book-title { font-size: 0.88rem; font-weight: 700; color: var(--black); line-height: 1.3; }
  .book-desc { font-size: 0.73rem; color: #aaa; margin-top: 0.4rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .book-price { font-size: 0.82rem; font-weight: 700; color: var(--red); margin-top: 0.35rem; }
  .book-status-badge { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 2px 9px; border-radius: 20px; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 4px; }
  .badge-available { background: #e8f8e8; color: #27ae60; }
  .badge-pending { background: #fff8e1; color: #e67e22; }
  .badge-sold { background: #fdf2f2; color: var(--red); }

  .buy-btn { width: calc(100% - 1.7rem); margin: 0.65rem 0.85rem 0.85rem; padding: 0.55rem; background: var(--red); color: white; border: none; border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: background 0.2s; letter-spacing: 0.01em; display: flex; align-items: center; justify-content: center; gap: 6px; }
  .buy-btn:hover { background: var(--red-dark); }
  .update-btn { width: calc(100% - 1.7rem); margin: 0.65rem 0.85rem 0.85rem; padding: 0.55rem; background: linear-gradient(135deg, #555, #333); color: white; border: none; border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: background 0.2s, transform 0.15s, box-shadow 0.2s; letter-spacing: 0.01em; display: flex; align-items: center; justify-content: center; gap: 6px; }
  .update-btn:hover { background: linear-gradient(135deg, #444, #222); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

  .file-upload-wrap { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
  .file-upload-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; background: var(--white); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; color: var(--black); cursor: pointer; transition: border-color 0.2s, background 0.2s; white-space: nowrap; }
  .file-upload-btn:hover { border-color: var(--red); background: var(--red-light); color: var(--red); }
  .file-name-label { font-size: 0.78rem; color: #aaa; font-style: italic; }

  .fab { position: fixed; bottom: 2rem; right: 2rem; width: 48px; height: 48px; background: var(--red); border: none; border-radius: 50%; color: white; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 18px rgba(168,44,44,0.4); transition: transform 0.2s, box-shadow 0.2s, bottom 0.2s; z-index: 999; }
  .fab:hover { transform: scale(1.1); box-shadow: 0 6px 24px rgba(168,44,44,0.5); }

  .home-footer { background: var(--red); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
  .home-footer-left { font-family: 'Rammetto One', cursive; color: #fff; font-size: 0.85rem; }
  .home-footer-right { display: flex; gap: 1.8rem; }
  .home-footer-right a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: opacity 0.2s; }
  .home-footer-right a:hover { opacity: 0.8; }

  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 300; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.25s, visibility 0.25s; }
  .modal-overlay.open { opacity: 1; visibility: visible; }
  .modal { background: var(--white); border-radius: 16px; padding: 1.75rem; width: 90%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.18); transform: translateY(20px) scale(0.97); transition: transform 0.25s cubic-bezier(0.4,0,0.2,1), opacity 0.25s; opacity: 0; }
  .modal-overlay.open .modal { transform: translateY(0) scale(1); opacity: 1; }
  .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
  .modal-title { font-size: 1rem; font-weight: 700; color: var(--black); }
  .modal-close { background: none; border: none; cursor: pointer; color: #888; font-size: 1.1rem; display: flex; align-items: center; padding: 2px; }
  .modal-close:hover { color: var(--black); }
  .form-group { margin-bottom: 1rem; }
  .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 5px; }
  .form-input, .form-textarea { width: 100%; padding: 0.6rem 0.85rem; border: 1.5px solid var(--border); border-radius: 9px; font-size: 0.85rem; font-family: 'Inter', sans-serif; color: var(--black); background: var(--white); transition: border-color 0.2s, box-shadow 0.2s; }
  .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(168,44,44,0.08); }
  .form-textarea { resize: vertical; min-height: 70px; }
  .form-row { display: flex; gap: 0.75rem; }
  .form-row .form-group { flex: 1; }
  .modal-submit { width: 100%; padding: 0.65rem; background: var(--red); color: white; border: none; border-radius: 9px; font-size: 0.88rem; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; margin-top: 0.25rem; transition: background 0.2s; }
  .modal-submit:hover { background: var(--red-dark); }
  .modal-actions { display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.25rem; }
  .modal-save-btn { width: 100%; padding: 0.65rem; background: var(--red); color: white; border: none; border-radius: 9px; font-size: 0.88rem; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; display: flex; align-items: center; justify-content: center; gap: 6px; }
  .modal-save-btn:hover { background: var(--red-dark); transform: translateY(-1px); }
  .modal-delete-btn { width: 100%; padding: 0.6rem; background: transparent; color: #c0392b; border: 1.5px solid #e8b4b4; border-radius: 9px; font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.15s; }
  .modal-delete-btn:hover { background: #fff0f0; border-color: #c0392b; color: #922b21; transform: translateY(-1px); }

  /* ── BUY MODAL ── */
  .buy-modal { background: var(--white); border-radius: 18px; width: 90%; max-width: 460px; box-shadow: 0 24px 64px rgba(0,0,0,0.22); transform: translateY(20px) scale(0.97); transition: transform 0.28s cubic-bezier(0.4,0,0.2,1), opacity 0.28s; opacity: 0; overflow: hidden; }
  .modal-overlay.open .buy-modal { transform: translateY(0) scale(1); opacity: 1; }
  .buy-modal-header { background: linear-gradient(135deg, var(--red) 0%, var(--red-dark) 100%); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: center; position: relative; }
  .buy-modal-header h2 { font-family: 'Rammetto One', cursive; font-size: 1.25rem; color: #fff; text-align: center; }
  .buy-modal-close { position: absolute; right: 1rem; background: rgba(255,255,255,0.2); border: none; border-radius: 50%; width: 28px; height: 28px; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; font-size: 0.9rem; }
  .buy-modal-close:hover { background: rgba(255,255,255,0.35); }
  .buy-modal-body { padding: 1.5rem 1.5rem 0.5rem; }
  .buy-modal-item-label { font-size: 0.72rem; color: #aaa; text-align: center; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
  .buy-modal-title { font-size: 1.3rem; font-weight: 700; color: var(--black); text-align: center; margin-bottom: 4px; }
  .buy-modal-price { font-size: 1.35rem; font-weight: 700; color: var(--red); text-align: center; margin-bottom: 1.25rem; }
  .buy-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem; }
  .buy-modal-field { display: flex; flex-direction: column; gap: 4px; }
  .buy-modal-field.full { grid-column: 1 / -1; }
  .buy-modal-label { font-size: 0.72rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.05em; }
  .buy-modal-label .req { color: var(--red); margin-left: 2px; font-size: 0.7rem; vertical-align: super; }
  .buy-modal-input, .buy-modal-textarea { width: 100%; padding: 0.55rem 0.8rem; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.85rem; font-family: 'Inter', sans-serif; color: var(--black); background: var(--white); transition: border-color 0.2s, box-shadow 0.2s; }
  .buy-modal-input:focus, .buy-modal-textarea:focus { outline: none; border-color: var(--red); box-shadow: 0 0 0 3px rgba(168,44,44,0.08); }
  .buy-modal-textarea { resize: vertical; min-height: 55px; }
  .buy-modal-input.field-error { border-color: #c0392b !important; background: rgba(192,57,43,0.04) !important; animation: fieldShake 0.35s ease; }
  @keyframes fieldShake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }
  .buy-modal-footer { padding: 0.75rem 1.5rem 1.5rem; display: flex; gap: 0.75rem; }
  .buy-modal-confirm { flex: 1; padding: 0.7rem; background: var(--red); color: #fff; border: none; border-radius: 9px; font-size: 0.9rem; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; display: flex; align-items: center; justify-content: center; gap: 8px; }
  .buy-modal-confirm:hover { background: var(--red-dark); transform: translateY(-1px); }
  .buy-modal-confirm:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
  .buy-modal-cancel { padding: 0.7rem 1.25rem; background: var(--light); color: var(--black); border: 1.5px solid var(--border); border-radius: 9px; font-size: 0.9rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: background 0.2s; }
  .buy-modal-cancel:hover { background: #e8e8e8; }

  .custom-date-wrap { position: relative; }
  .custom-date-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--red); pointer-events: none; width: 14px; height: 14px; }
  #buyModalDate { cursor: pointer; padding-right: 2rem; }
  .custom-calendar { display: none; position: absolute; top: calc(100% + 4px); left: 0; width: 240px; background: #fff; border-radius: 12px; box-shadow: 0 10px 32px rgba(0,0,0,0.16); z-index: 9999; overflow: hidden; }
  .custom-calendar.open { display: block; animation: calPop 0.18s cubic-bezier(0.4,0,0.2,1); }
  @keyframes calPop { from { opacity:0; transform:translateY(-6px) scale(0.97); } to { opacity:1; transform:translateY(0) scale(1); } }
  .cal-header { background: linear-gradient(135deg, var(--red), var(--red-dark)); display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.75rem; }
  .cal-header-label { display: flex; align-items: center; gap: 4px; }
  .cal-month-label { font-family: 'Rammetto One', cursive; font-size: 0.78rem; color: #fff; }
  .cal-year-btn { background: rgba(255,255,255,0.15); border: none; color: #fff; font-family: 'Rammetto One', cursive; font-size: 0.9rem; cursor: pointer; border-radius: 6px; padding: 2px 6px; display: flex; align-items: center; gap: 3px; transition: background 0.2s; }
  .cal-year-btn:hover { background: rgba(255,255,255,0.3); }
  .cal-year-btn svg { width: 9px; height: 9px; transition: transform 0.2s; }
  .cal-nav { background: rgba(255,255,255,0.2); border: none; color: #fff; width: 22px; height: 22px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; font-size: 0.85rem; line-height: 1; flex-shrink: 0; }
  .cal-nav:hover { background: rgba(255,255,255,0.35); }
  .cal-grid { padding: 0.4rem 0.5rem 0.5rem; }
  .cal-days-header { display: grid; grid-template-columns: repeat(7,1fr); text-align: center; margin-bottom: 2px; }
  .cal-days-header span { font-size: 0.58rem; font-weight: 700; color: #bbb; padding: 3px 0; text-transform: uppercase; }
  .cal-days { display: grid; grid-template-columns: repeat(7,1fr); gap: 1px; }
  .cal-day { width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 0.73rem; font-weight: 500; border-radius: 50%; cursor: pointer; border: none; background: none; color: var(--black); transition: background 0.15s, color 0.15s; font-family: 'Inter', sans-serif; padding: 0; }
  .cal-day:hover:not([disabled]):not(.selected) { background: var(--red-light); color: var(--red); }
  .cal-day.today { font-weight: 700; color: var(--red); border: 1.5px solid var(--red); }
  .cal-day.selected { background: var(--red); color: #fff; font-weight: 700; }
  .cal-day[disabled] { color: #ddd; cursor: not-allowed; }
  .cal-day.other-month { color: transparent; cursor: default; pointer-events: none; }
  .cal-year-list { display: grid; grid-template-columns: repeat(3,1fr); gap: 4px; padding: 0.75rem; animation: calPop 0.15s ease; }
  .cal-year-item { padding: 0.45rem 0; border: none; border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 500; color: var(--black); background: none; cursor: pointer; transition: background 0.15s, color 0.15s; text-align: center; }
  .cal-year-item:hover { background: var(--red-light); color: var(--red); }
  .cal-year-item.active { background: var(--red); color: #fff; font-weight: 700; }

  /* ── TOAST ── */
  .toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(80px); background: #263238; color: #fff; padding: 0.75rem 1.5rem; border-radius: 30px; font-size: 0.88rem; font-weight: 600; font-family: 'Inter', sans-serif; z-index: 9999; transition: transform 0.35s cubic-bezier(0.4,0,0.2,1), opacity 0.35s; opacity: 0; display: flex; align-items: center; gap: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.25); white-space: nowrap; }
  .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
  .toast.success { background: #27ae60; }
  .toast.error { background: #c0392b; }

  .empty-state { text-align: center; padding: 3rem 1rem; color: #aaa; grid-column: 1 / -1; }
  .empty-state p { font-size: 0.9rem; font-weight: 500; margin-top: 0.75rem; }

  .buy-modal-input[readonly] { background: #f7f8fa; color: #555; cursor: default; border-color: #e8e8e8; }
</style>
</head>
<body>

<header class="home-header">
  <div class="home-header-inner">
    <a href="home.php" class="home-logo">
      <img src="images/5.png" alt="UEsed Books Logo" width="32" height="32">
      <span>UEsed Books</span>
    </a>
    <nav class="home-nav">
      <a href="home.php">Home</a>
      <a href="listing.php" class="active">Listing</a>
      <a href="about.php">About</a>
    </nav>
    <div class="account-actions-group">
      <a href="history.php" title="Transactions" class="nav-bag-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M3 9h18"/></svg>
      </a>
      <div class="account-dropdown">
        <a href="account.php" class="home-account-btn">
          My Account
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </a>
        <div class="account-dropdown-menu">
          <a href="logout.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="hero">
  <h1><span class="red">UEsed Books:</span> The Ultimate Student Book Swap</h1>
  <p>Check all the books available</p>
</div>

<div class="search-bar-wrap">
  <div class="search-input-wrap">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input class="search-input" id="searchInput" type="text" placeholder="Search Book">
  </div>
  <div class="sort-wrap">
    <span>Sort:</span>
    <div class="sort-dropdown">
      <button class="sort-pill" id="sortBtn">Newest <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></button>
      <div class="sort-dropdown-menu" id="sortMenu">
        <button class="sort-option active" data-sort="newest">Newest</button>
        <button class="sort-option" data-sort="oldest">Oldest</button>
        <button class="sort-option" data-sort="alphabetical">Alphabetical</button>
      </div>
    </div>
  </div>
</div>

<div class="sell-banner">
  <div class="sell-banner-text">
    📚 Have a book to sell?
    <span>List your used books and help fellow students save money.</span>
  </div>
  <div style="display:flex;align-items:center;gap:0.65rem;">
    <button class="hide-mine-btn" id="hideMyListings">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      Hide My Listings
    </button>
    <button class="sell-btn" id="openSellModal">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Sell a Book
    </button>
  </div>
</div>

<div class="books-grid" id="booksGrid"></div>
<button class="fab" id="fabSell" title="Sell a Book">+</button>

<footer class="home-footer">
  <div class="home-footer-left">2026 UEsed Books</div>
  <div class="home-footer-right">
    <a href="#">Explorer</a>
    <a href="#">Shop</a>
    <a href="about.php">About</a>
  </div>
</footer>

<!-- SELL MODAL -->
<div class="modal-overlay" id="sellModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">List a Book for Sale</span>
      <button type="button" class="modal-close" id="closeSellModal">✕</button>
    </div>
    <form method="POST" action="listing.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_book">
      <div class="form-group"><label class="form-label">Book Title</label><input class="form-input" name="title" type="text" placeholder="e.g. Calculus 8th Edition" required></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-input form-textarea" name="description" placeholder="Condition, edition, notes..."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Price (₱)</label><input class="form-input" name="price" type="number" min="0" placeholder="150"></div>
        <div class="form-group"><label class="form-label">Stock / Copies</label><input class="form-input" name="stock" type="number" min="1" value="1"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Cover Image</label>
        <div class="file-upload-wrap">
          <input type="file" name="image" id="sellImageInput" accept="image/*" style="display:none;">
          <button type="button" class="file-upload-btn" onclick="document.getElementById('sellImageInput').click()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Attach Image
          </button>
          <span class="file-name-label" id="sellFileName">No file chosen</span>
        </div>
      </div>
      <button type="submit" class="modal-submit">List My Book</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Listing</span>
      <button type="button" class="modal-close" id="closeEditModal">✕</button>
    </div>
    <form method="POST" action="listing.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_book">
      <input type="hidden" name="book_id" id="editBookId">
      <div class="form-group"><label class="form-label">Book Title</label><input class="form-input" name="title" id="editTitle" type="text" required></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-input form-textarea" name="description" id="editDesc" placeholder="Condition, edition, notes..."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Price (₱)</label><input class="form-input" name="price" id="editPrice" type="number" min="0"></div>
        <div class="form-group"><label class="form-label">Stock / Copies</label><input class="form-input" name="stock" id="editStock" type="number" min="1"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Cover Image <span style="font-weight:400;color:#aaa;">(leave blank to keep current)</span></label>
        <div class="file-upload-wrap">
          <input type="file" name="image" id="editImageInput" accept="image/*" style="display:none;">
          <button type="button" class="file-upload-btn" onclick="document.getElementById('editImageInput').click()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Attach Image
          </button>
          <span class="file-name-label" id="editFileName">No file chosen</span>
        </div>
      </div>
      <div class="modal-actions">
        <button type="submit" class="modal-save-btn">Save Changes</button>
        <button type="button" class="modal-delete-btn" id="deleteBookBtn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Delete Listing
        </button>
      </div>
    </form>
  </div>
</div>

<!-- BUY MODAL -->
<div class="modal-overlay" id="buyModal">
  <div class="buy-modal">
    <div class="buy-modal-header">
      <h2>Order Confirmation</h2>
      <button class="buy-modal-close" id="closeBuyModal">✕</button>
    </div>
    <div class="buy-modal-body">
      <p class="buy-modal-item-label">Item details</p>
      <div class="buy-modal-title" id="buyModalTitle">Book Title</div>
      <div class="buy-modal-price" id="buyModalPrice">₱0.00</div>
      <input type="hidden" id="buyBookId">
      <!-- Hidden field storing the raw YYYY-MM-DD value for submission -->
      <input type="hidden" id="buyModalDateRaw">
      <div class="buy-modal-grid">
        <div class="buy-modal-field">
          <label class="buy-modal-label">Name</label>
          <input class="buy-modal-input" id="buyModalBuyer" type="text" value="<?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))); ?>" readonly>
        </div>
        <div class="buy-modal-field">
          <label class="buy-modal-label">Email</label>
          <input class="buy-modal-input" id="buyModalEmail" type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" readonly>
        </div>
        <!-- Date — required, full width -->
        <div class="buy-modal-field full">
          <label class="buy-modal-label">Meet-up Date <span class="req">*</span></label>
          <div class="custom-date-wrap" id="customDateWrap">
            <input class="buy-modal-input" id="buyModalDate" type="text" placeholder="Select meet-up date" readonly>
            <svg class="custom-date-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <div class="custom-calendar" id="customCalendar"></div>
          </div>
        </div>
        <!-- Meet-up place — required -->
        <div class="buy-modal-field full">
          <label class="buy-modal-label">Meet-up Place <span class="req">*</span></label>
          <input class="buy-modal-input" id="buyModalMeetup" type="text" placeholder="e.g. UE Caloocan Library, Gate 1...">
        </div>
        <div class="buy-modal-field full">
          <label class="buy-modal-label">Message (optional)</label>
          <textarea class="buy-modal-textarea" id="buyModalMessage" placeholder="Any notes for the seller..."></textarea>
        </div>
      </div>
    </div>
    <div class="buy-modal-footer">
      <button class="buy-modal-cancel" id="cancelBuyModal">Cancel</button>
      <button class="buy-modal-confirm" id="confirmBuyBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Confirm
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<form method="POST" action="listing.php" id="deleteBookForm">
  <input type="hidden" name="action" value="delete_book">
  <input type="hidden" name="book_id" id="deleteBookId">
</form>

<?php
$books_result = $conn->query("SELECT id, title, seller, seller_id, stock, price, image, description, created_at FROM books ORDER BY created_at DESC");
$books_data = [];
while ($row = $books_result->fetch_assoc()) {
    $row['is_owner'] = isBookOwner($conn, $row['id']);
    $books_data[] = $row;
}
?>

<script>
let books = <?php echo json_encode(array_map(function($b) {
    return [
        'id'       => (int)$b['id'],
        'title'    => $b['title'],
        'seller'   => $b['seller'] ?? '',
        'desc'     => $b['description'] ?? '',
        'price'    => (float)$b['price'],
        'stock'    => (int)$b['stock'],
        'image'    => !empty($b['image']) ? 'images/' . $b['image'] : '',
        'created'  => strtotime($b['created_at']) * 1000,
        'is_owner' => (bool)$b['is_owner'],
    ];
}, $books_data)); ?>;

const currentUser = <?php echo json_encode($_SESSION['username'] ?? $_SESSION['email'] ?? ''); ?>;
let sortMode = 'newest', searchQuery = '', hideMine = false;

function getFiltered() {
  let list = [...books];
  if (hideMine) list = list.filter(b => !b.is_owner);
  if (searchQuery) { const q = searchQuery.toLowerCase(); list = list.filter(b => b.title.toLowerCase().includes(q)); }
  list.sort((a,b) => {
    if (sortMode==='newest') return b.created - a.created;
    if (sortMode==='oldest') return a.created - b.created;
    if (sortMode==='az') return a.title.localeCompare(b.title);
    if (sortMode==='za') return b.title.localeCompare(a.title);
    return 0;
  });
  return list;
}

function renderGrid() {
  const grid = document.getElementById('booksGrid');
  const list = getFiltered();
  const loggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
  if (!list.length) {
    grid.innerHTML = `<div class="empty-state"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><p>No books found.</p></div>`;
    return;
  }
  grid.innerHTML = list.map((b,i) => `
    <div class="book-card" data-id="${b.id}" style="animation-delay:${i*0.05}s">
      <div class="book-cover-placeholder">
        ${b.image ? `<img src="${b.image}" alt="${b.title}" onerror="this.style.display='none'">` : `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>`}
      </div>
      <div class="book-info">
        <div class="book-title">${b.title}</div>
        <div class="book-desc">${b.desc || 'No description provided.'}</div>
        <div class="book-price">₱${Number(b.price).toFixed(2)}</div>
        <span class="book-status-badge ${b.stock <= 0 ? 'badge-sold' : 'badge-available'}">${b.stock <= 0 ? 'Not Available' : 'Available'}</span>
      </div>
      ${b.is_owner
        ? `<button class="update-btn" onclick="openEditModal(${b.id},'${b.title.replace(/'/g,"\\'")}',${b.price},${b.stock},'${(b.desc||'').replace(/'/g,"\\'").replace(/\n/g,' ')}')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Update</button>`
        : loggedIn
          ? b.stock <= 0
            ? `<button class="buy-btn" disabled style="opacity:0.45;cursor:not-allowed;background:#aaa;">Unavailable</button>`
            : `<button class="buy-btn" onclick="openBuyModal(${b.id},'${b.title.replace(/'/g,"\\'")}',${b.price},'${b.image}','${b.seller.replace(/'/g,"\\'")}')">
               <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
               Buy Now</button>`
          : `<a href="login.php" class="buy-btn" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">Login to Buy</a>`
      }
    </div>
  `).join('');
}

document.getElementById('searchInput').addEventListener('input', function() { searchQuery = this.value; renderGrid(); });

const hideBtn = document.getElementById('hideMyListings');
hideBtn.addEventListener('click', function() {
  hideMine = !hideMine;
  this.classList.toggle('active', hideMine);
  this.innerHTML = hideMine
    ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Show My Listings`
    : `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg> Hide My Listings`;
  renderGrid();
});

const sortBtn = document.getElementById('sortBtn');
const sortMenu = document.getElementById('sortMenu');
sortBtn.addEventListener('click', (e) => { e.stopPropagation(); sortMenu.classList.toggle('open'); });
document.querySelectorAll('.sort-option').forEach(option => {
  option.addEventListener('click', () => {
    const selected = option.dataset.sort;
    sortMode = selected === 'alphabetical' ? 'az' : selected;
    sortBtn.childNodes[0].textContent = option.textContent + ' ';
    document.querySelectorAll('.sort-option').forEach(o => o.classList.remove('active'));
    option.classList.add('active');
    sortMenu.classList.remove('open');
    renderGrid();
  });
});
document.addEventListener('click', (e) => { if (!e.target.closest('.sort-dropdown')) sortMenu.classList.remove('open'); });

const sellModal = document.getElementById('sellModal');
function openModal() { sellModal.classList.add('open'); }
function closeModal() { sellModal.classList.remove('open'); }
document.getElementById('openSellModal').addEventListener('click', openModal);
document.getElementById('fabSell').addEventListener('click', openModal);
document.getElementById('closeSellModal').addEventListener('click', closeModal);
sellModal.addEventListener('click', e => { if (e.target === sellModal) closeModal(); });

const editModal = document.getElementById('editModal');
function openEditModal(id, title, price, stock, desc) {
  document.getElementById('editBookId').value = id;
  document.getElementById('editTitle').value = title;
  document.getElementById('editPrice').value = price;
  document.getElementById('editStock').value = stock;
  document.getElementById('editDesc').value = desc || '';
  editModal.classList.add('open');
}
document.getElementById('closeEditModal').addEventListener('click', () => editModal.classList.remove('open'));
editModal.addEventListener('click', e => { if (e.target === editModal) editModal.classList.remove('open'); });

document.getElementById('deleteBookBtn').addEventListener('click', function() {
  if (confirm('Are you sure you want to delete this listing? This cannot be undone.')) {
    const bookId = document.getElementById('editBookId').value;
    const card = document.querySelector(`.book-card[data-id="${bookId}"]`);
    editModal.classList.remove('open');
    if (card) {
      card.classList.add('removing');
      card.addEventListener('animationend', () => { document.getElementById('deleteBookId').value = bookId; document.getElementById('deleteBookForm').submit(); }, { once: true });
    } else {
      document.getElementById('deleteBookId').value = bookId;
      document.getElementById('deleteBookForm').submit();
    }
  }
});

document.getElementById('sellImageInput').addEventListener('change', function() { document.getElementById('sellFileName').textContent = this.files[0]?.name || 'No file chosen'; });
document.getElementById('editImageInput').addEventListener('change', function() { document.getElementById('editFileName').textContent = this.files[0]?.name || 'No file chosen'; });

// ── Buy Modal ──────────────────────────────────────────────────────────────
const buyModal = document.getElementById('buyModal');
const confirmBuyBtn = document.getElementById('confirmBuyBtn');

function openBuyModal(id, title, price, image, seller) {
  document.getElementById('buyBookId').value = id;
  document.getElementById('buyModalTitle').textContent = title;
  document.getElementById('buyModalPrice').textContent = '₱' + Number(price).toFixed(2);
  document.getElementById('buyModalDate').value = '';
  document.getElementById('buyModalDateRaw').value = '';   // ← clear raw ISO date too
  document.getElementById('buyModalMeetup').value = '';
  document.getElementById('buyModalMessage').value = '';
  document.getElementById('buyModalDate').classList.remove('field-error');
  document.getElementById('buyModalMeetup').classList.remove('field-error');
  confirmBuyBtn.disabled = false;
  confirmBuyBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm`;
  buyModal.classList.add('open');
}
document.getElementById('closeBuyModal').addEventListener('click', () => buyModal.classList.remove('open'));
document.getElementById('cancelBuyModal').addEventListener('click', () => buyModal.classList.remove('open'));
buyModal.addEventListener('click', e => { if (e.target === buyModal) buyModal.classList.remove('open'); });

document.getElementById('buyModalDate').addEventListener('click', function() { this.classList.remove('field-error'); });
document.getElementById('buyModalMeetup').addEventListener('input', function() { this.classList.remove('field-error'); });

confirmBuyBtn.addEventListener('click', function() {
  const bookId  = document.getElementById('buyBookId').value;
  // Use the raw YYYY-MM-DD value stored by the calendar, not the display string
  const dateVal = document.getElementById('buyModalDateRaw').value.trim();
  const meetup  = document.getElementById('buyModalMeetup').value.trim();
  const message = document.getElementById('buyModalMessage').value.trim();

  let hasError = false;
  if (!dateVal) {
    document.getElementById('buyModalDate').classList.add('field-error');
    hasError = true;
  }
  if (!meetup) {
    document.getElementById('buyModalMeetup').classList.add('field-error');
    hasError = true;
  }
  if (hasError) {
    const missing = !dateVal && !meetup
      ? 'Please select a date and enter a meet-up place.'
      : !dateVal ? 'Please select a meet-up date.' : 'Please enter a meet-up place.';
    showToast(missing, 'error');
    return;
  }

  this.disabled = true;
  this.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 0.8s linear infinite"><polyline points="20 6 9 17 4 12"/></svg> Sending...`;

  const fd = new FormData();
  fd.append('book_id', bookId);
  fd.append('meetup_place', meetup);
  fd.append('transaction_date', dateVal);   // ← sends clean YYYY-MM-DD
  fd.append('message', message);
  fd.append('meetup_date', document.getElementById('buyModalDate').value);

  fetch('buy_request.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      buyModal.classList.remove('open');
      if (data.success) {
        showToast('✓ ' + data.message, 'success');
        const card = document.querySelector(`.book-card[data-id="${bookId}"]`);
        if (card) {
          const badge = card.querySelector('.book-status-badge');
          const btn   = card.querySelector('.buy-btn');
          if (data.stock <= 0) {
            if (badge) { badge.className = 'book-status-badge badge-sold'; badge.textContent = 'Not Available'; }
            if (btn)   { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; btn.innerHTML = 'Not Available'; }
          } else {
            if (badge) { badge.className = 'book-status-badge badge-pending'; badge.textContent = 'Pending'; }
            if (btn)   { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; btn.innerHTML = 'Request Sent'; }
          }
        }
      } else {
        showToast('✕ ' + data.message, 'error');
        confirmBuyBtn.disabled = false;
        confirmBuyBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm`;
      }
    })
    .catch(() => { buyModal.classList.remove('open'); showToast('✕ Network error.', 'error'); });
});

function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'toast ' + type;
  void t.offsetWidth; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}

const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(spinStyle);

// ── Custom Calendar ────────────────────────────────────────────────────────
(function() {
  const input    = document.getElementById('buyModalDate');
  const rawInput = document.getElementById('buyModalDateRaw');  // ← stores YYYY-MM-DD
  const calendar = document.getElementById('customCalendar');
  const wrap     = document.getElementById('customDateWrap');
  const dayNames   = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  let current = new Date();
  let selected = null;
  let showYearPicker = false;
  const today = new Date(); today.setHours(0,0,0,0);

  function render() {
    const year  = current.getFullYear();
    const month = current.getMonth();
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    let html = `<div class="cal-header">
      <button class="cal-nav" id="calPrev">&#8249;</button>
      <div class="cal-header-label">
        <span class="cal-month-label">${monthNames[month]}</span>
        <button class="cal-year-btn" id="calYearBtn">${year}
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
      </div>
      <button class="cal-nav" id="calNext">&#8250;</button>
    </div>`;

    if (showYearPicker) {
      const startYear = year - 5;
      html += '<div class="cal-year-list">';
      for (let y = startYear; y <= startYear + 11; y++) {
        html += `<button class="cal-year-item${y === year ? ' active' : ''}" data-year="${y}">${y}</button>`;
      }
      html += '</div>';
    } else {
      html += `<div class="cal-grid"><div class="cal-days-header">`;
      dayNames.forEach(d => { html += `<span>${d}</span>`; });
      html += `</div><div class="cal-days">`;
      for (let i = 0; i < firstDay; i++) { html += `<button class="cal-day other-month" disabled></button>`; }
      for (let d = 1; d <= daysInMonth; d++) {
        const date    = new Date(year, month, d);
        const isPast  = date < today;
        const isToday = date.getTime() === today.getTime();
        const isSel   = selected && date.getTime() === selected.getTime();
        const cls     = [isPast && !isToday ? 'disabled' : '', isToday && !isSel ? 'today' : '', isSel ? 'selected' : ''].filter(Boolean).join(' ');
        const ds      = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        html += `<button class="cal-day ${cls}" data-date="${ds}" ${isPast && !isToday ? 'disabled' : ''}>${d}</button>`;
      }
      html += `</div></div>`;
    }

    calendar.innerHTML = html;

    document.getElementById('calPrev').addEventListener('click', e => {
      e.stopPropagation();
      current = showYearPicker ? new Date(year - 6, month, 1) : new Date(year, month - 1, 1);
      render();
    });
    document.getElementById('calNext').addEventListener('click', e => {
      e.stopPropagation();
      current = showYearPicker ? new Date(year + 6, month, 1) : new Date(year, month + 1, 1);
      render();
    });
    document.getElementById('calYearBtn').addEventListener('click', e => {
      e.stopPropagation();
      showYearPicker = !showYearPicker;
      render();
    });

    if (showYearPicker) {
      calendar.querySelectorAll('.cal-year-item').forEach(btn => {
        btn.addEventListener('click', e => {
          e.stopPropagation();
          current = new Date(parseInt(btn.dataset.year), month, 1);
          showYearPicker = false;
          render();
        });
      });
    } else {
      calendar.querySelectorAll('.cal-day:not([disabled])').forEach(btn => {
        btn.addEventListener('click', e => {
          e.stopPropagation();
          const [y, m, d] = btn.dataset.date.split('-').map(Number);
          selected = new Date(y, m - 1, d);

          // Display value — human-readable (shown in the input)
          input.value = `${monthNames[m-1]} ${d}, ${y}`;

          // Raw value — YYYY-MM-DD (sent to the server)
          rawInput.value = btn.dataset.date;

          input.classList.remove('field-error');
          calendar.classList.remove('open');
          showYearPicker = false;
        });
      });
    }
  }

  input.addEventListener('click', e => {
    e.stopPropagation();
    showYearPicker = false;
    render();
    calendar.classList.toggle('open');
  });

  document.addEventListener('click', e => {
    if (!wrap.contains(e.target)) { calendar.classList.remove('open'); showYearPicker = false; }
  });
})();

renderGrid();

(function() {
  const fab = document.getElementById('fabSell');
  const footer = document.querySelector('.home-footer');
  if (!fab || !footer) return;
  const obs = new IntersectionObserver(([entry]) => {
    fab.style.bottom = entry.isIntersecting ? (footer.offsetHeight + 16) + 'px' : '2rem';
  }, { threshold: 0 });
  obs.observe(footer);
})();
</script>
</body>
</html>