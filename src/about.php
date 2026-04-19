<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About - UEsed Books</title>
<link href="https://fonts.googleapis.com/css2?family=Rammetto+One&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --red: #a82c2c; --red-dark: #8b2e2e; --red-light: #fdf2f2;
    --black: #263238; --gray: #555; --light: #f7f8fa;
    --white: #ffffff; --border: #e0e0e0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', 'Segoe UI', sans-serif; background: var(--white); color: var(--black); min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }

  /* ── NAVBAR ── */
  .home-header { background: #fff; padding: 0.8rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
  .home-header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
  .home-logo { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
  .home-logo span { font-family: 'Rammetto One', cursive; font-size: 1.3rem; color: var(--red-dark); }
  .home-nav { display: flex; align-items: center; gap: 2.2rem; }
  .home-nav a { text-decoration: none; color: #555; font-size: 0.95rem; font-weight: 500; transition: color 0.2s, font-weight 0.2s; }
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

  /* ── HERO ── */
  .about-hero { position: relative; background: var(--red); overflow: hidden; padding: 5rem 2rem 4rem; text-align: center; }
  .about-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.08) 0%, transparent 60%), radial-gradient(ellipse at 80% 20%, rgba(0,0,0,0.15) 0%, transparent 50%); }
  .about-hero-circles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
  .about-hero-circles span { position: absolute; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.12); animation: float 8s ease-in-out infinite; }
  .about-hero-circles span:nth-child(1) { width: 300px; height: 300px; top: -80px; right: -60px; animation-delay: 0s; }
  .about-hero-circles span:nth-child(2) { width: 180px; height: 180px; bottom: -40px; left: 5%; animation-delay: 2s; }
  .about-hero-circles span:nth-child(3) { width: 80px; height: 80px; top: 30%; left: 15%; animation-delay: 4s; }
  .about-hero-circles span:nth-child(4) { width: 120px; height: 120px; bottom: 10%; right: 20%; animation-delay: 1s; }
  @keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-18px) rotate(5deg); } }
  .about-hero-inner { position: relative; z-index: 2; max-width: 700px; margin: 0 auto; }
  .about-hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #fff; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; padding: 0.35rem 1rem; border-radius: 20px; margin-bottom: 1.25rem; backdrop-filter: blur(4px); animation: fadeUp 0.6s ease both; }
  .about-hero h1 { font-family: 'Inter', sans-serif; font-size: clamp(2.2rem, 5vw, 3.2rem); color: #fff; line-height: 1.15; font-weight: 700; animation: fadeUp 0.6s 0.1s ease both; }
  .about-hero h1 em { font-style: normal; opacity: 0.85; }
  .about-hero p { margin-top: 1rem; color: rgba(255,255,255,0.8); font-size: 1rem; line-height: 1.7; animation: fadeUp 0.6s 0.2s ease both; }
  @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

  /* ── STATS ── */
  .stats-strip { background: var(--white); border-bottom: 1px solid var(--border); }
  .stats-strip-inner { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); }
  .stat-item { padding: 1.75rem 1rem; text-align: center; border-right: 1px solid var(--border); opacity: 0; transform: translateY(16px); transition: opacity 0.5s ease, transform 0.5s ease; }
  .stat-item:last-child { border-right: none; }
  .stat-item.visible { opacity: 1; transform: translateY(0); }
  .stat-number { font-family: 'Rammetto One', cursive; font-size: 2rem; color: var(--red); line-height: 1; }
  .stat-label { font-size: 0.8rem; color: #888; margin-top: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }

  /* ── CONTENT ── */
  .about-content { max-width: 900px; margin: 0 auto; padding: 3.5rem 2rem; width: 100%; flex: 1; }
  .section-label { display: inline-flex; align-items: center; gap: 8px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--red); margin-bottom: 0.75rem; }
  .section-label::before { content: ''; width: 24px; height: 2px; background: var(--red); display: inline-block; }

  /* ── MISSION ── */
  .mission-block { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; margin-bottom: 4rem; opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .mission-block.visible { opacity: 1; transform: translateY(0); }
  .mission-text h2 { font-family: 'Inter', sans-serif; font-size: 1.85rem; font-weight: 700; color: var(--black); line-height: 1.25; margin-bottom: 1rem; }
  .mission-text p { color: #666; font-size: 0.92rem; line-height: 1.75; margin-bottom: 0.85rem; }
  .mission-visual { position: relative; height: 260px; border-radius: 16px; background: linear-gradient(135deg, var(--red-light) 0%, #fce8e8 100%); overflow: hidden; display: flex; align-items: center; justify-content: center; }
  .mission-visual::before { content: ''; position: absolute; width: 200px; height: 200px; border-radius: 50%; background: rgba(168,44,44,0.08); top: -40px; right: -40px; }
  .mission-visual::after { content: ''; position: absolute; width: 120px; height: 120px; border-radius: 50%; background: rgba(168,44,44,0.06); bottom: -20px; left: -20px; }
  .mission-visual-icon { position: relative; z-index: 2; text-align: center; }
  .mission-visual-icon svg { color: var(--red); opacity: 0.7; }
  .mission-visual-icon p { font-family: 'Rammetto One', cursive; font-size: 0.85rem; color: var(--red-dark); margin-top: 0.75rem; opacity: 0.8; }

  /* ── VALUES ── */
  .values-section { margin-bottom: 4rem; opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .values-section.visible { opacity: 1; transform: translateY(0); }
  .values-section h2 { font-family: 'Inter', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--black); margin-bottom: 1.5rem; }
  .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
  .value-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 14px; padding: 1.5rem 1.25rem; transition: box-shadow 0.25s, transform 0.2s, border-color 0.2s; }
  .value-card:hover { box-shadow: 0 8px 28px rgba(168,44,44,0.1); transform: translateY(-3px); border-color: #e8b4b4; }
  .value-icon { width: 42px; height: 42px; background: var(--red-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
  .value-icon svg { color: var(--red); }
  .value-card h3 { font-size: 0.92rem; font-weight: 700; color: var(--black); margin-bottom: 0.4rem; }
  .value-card p { font-size: 0.8rem; color: #888; line-height: 1.6; }

  /* ── TEAM ── */
  .team-section { margin-bottom: 4rem; opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .team-section.visible { opacity: 1; transform: translateY(0); }
  .team-section h2 { font-family: 'Inter', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--black); margin-bottom: 1.5rem; }
  .team-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem; }
  .team-card { text-align: center; padding: 1.5rem 1rem; border: 1.5px solid var(--border); border-radius: 14px; transition: box-shadow 0.25s, transform 0.2s; }
  .team-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.08); transform: translateY(-3px); }
  .team-avatar { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.85rem; font-family: 'Rammetto One', cursive; font-size: 1.3rem; color: #fff; }
  .team-card h3 { font-size: 0.88rem; font-weight: 700; color: var(--black); margin-bottom: 3px; }
  .team-card span { font-size: 0.75rem; color: var(--red); font-weight: 600; }

  /* ── HOW IT WORKS ── */
  .how-section { margin-bottom: 4rem; opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .how-section.visible { opacity: 1; transform: translateY(0); }
  .how-section h2 { font-family: 'Inter', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--black); margin-bottom: 1.5rem; }
  .how-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; position: relative; }
  .how-steps::before { content: ''; position: absolute; top: 28px; left: calc(16.66% + 14px); right: calc(16.66% + 14px); height: 2px; background: linear-gradient(to right, var(--red), #e8b4b4, var(--red)); z-index: 0; }
  .how-step { text-align: center; padding: 0 1rem; position: relative; z-index: 1; }
  .step-num { width: 56px; height: 56px; border-radius: 50%; background: var(--red); color: #fff; font-family: 'Rammetto One', cursive; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 4px 14px rgba(168,44,44,0.35); transition: transform 0.2s; }
  .how-step:hover .step-num { transform: scale(1.1); }
  .how-step h3 { font-size: 0.9rem; font-weight: 700; color: var(--black); margin-bottom: 0.4rem; }
  .how-step p { font-size: 0.79rem; color: #888; line-height: 1.6; }

  /* ── CTA ── */
  .cta-banner { background: linear-gradient(135deg, var(--red) 0%, var(--red-dark) 100%); border-radius: 16px; padding: 2.5rem 2rem; text-align: center; position: relative; overflow: hidden; opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .cta-banner.visible { opacity: 1; transform: translateY(0); }
  .cta-banner::before { content: ''; position: absolute; width: 250px; height: 250px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.1); top: -80px; right: -40px; }
  .cta-banner::after { content: ''; position: absolute; width: 150px; height: 150px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.08); bottom: -50px; left: -30px; }
  .cta-banner h2 { font-family: 'Inter', sans-serif; font-size: 1.75rem; font-weight: 700; color: #fff; margin-bottom: 0.6rem; position: relative; z-index: 2; }
  .cta-banner p { color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-bottom: 1.5rem; position: relative; z-index: 2; }
  .cta-btns { display: flex; align-items: center; justify-content: center; gap: 1rem; position: relative; z-index: 2; flex-wrap: wrap; }
  .cta-btn-primary { background: #fff; color: var(--red-dark); padding: 0.7rem 1.75rem; border-radius: 25px; font-size: 0.88rem; font-weight: 700; text-decoration: none; font-family: 'Inter', sans-serif; transition: transform 0.2s, box-shadow 0.2s; }
  .cta-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
  .cta-btn-secondary { background: rgba(255,255,255,0.15); color: #fff; padding: 0.7rem 1.75rem; border-radius: 25px; font-size: 0.88rem; font-weight: 600; text-decoration: none; font-family: 'Inter', sans-serif; border: 1px solid rgba(255,255,255,0.3); transition: background 0.2s; }
  .cta-btn-secondary:hover { background: rgba(255,255,255,0.25); }

  /* ── FOOTER ── */
  .home-footer { background: var(--red); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
  .home-footer-left { font-family: 'Rammetto One', cursive; color: #fff; font-size: 0.85rem; }
  .home-footer-right { display: flex; gap: 1.8rem; }
  .home-footer-right a { color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: opacity 0.2s; }
  .home-footer-right a:hover { opacity: 0.8; }

  @media (max-width: 768px) {
    .mission-block { grid-template-columns: 1fr; gap: 1.5rem; }
    .values-grid { grid-template-columns: 1fr; }
    .team-grid { grid-template-columns: repeat(3, 1fr); }
    .how-steps { grid-template-columns: 1fr; gap: 1.5rem; }
    .how-steps::before { display: none; }
    .stats-strip-inner { grid-template-columns: 1fr; }
    .stat-item { border-right: none; border-bottom: 1px solid var(--border); }
    .stat-item:last-child { border-bottom: none; }
  }
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
      <a href="about.php" class="active">About</a>
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

<section class="about-hero">
  <div class="about-hero-circles">
    <span></span><span></span><span></span><span></span>
  </div>
  <div class="about-hero-inner">
    <div class="about-hero-badge">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      About UEsed Books
    </div>
    <h1>Where Books Find <em>New Stories</em></h1>
    <p>A student-built marketplace that makes quality textbooks affordable — one swap at a time.</p>
  </div>
</section>

<main class="about-content">

  <div class="mission-block">
    <div class="mission-text">
      <div class="section-label">Our Mission</div>
      <h2>Empowering Students Through Affordable Learning</h2>
      <p>UEsed Books was born out of a simple frustration — textbooks are expensive, and most students only use them for a semester. We built a platform where UE Caloocan students can buy and sell used books directly with each other.</p>
      <p>No middlemen. No markups. Just students helping students succeed.</p>
    </div>
    <div class="mission-visual">
      <div class="mission-visual-icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>UEsed Books</p>
      </div>
    </div>
  </div>

  <div class="values-section">
    <div class="section-label">What We Stand For</div>
    <h2>Our Core Values</h2>
    <div class="values-grid">
      <div class="value-card">
        <div class="value-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h3>Community First</h3>
        <p>Built by UE students, for UE students. Every feature is designed with our campus community in mind.</p>
      </div>
      <div class="value-card">
        <div class="value-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <h3>Transparency</h3>
        <p>Honest listings, fair prices, and clear communication between buyers and sellers — always.</p>
      </div>
      <div class="value-card">
        <div class="value-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3>Sustainability</h3>
        <p>Every reused book is one less tree cut down. We're making education greener, one listing at a time.</p>
      </div>
    </div>
  </div>

  <div class="how-section">
    <div class="section-label">The Process</div>
    <h2>How It Works</h2>
    <div class="how-steps">
      <div class="how-step">
        <div class="step-num">1</div>
        <h3>List Your Book</h3>
        <p>Snap a photo, set your price, and post your used textbook in under a minute.</p>
      </div>
      <div class="how-step">
        <div class="step-num">2</div>
        <h3>Connect & Deal</h3>
        <p>Interested buyers reach out directly. No fees, no commission — pure peer-to-peer.</p>
      </div>
      <div class="how-step">
        <div class="step-num">3</div>
        <h3>Exchange & Save</h3>
        <p>Meet on campus, complete the trade, and put that saved money to better use.</p>
      </div>
    </div>
  </div>

  <div class="team-section">
    <div class="section-label">The Builders</div>
    <h2>Meet the Team</h2>
    <div class="team-grid" id="teamGrid"></div>
  </div>

  <div class="cta-banner">
    <h2>Ready to Start Saving?</h2>
    <p>Join hundreds of UE Caloocan students already buying and selling smarter.</p>
    <div class="cta-btns">
      <a href="listing.php" class="cta-btn-primary">Browse Books</a>
      <a href="listing.php" class="cta-btn-secondary">Sell a Book</a>
    </div>
  </div>

</main>

<footer class="home-footer">
  <div class="home-footer-left">2026 UEsed Books</div>
  <div class="home-footer-right">
    <a href="listing.php">Shop</a>
    <a href="about.php">About</a>
  </div>
</footer>

<script>
// Scroll animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      entry.target.style.transitionDelay = (i * 0.05) + 's';
      entry.target.classList.add('visible');
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.mission-block, .values-section, .how-section, .team-section, .cta-banner').forEach(el => {
  observer.observe(el);
});

const statObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      entry.target.style.transitionDelay = (i * 0.1) + 's';
      entry.target.classList.add('visible');
    }
  });
}, { threshold: 0.2 });
document.querySelectorAll('.stat-item').forEach(el => statObserver.observe(el));

function animateCounter(el, target, duration = 1500) {
  let start = 0;
  const step = target / (duration / 16);
  const timer = setInterval(() => {
    start += step;
    if (start >= target) {
      el.textContent = target + (el.dataset.suffix || '+');
      clearInterval(timer);
    } else {
      el.textContent = Math.floor(start) + (el.dataset.suffix || '+');
    }
  }, 16);
}

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const el = entry.target;
      animateCounter(el, parseInt(el.dataset.target));
      counterObserver.unobserve(el);
    }
  });
}, { threshold: 0.5 });
document.querySelectorAll('.stat-number[data-target]').forEach(el => counterObserver.observe(el));

const team = [
  { first: 'Michelle', last: 'Angeles', role: 'UI/UX Designer' },
  { first: 'Shaina',   last: 'Cruz',    role: 'UI/UX Designer' },
  { first: 'Sam',      last: 'Enriquez',role: 'Lead Developer' },
  { first: 'Jaymar',   last: 'Lumbang', role: 'UI/UX Designer' },
  { first: 'Phoeben',  last: 'Andress', role: 'Presenter' },
];

const avatarColors = ['#a82c2c','#c0392b','#8b2e2e','#6d2121','#b03a2e'];

const grid = document.getElementById('teamGrid');
grid.innerHTML = team.map((member, i) => `
  <div class="team-card">
    <div class="team-avatar" style="background: linear-gradient(135deg, ${avatarColors[i]}, ${avatarColors[(i+2) % avatarColors.length]});">
      ${member.first.charAt(0).toUpperCase()}
    </div>
    <h3>${member.first}<br>${member.last}</h3>
    <span>${member.role}</span>
  </div>
`).join('');
</script>
</body>
</html>