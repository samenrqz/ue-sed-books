<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's listings
$listings = [];
$listResult = $conn->query("SELECT id, title, description, price, stock, image, created_at FROM books WHERE seller_id = $user_id ORDER BY created_at DESC");
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) $listings[] = $row;
}

// Fetch ALL requests (as seller)
$requests = [];
$reqResult = $conn->query("SELECT t.id, t.book, t.buyer, t.buyer_email, t.amount, t.status, t.meetup_place, t.message, t.transaction_date, t.created_at FROM transactions t WHERE t.seller_id = $user_id ORDER BY t.created_at DESC");
if ($reqResult) {
    while ($row = $reqResult->fetch_assoc()) $requests[] = $row;
}

// Split requests by status for sub-tabs
$req_pending  = array_values(array_filter($requests, fn($r) => strtolower($r['status'] ?? '') === 'pending'));
$req_approved = array_values(array_filter($requests, fn($r) => strtolower($r['status'] ?? '') === 'approved'));
$req_rejected = array_values(array_filter($requests, fn($r) => strtolower($r['status'] ?? '') === 'rejected'));

// Fetch purchased books (as buyer)
$purchases = [];
$purResult = $conn->query("SELECT t.id, t.book, t.amount, t.status, t.meetup_place, t.transaction_date, t.created_at, b.image FROM transactions t LEFT JOIN books b ON b.title = t.book AND b.seller_id = t.seller_id WHERE t.buyer_id = $user_id ORDER BY t.created_at DESC");
if ($purResult) {
    while ($row = $purResult->fetch_assoc()) $purchases[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>History - UEsed Books</title>
<link href="https://fonts.googleapis.com/css2?family=Rammetto+One&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --red: #a82c2c; --red-dark: #8b2e2e; --red-light: #fdf2f2;
    --black: #263238; --gray: #555; --light: #f7f8fa;
    --white: #ffffff; --border: #e0e0e0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--light); color: var(--black); min-height: 100vh; display: flex; flex-direction: column; }

  .home-header { background: #fff; padding: 0.8rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
  .home-header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
  .home-logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
  .home-logo span { font-family: 'Rammetto One', cursive; font-size: 1.3rem; color: var(--red-dark); }
  .home-nav { display: flex; align-items: center; gap: 2.2rem; }
  .home-nav a { text-decoration: none; color: #555; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
  .home-nav a:hover { color: #333; font-weight: 700; }
  .account-actions-group { display: flex; align-items: center; gap: 8px; }
  .nav-bag-icon { cursor: pointer; transition: filter 0.25s ease; filter: brightness(0) saturate(100%) invert(17%) sepia(86%) saturate(7496%) hue-rotate(353deg) brightness(90%) contrast(120%); display: inline-block; vertical-align: middle; margin-right: 0.5rem; }
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

  .page-hero { background: var(--white); border-bottom: 1px solid var(--border); padding: 2rem 2rem 0; }
  .page-hero-inner { max-width: 1000px; margin: 0 auto; }
  .page-hero h1 { font-family: 'Rammetto One', cursive; font-size: 1.6rem; color: var(--red-dark); margin-bottom: 0.25rem; }
  .page-hero p { font-size: 0.88rem; color: #888; margin-bottom: 1.25rem; }

  .tabs { display: flex; gap: 0; }
  .tab-btn { padding: 0.75rem 1.5rem; font-size: 0.9rem; font-family: 'Inter', sans-serif; font-weight: 600; color: #888; background: none; border: none; cursor: pointer; border-bottom: 3px solid transparent; transition: color 0.2s, border-color 0.2s; display: flex; align-items: center; gap: 7px; }
  .tab-btn:hover { color: var(--black); }
  .tab-btn.active { color: var(--red); border-bottom-color: var(--red); }
  .tab-badge { background: var(--red); color: #fff; font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 20px; min-width: 20px; text-align: center; }
  .tab-btn:not(.active) .tab-badge { background: #e0e0e0; color: #888; }

  .page-content { max-width: 1000px; margin: 1.5rem auto; padding: 0 2rem 3rem; width: 100%; flex: 1; }
  .tab-panel { display: none; }
  .tab-panel.active { display: block; animation: fadeUp 0.3s ease both; }
  @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

  /* ── SUB-TABS (reuses same design tokens, smaller scale) ── */
  .sub-tabs-wrap { background: var(--white); border: 1.5px solid var(--border); border-radius: 12px; margin-bottom: 1.25rem; overflow: hidden; }
  .sub-tabs { display: flex; gap: 0; border-bottom: 1.5px solid var(--border); }
  .sub-tab-btn { padding: 0.65rem 1.25rem; font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 600; color: #888; background: none; border: none; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -1.5px; transition: color 0.2s, border-color 0.2s; display: flex; align-items: center; gap: 6px; }
  .sub-tab-btn:hover { color: var(--black); }
  .sub-tab-btn.active { color: var(--red); border-bottom-color: var(--red); }
  .sub-tab-btn .tab-badge { font-size: 0.65rem; }
  .sub-tab-btn:not(.active) .tab-badge { background: #e0e0e0; color: #888; }
  .sub-panel { display: none; padding: 1.1rem 1.1rem 1.25rem; }
  .sub-panel.active { display: block; animation: fadeUp 0.25s ease both; }

  .empty-state { text-align: center; padding: 4rem 1rem; color: #bbb; }
  .empty-state svg { margin-bottom: 1rem; }
  .empty-state h3 { font-size: 1rem; font-weight: 600; color: #aaa; margin-bottom: 0.4rem; }
  .empty-state p { font-size: 0.85rem; color: #ccc; }

  /* ── LISTING CARDS ── */
  .listings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
  .listing-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow 0.25s, transform 0.2s; animation: fadeUp 0.4s ease both; }
  .listing-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.1); transform: translateY(-2px); }
  .listing-card:nth-child(1){animation-delay:.05s} .listing-card:nth-child(2){animation-delay:.10s}
  .listing-card:nth-child(3){animation-delay:.15s} .listing-card:nth-child(4){animation-delay:.20s}
  .listing-card:nth-child(5){animation-delay:.25s}
  .listing-cover { width: 100%; height: 150px; background: linear-gradient(135deg, #dde1ea, #c8cdd8); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
  .listing-cover img { width: 100%; height: 100%; object-fit: cover; }
  .listing-cover svg { color: #a0a8b8; }
  .listing-status { position: absolute; top: 10px; right: 10px; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.04em; text-transform: uppercase; }
  .status-available { background: #e8f8e8; color: #27ae60; }
  .status-sold { background: #fdf2f2; color: var(--red); }
  .status-pending { background: #fff8e8; color: #e67e22; }
  .status-approved { background: #e8f8e8; color: #27ae60; }
  .status-rejected { background: #fdf2f2; color: var(--red); }
  .listing-body { padding: 0.85rem 1rem 0; flex: 1; }
  .listing-title { font-size: 0.92rem; font-weight: 700; color: var(--black); line-height: 1.3; margin-bottom: 3px; }
  .listing-desc { font-size: 0.75rem; color: #aaa; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .listing-price { font-size: 0.85rem; font-weight: 700; color: var(--red); margin-top: 0.4rem; }
  .listing-meta { font-size: 0.72rem; color: #bbb; margin-top: 3px; }
  .listing-actions { display: flex; gap: 0.5rem; padding: 0.75rem 1rem 1rem; flex-wrap: wrap; }
  .action-btn { flex: 1; padding: 0.5rem 0.6rem; border-radius: 8px; font-size: 0.78rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.2s; white-space: nowrap; }
  .btn-edit { background: var(--light); color: var(--black); border: 1.5px solid var(--border); }
  .btn-edit:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }
  .btn-delete { background: transparent; color: #c0392b; border: 1.5px solid #e8b4b4; }
  .btn-delete:hover { background: #fff0f0; border-color: #c0392b; }

  /* ── REQUEST CARDS ── */
  .requests-list { display: flex; flex-direction: column; gap: 1rem; }
  .request-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 14px; padding: 1.1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; transition: box-shadow 0.2s; animation: fadeUp 0.4s ease both; }
  .request-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.07); }
  .request-info { flex: 1; }
  .request-book { font-size: 0.92rem; font-weight: 700; color: var(--black); margin-bottom: 3px; }
  .request-buyer { font-size: 0.8rem; color: #888; display: flex; align-items: center; gap: 5px; }
  .request-price { font-size: 0.82rem; font-weight: 700; color: var(--red); margin-top: 4px; }
  .request-status { font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.04em; text-transform: uppercase; flex-shrink: 0; }
  .req-pending { background: #fff8e8; color: #e67e22; }
  .req-approved { background: #e8f8e8; color: #27ae60; }
  .req-rejected { background: #fdf2f2; color: var(--red); }
  .request-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
  .btn-approve { padding: 0.45rem 1rem; background: #27ae60; color: #fff; border: none; border-radius: 8px; font-size: 0.78rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background 0.2s, transform 0.15s; }
  .btn-approve:hover { background: #219a52; transform: translateY(-1px); }
  .btn-reject { padding: 0.45rem 1rem; background: transparent; color: #c0392b; border: 1.5px solid #e8b4b4; border-radius: 8px; font-size: 0.78rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background 0.2s, border-color 0.2s; }
  .btn-reject:hover { background: #fff0f0; border-color: #c0392b; }

  /* ── MY BOOKS (PURCHASED) CARDS ── */
  .purchases-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
  .purchase-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow 0.25s, transform 0.2s; animation: fadeUp 0.4s ease both; }
  .purchase-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.1); transform: translateY(-2px); }
  .purchase-card:nth-child(1){animation-delay:.05s} .purchase-card:nth-child(2){animation-delay:.10s}
  .purchase-card:nth-child(3){animation-delay:.15s} .purchase-card:nth-child(4){animation-delay:.20s}
  .purchase-cover { width: 100%; height: 140px; background: linear-gradient(135deg, #dde1ea, #c8cdd8); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
  .purchase-cover img { width: 100%; height: 100%; object-fit: cover; }
  .purchase-cover svg { color: #a0a8b8; }
  .purchase-badge { position: absolute; top: 10px; right: 10px; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.04em; text-transform: uppercase; }
  .purchase-body { padding: 0.85rem 1rem 1rem; flex: 1; display: flex; flex-direction: column; gap: 3px; }
  .purchase-title { font-size: 0.92rem; font-weight: 700; color: var(--black); line-height: 1.3; }
  .purchase-price { font-size: 0.85rem; font-weight: 700; color: var(--red); }
  .purchase-meta { font-size: 0.72rem; color: #bbb; }
  .purchase-meetup { font-size: 0.75rem; color: #888; margin-top: 2px; display: flex; align-items: center; gap: 4px; }

  /* ── FOOTER ── */
  .home-footer { background: var(--red); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
  .home-footer-left { font-family: 'Rammetto One', cursive; color: #fff; font-size: 0.85rem; }
  .home-footer-right { display: flex; gap: 1.8rem; }
  .home-footer-right a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: opacity 0.2s; }
  .home-footer-right a:hover { opacity: 0.8; }

  /* ── MODAL ── */
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
  .modal-actions { display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.25rem; }
  .modal-save-btn { width: 100%; padding: 0.65rem; background: var(--red); color: white; border: none; border-radius: 9px; font-size: 0.88rem; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.15s; display: flex; align-items: center; justify-content: center; gap: 6px; }
  .modal-save-btn:hover { background: var(--red-dark); transform: translateY(-1px); }
  .modal-delete-btn { width: 100%; padding: 0.6rem; background: transparent; color: #c0392b; border: 1.5px solid #e8b4b4; border-radius: 9px; font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.15s; }
  .modal-delete-btn:hover { background: #fff0f0; border-color: #c0392b; color: #922b21; transform: translateY(-1px); }
  .confirm-modal-icon { width: 56px; height: 56px; border-radius: 50%; background: #fdf2f2; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--red); }
  .confirm-modal-title { font-size: 1rem; font-weight: 700; color: var(--black); text-align: center; margin-bottom: 0.4rem; }
  .confirm-modal-msg { font-size: 0.85rem; color: #888; text-align: center; margin-bottom: 1.25rem; }
  .confirm-btns { display: flex; gap: 0.75rem; }
  .confirm-cancel { flex: 1; padding: 0.6rem; background: var(--light); color: var(--black); border: 1.5px solid var(--border); border-radius: 9px; font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 600; cursor: pointer; transition: background 0.2s; }
  .confirm-cancel:hover { background: #ececec; }
  .confirm-ok { flex: 1; padding: 0.6rem; background: var(--red); color: #fff; border: none; border-radius: 9px; font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 700; cursor: pointer; transition: background 0.2s; }
  .confirm-ok:hover { background: var(--red-dark); }
  @keyframes cardFadeOut { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.93); } }
  .listing-card.removing { animation: cardFadeOut 0.3s ease forwards; pointer-events: none; }
  .file-upload-wrap { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
  .file-upload-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; background: var(--white); border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.82rem; font-family: 'Inter', sans-serif; font-weight: 600; color: var(--black); cursor: pointer; transition: border-color 0.2s, background 0.2s; white-space: nowrap; }
  .file-upload-btn:hover { border-color: var(--red); background: var(--red-light); color: var(--red); }
  .file-name-label { font-size: 0.78rem; color: #aaa; font-style: italic; }
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
      <a href="listing.php">Listing</a>
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

<div class="page-hero">
  <div class="page-hero-inner">
    <h1>My History</h1>
    <p>Manage your listings, view incoming requests, and track your purchases.</p>
    <div class="tabs">
      <button class="tab-btn active" data-tab="listings">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        My Listings
        <span class="tab-badge"><?php echo count($listings); ?></span>
      </button>
      <button class="tab-btn" data-tab="requests">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Pending Requests
        <span class="tab-badge"><?php echo count($requests); ?></span>
      </button>
      <button class="tab-btn" data-tab="mybooks">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        My Books
        <span class="tab-badge"><?php echo count($purchases); ?></span>
      </button>
    </div>
  </div>
</div>

<div class="page-content">

  <!-- TAB 1: My Listings -->
  <div class="tab-panel active" id="panel-listings">
    <?php if (empty($listings)): ?>
      <div class="empty-state">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <h3>No listings yet</h3>
        <p>Head over to the listing page to sell your first book.</p>
      </div>
    <?php else: ?>
      <div class="listings-grid">
        <?php foreach ($listings as $i => $book): ?>
        <div class="listing-card" data-id="<?php echo $book['id']; ?>" style="animation-delay:<?php echo $i * 0.06; ?>s">
          <div class="listing-cover">
            <?php if (!empty($book['image']) && file_exists(__DIR__ . '/images/' . $book['image'])): ?>
              <img src="images/<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
            <?php else: ?>
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?php endif; ?>
            <span class="listing-status <?php echo $book['stock'] > 0 ? 'status-available' : 'status-sold'; ?>">
              <?php echo $book['stock'] > 0 ? 'Available' : 'Not Available'; ?>
            </span>
          </div>
          <div class="listing-body">
            <div class="listing-title"><?php echo htmlspecialchars($book['title']); ?></div>
            <div class="listing-desc"><?php echo htmlspecialchars($book['description'] ?? 'No description provided.'); ?></div>
            <div class="listing-price">₱<?php echo number_format($book['price'], 2); ?></div>
            <div class="listing-meta">Stock: <?php echo (int)$book['stock']; ?> · Listed <?php echo date('M j, Y', strtotime($book['created_at'])); ?></div>
          </div>
          <div class="listing-actions">
            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo $book['id']; ?>,'<?php echo addslashes(htmlspecialchars($book['title'])); ?>',<?php echo $book['price']; ?>,<?php echo $book['stock']; ?>,'<?php echo addslashes(htmlspecialchars($book['description'] ?? '')); ?>')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit
            </button>
            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $book['id']; ?>,'<?php echo addslashes(htmlspecialchars($book['title'])); ?>')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Delete
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- TAB 2: Requests — with Pending / Approved / Rejected sub-tabs -->
  <div class="tab-panel" id="panel-requests">
    <div class="sub-tabs-wrap">

      <!-- Sub-tab buttons -->
      <div class="sub-tabs" role="tablist">
        <button class="sub-tab-btn active" data-subtab="req-pending" role="tab" aria-selected="true">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Pending
          <span class="tab-badge"><?php echo count($req_pending); ?></span>
        </button>
        <button class="sub-tab-btn" data-subtab="req-approved" role="tab" aria-selected="false">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Approved
          <span class="tab-badge"><?php echo count($req_approved); ?></span>
        </button>
        <button class="sub-tab-btn" data-subtab="req-rejected" role="tab" aria-selected="false">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Rejected
          <span class="tab-badge"><?php echo count($req_rejected); ?></span>
        </button>
      </div>

      <!-- Sub-panel: Pending -->
      <div class="sub-panel active" id="panel-req-pending">
        <?php if (empty($req_pending)): ?>
          <div class="empty-state" style="padding:2.5rem 1rem;">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <h3>No pending requests</h3>
            <p>New buyer requests will appear here.</p>
          </div>
        <?php else: ?>
          <div class="requests-list">
            <?php foreach ($req_pending as $i => $req): ?>
            <div class="request-card" style="animation-delay:<?php echo $i * 0.06; ?>s">
              <div class="request-info">
                <div class="request-book"><?php echo htmlspecialchars($req['book']); ?></div>
                <div class="request-buyer">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?php echo htmlspecialchars($req['buyer']); ?>
                  <?php if (!empty($req['buyer_email'])): ?>
                    <span style="color:#bbb;">· <?php echo htmlspecialchars($req['buyer_email']); ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($req['meetup_place'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:3px;">📍 <?php echo htmlspecialchars($req['meetup_place']); ?></div>
                <?php endif; ?>
                <?php if (!empty($req['message'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:2px;font-style:italic;">"<?php echo htmlspecialchars($req['message']); ?>"</div>
                <?php endif; ?>
                <div class="request-price">₱<?php echo number_format((float)$req['amount'], 2); ?></div>
                <div class="listing-meta" style="margin-top:4px;">
                  📅 Meetup Date: <?php echo !empty($req['transaction_date']) ? date('M j, Y', strtotime($req['transaction_date'])) : 'Not set'; ?>
                </div>
              </div>
              <span class="request-status req-pending">Pending</span>
              <div class="request-actions">
                <button class="btn-approve" onclick="handleRequest(<?php echo (int)$req['id']; ?>, 'Approved', this)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  Approve
                </button>
                <button class="btn-reject" onclick="handleRequest(<?php echo (int)$req['id']; ?>, 'Rejected', this)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Reject
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sub-panel: Approved -->
      <div class="sub-panel" id="panel-req-approved">
        <?php if (empty($req_approved)): ?>
          <div class="empty-state" style="padding:2.5rem 1rem;">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
            <h3>No approved requests</h3>
            <p>Requests you've approved will show up here.</p>
          </div>
        <?php else: ?>
          <div class="requests-list">
            <?php foreach ($req_approved as $i => $req): ?>
            <div class="request-card" style="animation-delay:<?php echo $i * 0.06; ?>s">
              <div class="request-info">
                <div class="request-book"><?php echo htmlspecialchars($req['book']); ?></div>
                <div class="request-buyer">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?php echo htmlspecialchars($req['buyer']); ?>
                  <?php if (!empty($req['buyer_email'])): ?>
                    <span style="color:#bbb;">· <?php echo htmlspecialchars($req['buyer_email']); ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($req['meetup_place'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:3px;">📍 <?php echo htmlspecialchars($req['meetup_place']); ?></div>
                <?php endif; ?>
                <?php if (!empty($req['message'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:2px;font-style:italic;">"<?php echo htmlspecialchars($req['message']); ?>"</div>
                <?php endif; ?>
                <div class="request-price">₱<?php echo number_format((float)$req['amount'], 2); ?></div>
                <div class="listing-meta" style="margin-top:4px;">
                  📅 Meetup: <?php echo !empty($req['transaction_date']) ? date('M j, Y', strtotime($req['transaction_date'])) : 'Not set'; ?>
                </div>
              </div>
              <span class="request-status req-approved">Approved</span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sub-panel: Rejected -->
      <div class="sub-panel" id="panel-req-rejected">
        <?php if (empty($req_rejected)): ?>
          <div class="empty-state" style="padding:2.5rem 1rem;">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            <h3>No rejected requests</h3>
            <p>Requests you've declined will appear here.</p>
          </div>
        <?php else: ?>
          <div class="requests-list">
            <?php foreach ($req_rejected as $i => $req): ?>
            <div class="request-card" style="animation-delay:<?php echo $i * 0.06; ?>s">
              <div class="request-info">
                <div class="request-book"><?php echo htmlspecialchars($req['book']); ?></div>
                <div class="request-buyer">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?php echo htmlspecialchars($req['buyer']); ?>
                  <?php if (!empty($req['buyer_email'])): ?>
                    <span style="color:#bbb;">· <?php echo htmlspecialchars($req['buyer_email']); ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($req['meetup_place'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:3px;">📍 <?php echo htmlspecialchars($req['meetup_place']); ?></div>
                <?php endif; ?>
                <?php if (!empty($req['message'])): ?>
                <div style="font-size:0.75rem;color:#aaa;margin-top:2px;font-style:italic;">"<?php echo htmlspecialchars($req['message']); ?>"</div>
                <?php endif; ?>
                <div class="request-price">₱<?php echo number_format((float)$req['amount'], 2); ?></div>
                <div class="listing-meta" style="margin-top:4px;">
                  📅 Meetup: <?php echo !empty($req['transaction_date']) ? date('M j, Y', strtotime($req['transaction_date'])) : 'Not set'; ?>
                </div>
              </div>
              <span class="request-status req-rejected">Rejected</span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /.sub-tabs-wrap -->
  </div><!-- /#panel-requests -->

  <!-- TAB 3: My Books (purchased) -->
  <div class="tab-panel" id="panel-mybooks">
    <?php if (empty($purchases)): ?>
      <div class="empty-state">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <h3>No purchases yet</h3>
        <p>Books you've requested to buy will appear here.</p>
      </div>
    <?php else: ?>
      <div class="purchases-grid">
        <?php foreach ($purchases as $i => $p): ?>
        <?php
          $st = strtolower($p['status'] ?? 'pending');
          $badgeClass = $st === 'approved' ? 'status-approved' : ($st === 'rejected' ? 'status-rejected' : 'status-pending');
          $badgeLabel = ucfirst($p['status'] ?? 'Pending');
        ?>
        <div class="purchase-card" style="animation-delay:<?php echo $i * 0.06; ?>s">
          <div class="purchase-cover">
            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/images/' . $p['image'])): ?>
              <img src="images/<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['book']); ?>">
            <?php else: ?>
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?php endif; ?>
            <span class="purchase-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
          </div>
          <div class="purchase-body">
            <div class="purchase-title"><?php echo htmlspecialchars($p['book']); ?></div>
            <div class="purchase-price">₱<?php echo number_format((float)$p['amount'], 2); ?></div>
            <?php if (!empty($p['meetup_place'])): ?>
            <div class="purchase-meetup">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
              <?php echo htmlspecialchars($p['meetup_place']); ?>
            </div>
            <?php endif; ?>
            <div class="purchase-meta">Requested <?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<footer class="home-footer">
  <div class="home-footer-left">2026 UEsed Books</div>
  <div class="home-footer-right">
    <a href="#">Explorer</a>
    <a href="#">Shop</a>
    <a href="about.php">About</a>
  </div>
</footer>

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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Attach Image
          </button>
          <span class="file-name-label" id="editFileName">No file chosen</span>
        </div>
      </div>
      <div class="modal-actions">
        <button type="submit" class="modal-save-btn">Save Changes</button>
        <button type="button" class="modal-delete-btn" id="modalDeleteBtn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Delete Listing
        </button>
      </div>
    </form>
  </div>
</div>

<!-- CONFIRM DELETE MODAL -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal">
    <div class="confirm-modal-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
    </div>
    <div class="confirm-modal-title">Delete this listing?</div>
    <div class="confirm-modal-msg" id="confirmMsg">This action cannot be undone.</div>
    <div class="confirm-btns">
      <button class="confirm-cancel" id="confirmCancel">Cancel</button>
      <button class="confirm-ok" id="confirmOk">Delete</button>
    </div>
    <form method="POST" action="listing.php" id="deleteForm" style="display:none;">
      <input type="hidden" name="action" value="delete_book">
      <input type="hidden" name="book_id" id="deleteBookId">
    </form>
  </div>
</div>

<script>
// ── Primary tab switching (unchanged logic) ──────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('panel-' + this.dataset.tab).classList.add('active');
  });
});

// ── Sub-tab switching (same pattern, scoped to .sub-tabs-wrap) ───────────────
document.querySelectorAll('.sub-tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    // Deactivate all sibling sub-tab buttons
    this.closest('.sub-tabs').querySelectorAll('.sub-tab-btn').forEach(b => {
      b.classList.remove('active');
      b.setAttribute('aria-selected', 'false');
    });
    // Hide all sibling sub-panels
    this.closest('.sub-tabs-wrap').querySelectorAll('.sub-panel').forEach(p => p.classList.remove('active'));
    // Activate clicked button and its panel
    this.classList.add('active');
    this.setAttribute('aria-selected', 'true');
    document.getElementById('panel-' + this.dataset.subtab).classList.add('active');
  });
});

// ── handleRequest: approve/reject and move card to the correct sub-panel ─────
function handleRequest(txnId, status, btn) {
  btn.disabled = true;
  const card = btn.closest('.request-card');
  const fd = new FormData();
  fd.append('txn_id', txnId);
  fd.append('status', status);
  fetch('update_request.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Update badge in place
        const badge = card.querySelector('.request-status');
        if (badge) {
          badge.className = 'request-status ' + (status === 'Approved' ? 'req-approved' : 'req-rejected');
          badge.textContent = status;
        }
        // Remove action buttons
        const actions = card.querySelector('.request-actions');
        if (actions) actions.remove();

        // Move card to the matching sub-panel list
        const targetPanelId = status === 'Approved' ? 'panel-req-approved' : 'panel-req-rejected';
        const targetPanel = document.getElementById(targetPanelId);
        if (targetPanel) {
          // Remove empty-state if present
          const es = targetPanel.querySelector('.empty-state');
          if (es) {
            const list = document.createElement('div');
            list.className = 'requests-list';
            targetPanel.replaceChild(list, es);
          }
          const list = targetPanel.querySelector('.requests-list');
          if (list) list.appendChild(card);
        }

        // Remove card from pending panel; show empty-state if now empty
        const pendingList = document.querySelector('#panel-req-pending .requests-list');
        if (pendingList && pendingList.children.length === 0) {
          pendingList.outerHTML = `
            <div class="empty-state" style="padding:2.5rem 1rem;">
              <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <h3>No pending requests</h3>
              <p>New buyer requests will appear here.</p>
            </div>`;
        }

        // Update sub-tab badge counts
        updateSubBadge('req-pending',  document.querySelectorAll('#panel-req-pending  .request-card').length);
        updateSubBadge('req-approved', document.querySelectorAll('#panel-req-approved .request-card').length);
        updateSubBadge('req-rejected', document.querySelectorAll('#panel-req-rejected .request-card').length);
      } else {
        btn.disabled = false;
        alert(data.message || 'Failed.');
      }
    })
    .catch(() => { btn.disabled = false; alert('Network error.'); });
}

function updateSubBadge(subtab, count) {
  const btn = document.querySelector(`.sub-tab-btn[data-subtab="${subtab}"] .tab-badge`);
  if (btn) btn.textContent = count;
}

// ── Edit modal ────────────────────────────────────────────────────────────────
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
document.getElementById('editImageInput').addEventListener('change', function() {
  document.getElementById('editFileName').textContent = this.files[0]?.name || 'No file chosen';
});

document.getElementById('modalDeleteBtn').addEventListener('click', function() {
  const id = document.getElementById('editBookId').value;
  editModal.classList.remove('open');
  triggerConfirmDelete(id, document.getElementById('editTitle').value);
});

// ── Confirm delete modal ──────────────────────────────────────────────────────
const confirmModal = document.getElementById('confirmModal');
let pendingDeleteId = null;
function confirmDelete(id, title) { triggerConfirmDelete(id, title); }
function triggerConfirmDelete(id, title) {
  pendingDeleteId = id;
  document.getElementById('confirmMsg').textContent = 'Are you sure you want to delete "' + title + '"? This cannot be undone.';
  confirmModal.classList.add('open');
}
document.getElementById('confirmCancel').addEventListener('click', () => { confirmModal.classList.remove('open'); pendingDeleteId = null; });
confirmModal.addEventListener('click', e => { if (e.target === confirmModal) { confirmModal.classList.remove('open'); pendingDeleteId = null; } });
document.getElementById('confirmOk').addEventListener('click', function() {
  if (!pendingDeleteId) return;
  const card = document.querySelector(`.listing-card[data-id="${pendingDeleteId}"]`);
  confirmModal.classList.remove('open');
  if (card) {
    card.classList.add('removing');
    card.addEventListener('animationend', () => {
      document.getElementById('deleteBookId').value = pendingDeleteId;
      document.getElementById('deleteForm').submit();
    }, { once: true });
  } else {
    document.getElementById('deleteBookId').value = pendingDeleteId;
    document.getElementById('deleteForm').submit();
  }
});
</script>
</body>
</html>