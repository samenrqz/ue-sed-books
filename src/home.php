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

$firstName   = htmlspecialchars($_SESSION['first_name']);
$lastName    = htmlspecialchars($_SESSION['last_name']);
$userInitial = strtoupper(mb_substr($_SESSION['first_name'], 0, 1));

$profilePhoto = '';
$photoStmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
$photoStmt->bind_param("i", $_SESSION['user_id']);
$photoStmt->execute();
$photoRow = $photoStmt->get_result()->fetch_assoc();
$photoStmt->close();
if (!empty($photoRow['profile_photo']) && file_exists($photoRow['profile_photo'])) {
    $profilePhoto = $photoRow['profile_photo'];
}

$books = [];
$bookResult = $conn->query("SELECT id, title, image, price FROM books ORDER BY RAND() LIMIT 8");
if ($bookResult && $bookResult->num_rows > 0) {
    while ($row = $bookResult->fetch_assoc()) $books[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home - UEsed Books</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Rammetto+One&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  body.home-body { background: #fff; font-family: 'Inter', 'Segoe UI', sans-serif; margin: 0; padding: 0; }

  /* NAVBAR */
  .home-header { background: #fff; padding: 0.8rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
  .home-header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
  .home-logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
  .home-logo img { width: 36px; height: 36px; object-fit: contain; border-radius: 8px; }
  .home-logo span { font-family: 'Rammetto One', cursive; font-size: 1.3rem; color: #8b2e2e; }
  .home-nav { display: flex; align-items: center; gap: 2.2rem; }
  .home-nav a { text-decoration: none; color: #555; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
  .home-nav a:hover { color: #333; font-weight: 700; }
  .home-nav a.active { color: #333; }
  .account-actions-group { display: flex; align-items: center; gap: 8px; }
  .nav-bag-icon { cursor: pointer; transition: filter 0.25s ease; filter: brightness(0); display: inline-block; vertical-align: middle; margin-right: 0.5rem; }
  .nav-bag-icon:hover { filter: brightness(0) saturate(100%) invert(17%) sepia(86%) saturate(7496%) hue-rotate(353deg) brightness(90%) contrast(120%); }
  .account-dropdown { position: relative; display: inline-block; }
  .home-account-btn { display: inline-flex; align-items: center; gap: 0.4rem; background: #a82c2c; color: #fff; text-decoration: none; padding: 0.6rem 1.4rem; border-radius: 25px; font-size: 0.9rem; font-weight: 600; transition: background 0.2s, transform 0.2s, box-shadow 0.2s, border-radius 0.2s; position: relative; z-index: 2; }
  .home-account-btn svg { transition: transform 0.25s ease; }
  .account-dropdown:hover .home-account-btn { background: #8b2e2e; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(168,44,44,0.35); border-radius: 25px 25px 0 0; }
  .account-dropdown:hover .home-account-btn svg { transform: rotate(180deg); }
  .account-dropdown-menu { position: absolute; top: calc(100% - 4px); right: 0; min-width: 100%; background: #8b2e2e; border-radius: 0 0 16px 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(168,44,44,0.28); opacity: 0; pointer-events: none; transform: translateY(-6px); transition: opacity 0.2s ease, transform 0.2s ease; z-index: 1; }
  .account-dropdown:hover .account-dropdown-menu { opacity: 1; pointer-events: auto; transform: translateY(0); }
  .account-dropdown-menu a { display: flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.4rem; color: #fff; text-decoration: none; font-size: 0.88rem; font-weight: 600; white-space: nowrap; transition: background 0.15s, padding-left 0.15s; }
  .account-dropdown-menu a:hover { background: rgba(255,255,255,0.15); padding-left: 1.7rem; }
  .account-dropdown-menu a svg { flex-shrink: 0; opacity: 0.85; }

  /* HERO */
  .home-hero { max-width: 1200px; margin: 0 auto; padding: 4rem 2rem 2rem; display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 2rem; }
  .home-hero-text h1 { font-size: 2.8rem; line-height: 1.2; color: #263238; font-weight: 700; margin-bottom: 1rem; }
  .home-hero-text h1 .red { color: #a82c2c; }
  .home-hero-text p { color: #666; font-size: 0.95rem; margin-bottom: 1.5rem; }
  .home-btn-read { display: inline-block; background: #a82c2c; color: #fff; text-decoration: none; padding: 0.75rem 2rem; border-radius: 6px; font-size: 0.95rem; font-weight: 600; border: none; cursor: pointer; transition: background 0.2s, transform 0.2s, box-shadow 0.2s; }
  .home-btn-read:hover { background: #8b2e2e; transform: translateY(-3px); box-shadow: 0 6px 20px rgba(168,44,44,0.35); }
  .home-hero-visual { position: relative; display: flex; justify-content: center; align-items: center; }
  .home-hero-visual .visual-container { position: relative; height: 400px; width: 100%; max-width: 440px; margin: 0 auto; }

  /* SEARCH */
  .home-search-section { max-width: 600px; margin: 2rem auto 2.5rem; padding: 0 2rem; }
  .home-search-bar { display: flex; align-items: center; background: #fff; border: 1.5px solid #e0e0e0; border-radius: 30px; padding: 0.6rem 1.2rem; gap: 0.6rem; transition: border-color 0.2s, box-shadow 0.2s; }
  .home-search-bar:focus-within { border-color: #a82c2c; box-shadow: 0 0 0 3px rgba(168,44,44,0.08); }
  .home-search-bar svg { color: #999; flex-shrink: 0; }
  .home-search-bar input { border: none; outline: none; flex: 1; font-size: 0.95rem; font-family: 'Inter', sans-serif; color: #333; background: transparent; }
  .home-search-bar input::placeholder { color: #aaa; }

  /* BOOK LISTINGS */
  .home-listings-section { max-width: 1000px; margin: 0 auto 3rem; padding: 0 2rem; }
  .home-listings-box { border: 2px dashed #ccc; border-radius: 16px; padding: 2rem 1.5rem 1.5rem; }
  .home-listings-box h2 {
    font-family: 'Inter', sans-serif;
    font-size: 1.4rem;
    font-weight: 700;
    color: #263238;
    margin-bottom: 1.5rem;
    letter-spacing: -0.02em;
}

  /* CAROUSEL — key fix: padding instead of overflow:hidden on wrapper */
  .carousel-wrapper {
    position: relative;
    padding: 0 32px; /* makes room for arrows so they aren't clipped */
  }
  .carousel-track {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding-bottom: 4px;
  }
 .carousel-card {
  flex: 0 0 210px;
  text-align: center;
  scroll-snap-align: start;
  cursor: pointer;
  transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
}
.carousel-card:hover { transform: translateY(-6px); }

.carousel-card .book-cover-wrap {
  position: relative;
  width: 185px;
  height: 235px;
  margin: 0 auto 0.75rem;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0,0,0,0.14);
  transition: box-shadow 0.3s ease;
}
.carousel-card:hover .book-cover-wrap {
  box-shadow: 0 14px 36px rgba(168,44,44,0.22);
}
.carousel-card .book-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
}
.carousel-card:hover .book-cover { transform: scale(1.07); }

.carousel-card .book-title {
  font-size: 0.88rem;
  font-weight: 700;
  color: #263238;
  margin-bottom: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 185px;
  margin-left: auto;
  margin-right: auto;
  transition: color 0.2s;
}
.carousel-card:hover .book-title { color: #a82c2c; }
.carousel-card .book-price {
  font-size: 0.8rem;
  color: #a82c2c;
  font-weight: 700;
}

  /* ARROWS — positioned inside padding area */
  .carousel-arrow {
    position: absolute;
    top: 45%;
    transform: translateY(-50%);
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #fff;
    border: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.14);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
    color: #444;
    flex-shrink: 0;
  }
  .carousel-arrow:hover {
    background: #a82c2c;
    color: #fff;
    box-shadow: 0 6px 20px rgba(168,44,44,0.3);
    transform: translateY(-50%) scale(1.1);
  }
  .carousel-arrow.left  { left: 0; }
  .carousel-arrow.right { right: 0; }

  /* VIEW MORE */
  .home-viewmore-wrap { text-align: center; margin: 1.5rem 0 0.25rem; }
  .home-viewmore-btn { display: inline-block; background: #a82c2c; color: #fff; text-decoration: none; padding: 0.55rem 2rem; border-radius: 20px; font-size: 0.88rem; font-weight: 600; transition: background 0.2s, transform 0.2s; }
  .home-viewmore-btn:hover { background: #8b2e2e; transform: translateY(-2px); }

  /* INFO CARDS */
  .home-info-section { max-width: 1100px; margin: 0 auto 3rem; padding: 0 2rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
  .home-info-card { text-align: center; transition: transform 0.3s ease, box-shadow 0.3s ease; border-radius: 14px; padding: 0 0 1rem; }
  .home-info-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,0.12); }
  .home-info-card img { width: 100%; height: 200px; object-fit: cover; border-radius: 12px; margin-bottom: 1rem; box-shadow: 0 4px 16px rgba(0,0,0,0.08); transition: transform 0.3s ease, box-shadow 0.3s ease; }
  .home-info-card:hover img { transform: scale(1.03); box-shadow: 0 8px 24px rgba(0,0,0,0.14); }
  .home-info-card h3 { font-size: 1rem; font-weight: 700; color: #a82c2c; margin-bottom: 0.6rem; }
  .home-info-card h3 a { color: #a82c2c; text-decoration: none; transition: color 0.2s; }
  .home-info-card h3 a:hover { color: #8b2e2e; text-decoration: underline; }
  .home-info-card p { font-size: 0.82rem; color: #666; line-height: 1.6; }

  /* FOOTER */
  .home-footer { background: #a82c2c; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
  .home-footer-left { font-family: 'Rammetto One', cursive; color: #fff; font-size: 0.85rem; }
  .home-footer-right { display: flex; gap: 1.8rem; }
  .home-footer-right a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: opacity 0.2s; }
  .home-footer-right a:hover { opacity: 0.8; }
  .home-empty-listing { text-align: center; color: #aaa; padding: 3rem 0; font-size: 0.95rem; }

  @media (max-width: 768px) {
    .home-hero { grid-template-columns: 1fr; padding: 2rem 1.5rem 1rem; }
    .home-hero-text h1 { font-size: 2rem; }
    .home-hero-visual .visual-container { height: 300px; }
    .home-info-section { grid-template-columns: 1fr; gap: 1.5rem; }
    .home-nav { gap: 1.2rem; }
    .home-footer { flex-direction: column; gap: 0.8rem; text-align: center; }
  }

  @keyframes homePageFadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
  .home-page-content { animation: homePageFadeIn 0.5s cubic-bezier(.22,.61,.36,1) both; }
</style>
</head>
<body class="home-body">
<div class="home-page-content">

<header class="home-header">
  <div class="home-header-inner">
    <a href="home.php" class="home-logo">
      <img src="images/5.png" alt="UEsed Books Logo">
      <span>UEsed Books</span>
    </a>
    <nav class="home-nav">
      <a href="home.php" class="active">Home</a>
      <a href="listing.php">Listing</a>
      <a href="about.php">About</a>
    </nav>
    <div class="account-actions-group">
      <a href="history.php" title="Transactions" style="display:inline-block;vertical-align:middle;margin-right:0.5rem;">
        <svg class="nav-bag-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="14" rx="3"/>
          <path d="M16 3v4"/><path d="M8 3v4"/><path d="M3 9h18"/>
        </svg>
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

<section class="home-hero">
  <div class="home-hero-text">
    <h1><span class="red">UEsed Books:</span><br>The Ultimate Student<br>Book Swap</h1>
    <p>Please register to be a part of the community.</p>
    <a href="#info" class="home-btn-read" id="readMoreBtn">Read more</a>
  </div>
  <div class="home-hero-visual">
    <div class="visual-container">
      <div class="dots dots-top-right"></div>
      <div class="dots dots-left-middle"></div>
      <img class="book-stack" src="images/4.png" alt="Book Stack">
      <img class="floating-item student-top" src="images/1.png" alt="Student">
      <img class="floating-item students-bottom" src="images/2.png" alt="Students">
      <img class="floating-item coin" src="images/3.png" alt="Coin">
    </div>
  </div>
</section>



<section class="home-listings-section" id="listings">
  <div class="home-listings-box">
    <h2>Book Listings</h2>
    <?php if (count($books) > 0): ?>
    <div class="carousel-wrapper">
      <button class="carousel-arrow left" id="carouselLeft" aria-label="Previous">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <div class="carousel-track" id="carouselTrack">
        <?php foreach ($books as $b): ?>
        <div class="carousel-card">
  <div class="book-cover-wrap">
    <?php if (!empty($b['image'])): ?>
      <img class="book-cover" src="images/<?php echo htmlspecialchars($b['image']); ?>" alt="<?php echo htmlspecialchars($b['title']); ?>">
    <?php else: ?>
      <div class="book-cover" style="display:flex;align-items:center;justify-content:center;background:#f0f0f0;color:#bbb;font-size:0.8rem;">No Image</div>
    <?php endif; ?>
    <div class="book-cover-overlay">
      <span>
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        View
      </span>
    </div>
  </div>
  <div class="book-title"><?php echo htmlspecialchars($b['title']); ?></div>
  <div class="book-price">₱<?php echo number_format($b['price'], 2); ?></div>
</div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-arrow right" id="carouselRight" aria-label="Next">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
    <?php else: ?>
    <div class="home-empty-listing">No books listed yet. Check back soon!</div>
    <?php endif; ?>
    <div class="home-viewmore-wrap">
      <a href="listing.php" class="home-viewmore-btn">View More</a>
    </div>
  </div>
</section>

<section class="home-info-section" id="info">
  <div class="home-info-card">
    <img src="https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?w=400&h=300&fit=crop" alt="Best books of all time">
    <h3><a href="https://www.goodreads.com/list/show/1.Best_Books_Ever" target="_blank" rel="noopener">Best Books of All Time</a></h3>
    <p>Discover the most beloved books ever written — timeless classics and modern masterpieces voted by millions of readers around the world.</p>
  </div>
  <div class="home-info-card">
    <img src="https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=400&h=300&fit=crop" alt="Must-read books for students">
    <h3><a href="https://www.goodreads.com/list/show/264.Books_That_Everyone_Should_Read_At_Least_Once" target="_blank" rel="noopener">Must-Read Books for Students</a></h3>
    <p>A curated collection of essential reads every student should explore — from thought-provoking novels to life-changing non-fiction.</p>
  </div>
  <div class="home-info-card">
    <img src="https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=400&h=300&fit=crop" alt="Top books of 2025">
    <h3><a href="https://www.goodreads.com/list/show/171741.Best_Books_of_2025" target="_blank" rel="noopener">Top Rated Books of 2025</a></h3>
    <p>Catch up on the highest rated releases of the year — trending titles that readers everywhere can't stop talking about.</p>
  </div>
</section>

<footer class="home-footer">
  <div class="home-footer-left">2026 UEsed Books</div>
  <div class="home-footer-right">
    <a href="listing.php">Shop</a>
    <a href="about.php">About</a>
  </div>
</footer>

</div>

<script src="script.js"></script>
<script>
    // Click card → go to listing
document.querySelectorAll('.carousel-card').forEach(function(card) {
  card.addEventListener('click', function() {
    window.location.href = 'listing.php';
  });
});
document.getElementById('readMoreBtn').addEventListener('click', function(e) {
  e.preventDefault();
  document.getElementById('info').scrollIntoView({ behavior: 'smooth' });
});

(function() {
  var track    = document.getElementById('carouselTrack');
  var leftBtn  = document.getElementById('carouselLeft');
  var rightBtn = document.getElementById('carouselRight');
  if (!track || !leftBtn || !rightBtn) return;
  var scrollAmount = 196;
  leftBtn.addEventListener('click', function() { track.scrollBy({ left: -scrollAmount, behavior: 'smooth' }); });
  rightBtn.addEventListener('click', function() { track.scrollBy({ left: scrollAmount, behavior: 'smooth' }); });
})();
</script>
</body>
</html>