<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Already authenticated? Send to the right dashboard.
if (!empty($_SESSION['admin_logged_in']))    { header('Location: /Traveloka/AdminDashboard/index.php'); exit; }
if (!empty($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/ProviderDashboard/index.php'); exit; }
if (!empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Traveloka Car Rental — Find Your Ride</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue:    #0064D2;
      --blue-dk: #004FAA;
      --orange:  #FF6000;
      --navy:    #001A3C;
      --border:  #E4E8EF;
      --muted:   #637083;
      --surface: #fff;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Inter', sans-serif; color: #0D1B30; overflow-x: hidden; }

    /* ── NAVBAR ── */
    #mainNav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
      padding: 18px 0;
      transition: background .3s, box-shadow .3s, padding .3s;
    }
    #mainNav.scrolled {
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(12px);
      box-shadow: 0 2px 20px rgba(0,0,0,0.08);
      padding: 12px 0;
    }
    .nav-brand {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 900; font-size: 1.5rem;
      color: #fff; text-decoration: none; letter-spacing: -.5px;
      transition: color .3s;
    }
    .nav-brand .t { color: var(--orange); }
    #mainNav.scrolled .nav-brand { color: var(--navy); }
    .nav-link-tv {
      color: rgba(255,255,255,0.8) !important;
      font-size: 14px; font-weight: 500;
      padding: 6px 14px !important;
      border-radius: 8px; text-decoration: none;
      transition: color .18s, background .18s;
    }
    .nav-link-tv:hover { color: #fff !important; background: rgba(255,255,255,0.12); }
    #mainNav.scrolled .nav-link-tv { color: var(--muted) !important; }
    #mainNav.scrolled .nav-link-tv:hover { color: var(--navy) !important; background: #F1F5F9; }
    .btn-nav-signin {
      background: rgba(255,255,255,0.18);
      backdrop-filter: blur(4px);
      color: #fff; border: 1.5px solid rgba(255,255,255,0.38);
      padding: 8px 22px; border-radius: 99px;
      font-size: 14px; font-weight: 600; text-decoration: none;
      display: inline-flex; align-items: center; gap: 7px;
      transition: all .18s; white-space: nowrap;
    }
    .btn-nav-signin:hover { background: rgba(255,255,255,0.32); color: #fff; }
    #mainNav.scrolled .btn-nav-signin { background: var(--blue); border-color: var(--blue); color: #fff; }
    #mainNav.scrolled .btn-nav-signin:hover { background: var(--blue-dk); }
    .navbar-toggler { border: 1.5px solid rgba(255,255,255,0.4); padding: 5px 9px; }
    .navbar-toggler-icon { filter: invert(1); }
    #mainNav.scrolled .navbar-toggler { border-color: var(--muted); }
    #mainNav.scrolled .navbar-toggler-icon { filter: none; }
    @media (max-width: 767px) {
      .navbar-collapse {
        background: rgba(0,20,55,0.97); backdrop-filter: blur(20px);
        padding: 16px; border-radius: 16px; margin-top: 10px;
      }
      #mainNav.scrolled .navbar-collapse { background: rgba(255,255,255,0.97); }
    }

    /* ── HERO ── */
    .hero {
      position: relative; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute; inset: 0;
      background: url('https://images.unsplash.com/photo-1485291571150-772bcfc10da5?w=1800&q=80') center/cover no-repeat;
    }
    .hero-bg::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(150deg, rgba(0,15,45,0.75) 0%, rgba(0,30,80,0.55) 55%, rgba(0,10,30,0.4) 100%);
    }
    .hero-inner { position: relative; z-index: 1; width: 100%; }
    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.22);
      color: #fff; font-size: 12px; font-weight: 700;
      letter-spacing: .7px; text-transform: uppercase;
      padding: 6px 16px; border-radius: 99px; margin-bottom: 20px;
    }
    .hero-eyebrow i { color: var(--orange); }
    .hero-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 900;
      font-size: clamp(2.4rem, 5.5vw, 4.6rem);
      color: #fff; line-height: 1.08; letter-spacing: -1.5px;
      margin-bottom: 18px;
    }
    .hero-title span { color: var(--orange); }
    .hero-sub {
      color: rgba(255,255,255,0.72);
      font-size: clamp(14px, 1.6vw, 16.5px);
      line-height: 1.7; max-width: 480px; margin-bottom: 36px;
    }

    /* ── SEARCH WIDGET ── */
    .search-widget {
      background: rgba(255,255,255,0.12); backdrop-filter: blur(22px);
      border: 1.5px solid rgba(255,255,255,0.25);
      border-radius: 20px; padding: 8px 8px 14px;
      max-width: 820px;
    }
    .s-tabs { display: flex; gap: 3px; padding: 4px 4px 10px; border-bottom: 1px solid rgba(255,255,255,0.16); margin-bottom: 12px; }
    .s-tab {
      color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 600;
      padding: 8px 16px; border-radius: 10px; border: none;
      background: transparent; cursor: pointer; transition: all .16s;
      display: flex; align-items: center; gap: 6px;
    }
    .s-tab:hover { color: #fff; background: rgba(255,255,255,0.1); }
    .s-tab.active { background: #fff; color: var(--blue); }
    .s-fields { display: grid; grid-template-columns: 2fr 1.1fr 1.1fr 1fr auto; gap: 8px; align-items: end; padding: 0 4px; }
    .s-group label { display: block; color: rgba(255,255,255,0.65); font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 6px; }
    .s-wrap { position: relative; }
    .s-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); font-size: 14px; pointer-events: none; }
    .s-input {
      width: 100%; background: rgba(255,255,255,0.14); border: 1.5px solid rgba(255,255,255,0.18);
      color: #fff; border-radius: 11px; padding: 11px 12px 11px 36px;
      font-size: 13.5px; font-weight: 500; outline: none; font-family: 'Inter', sans-serif;
      transition: border-color .16s, background .16s;
    }
    .s-input::placeholder { color: rgba(255,255,255,0.45); }
    .s-input:focus { border-color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.2); }
    .s-input::-webkit-calendar-picker-indicator { filter: invert(1); opacity: .55; cursor: pointer; }
    .btn-search {
      background: var(--orange); color: #fff; border: none; border-radius: 11px;
      padding: 11px 22px; font-size: 14px; font-weight: 700; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 7px;
      width: 100%; white-space: nowrap; transition: background .16s, transform .1s;
      font-family: 'Inter', sans-serif;
    }
    .btn-search:hover { background: #e05500; transform: translateY(-1px); }
    @media (max-width: 991px) {
      .s-fields { grid-template-columns: 1fr 1fr; }
      .s-fields > *:last-child { grid-column: 1 / -1; }
    }
    @media (max-width: 575px) {
      .s-fields { grid-template-columns: 1fr; }
      .s-tab span { display: none; }
    }

    /* Scroll hint */
    .scroll-hint {
      position: absolute; bottom: 26px; left: 50%; transform: translateX(-50%);
      z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 5px;
      color: rgba(255,255,255,0.45); font-size: 10.5px; font-weight: 700;
      letter-spacing: .6px; text-transform: uppercase;
    }
    .scroll-dot { width: 22px; height: 36px; border: 2px solid rgba(255,255,255,0.3); border-radius: 99px; display: flex; justify-content: center; padding-top: 5px; }
    .scroll-dot::before { content: ''; width: 3px; height: 7px; background: rgba(255,255,255,0.55); border-radius: 99px; animation: bob 1.8s ease-in-out infinite; }
    @keyframes bob { 0%,100%{transform:translateY(0);opacity:1} 60%{transform:translateY(9px);opacity:.3} }

    /* ── STATS BAR ── */
    .stats-bar { background: #fff; border-bottom: 1px solid #E9EEF5; padding: 26px 0; }
    .stat-item { text-align: center; }
    .stat-num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.9rem; color: var(--blue); line-height: 1; }
    .stat-num .acc { color: var(--orange); }
    .stat-lbl { font-size: 12.5px; color: var(--muted); margin-top: 4px; }

    /* ── SECTIONS ── */
    section { padding: 76px 0; }
    .s-eyebrow { display: inline-flex; align-items: center; gap: 7px; color: var(--blue); font-size: 11.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 12px; }
    .s-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: clamp(1.7rem, 3vw, 2.5rem); line-height: 1.15; letter-spacing: -.4px; margin-bottom: 12px; }
    .s-sub { color: var(--muted); font-size: 14.5px; line-height: 1.7; max-width: 500px; }

    /* ── CAR CARDS ── */
    .car-card { background: #fff; border: 1px solid var(--border); border-radius: 18px; overflow: hidden; transition: box-shadow .2s, transform .2s; }
    .car-card:hover { box-shadow: 0 12px 36px rgba(0,0,0,0.1); transform: translateY(-4px); }
    .car-card img { width: 100%; height: 190px; object-fit: cover; display: block; transition: transform .35s; }
    .car-card:hover img { transform: scale(1.04); }
    .car-card-body { padding: 18px; }
    .car-badge { display: inline-block; background: var(--blue); color: #fff; font-size: 10.5px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; padding: 3px 10px; border-radius: 6px; margin-bottom: 9px; }
    .car-name { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 16px; margin-bottom: 6px; }
    .car-meta { display: flex; gap: 14px; font-size: 12.5px; color: var(--muted); margin-bottom: 14px; }
    .car-meta i { font-size: 13px; color: var(--blue); }
    .car-price { display: flex; align-items: baseline; gap: 4px; }
    .car-price strong { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.3rem; color: var(--navy); }
    .car-price span { font-size: 12px; color: var(--muted); }

    /* ── HOW IT WORKS ── */
    .hiw-section { background: #F7FAFF; }
    .step-wrap { position: relative; width: fit-content; margin: 0 auto 16px; }
    .step-icon { width: 68px; height: 68px; border-radius: 18px; background: var(--blue); color: #fff; font-size: 26px; display: flex; align-items: center; justify-content: center; }
    .step-num { position: absolute; top: -8px; right: -8px; width: 22px; height: 22px; background: var(--orange); color: #fff; font-size: 11px; font-weight: 800; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .step-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 16.5px; margin-bottom: 8px; }
    .step-desc { color: var(--muted); font-size: 13.5px; line-height: 1.65; }

    /* ── PROVIDER CTA ── */
    .prov-section { background: linear-gradient(130deg, #001232 0%, #002B70 50%, #003A99 100%); }
    .prov-card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 18px; padding: 28px 24px; }
    .prov-card-icon { width: 52px; height: 52px; border-radius: 14px; background: rgba(255,96,0,0.15); color: var(--orange); font-size: 22px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
    .prov-card-title { color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 16px; margin-bottom: 7px; }
    .prov-card-desc { color: rgba(255,255,255,0.58); font-size: 13px; line-height: 1.6; }

    /* ── REVIEWS ── */
    .review-section { background: #F7FAFF; }
    .review-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 22px; }
    .stars { color: #F59E0B; font-size: 13.5px; margin-bottom: 11px; }
    .review-text { color: var(--muted); font-size: 13.5px; line-height: 1.7; margin-bottom: 16px; font-style: italic; }
    .reviewer { display: flex; align-items: center; gap: 11px; }
    .reviewer-av { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
    .reviewer-name { font-weight: 600; font-size: 13.5px; color: #0D1B30; }
    .reviewer-loc { font-size: 11.5px; color: var(--muted); }

    /* ── FOOTER ── */
    footer { background: #040E20; color: rgba(255,255,255,0.55); padding: 56px 0 24px; }
    .ft-brand { font-family:'Plus Jakarta Sans',sans-serif; font-weight:900; font-size:1.4rem; color:#fff; margin-bottom:9px; }
    .ft-brand .t { color:var(--orange); }
    .ft-desc { font-size:13px; line-height:1.7; max-width:250px; margin-bottom:18px; }
    .ft-title { color:#fff; font-weight:700; font-size:13.5px; margin-bottom:13px; }
    .ft-link { display:block; color:rgba(255,255,255,.48); font-size:13px; text-decoration:none; margin-bottom:8px; transition:color .14s; }
    .ft-link:hover { color:#fff; }
    .ft-bottom { border-top:1px solid rgba(255,255,255,.07); margin-top:38px; padding-top:20px; font-size:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
    .social-links { display:flex; gap:10px; }
    .social-link { width:34px; height:34px; border-radius:8px; background:rgba(255,255,255,.07); color:rgba(255,255,255,.55); display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:15px; transition:background .14s,color .14s; }
    .social-link:hover { background:var(--blue); color:#fff; }

    /* ── CTA BAND ── */
    .cta-band { background:linear-gradient(130deg,#001232 0%,var(--blue) 55%,#0059C4 100%); padding:72px 0; position:relative; overflow:hidden; }
    .cta-band::before { content:''; position:absolute; top:-80px; right:-80px; width:360px; height:360px; border-radius:50%; background:rgba(255,255,255,0.04); }
    .cta-band-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:900; font-size:clamp(1.9rem,3.5vw,2.8rem); color:#fff; letter-spacing:-.4px; }
    .cta-band-sub { color:rgba(255,255,255,.68); font-size:15px; margin:12px 0 28px; }
    .btn-cta-w { background:#fff; color:var(--blue); font-weight:700; font-size:14.5px; padding:13px 30px; border-radius:11px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all .16s; }
    .btn-cta-w:hover { background:#F0F6FF; color:var(--blue-dk); transform:translateY(-1.5px); }
    .btn-cta-o { background:transparent; color:#fff; font-weight:600; font-size:14.5px; padding:13px 30px; border-radius:11px; text-decoration:none; border:2px solid rgba(255,255,255,.4); display:inline-flex; align-items:center; gap:8px; transition:all .16s; }
    .btn-cta-o:hover { border-color:rgba(255,255,255,.8); color:#fff; }

    /* Toast */
    #toastWrap { position:fixed; bottom:22px; right:22px; z-index:9999; }
    @keyframes toastIn { from{opacity:0;transform:translateX(28px)} to{opacity:1;transform:none} }
  </style>
</head>
<body>

<!-- ─── NAVBAR ─── -->
<nav class="navbar navbar-expand-lg" id="mainNav">
  <div class="container">
    <a class="nav-brand" href="#"><span class="t">t</span>raveloka</a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav mx-auto gap-1">
        <li class="nav-item"><a href="#fleet"      class="nav-link-tv">Fleet</a></li>
        <li class="nav-item"><a href="#how"         class="nav-link-tv">How It Works</a></li>
        <li class="nav-item"><a href="#providers"   class="nav-link-tv">Providers</a></li>
        <li class="nav-item"><a href="#reviews"     class="nav-link-tv">Reviews</a></li>
        <li class="nav-item"><a href="/Traveloka/auth/signin.php" class="nav-link-tv">Sign In</a></li>
      </ul>
      <div class="d-flex gap-2 align-items-center mt-3 mt-lg-0">
        <a href="/Traveloka/auth/signin.php" class="btn-nav-signin">
          <i class="bi bi-person"></i> Sign In
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ─── HERO ─── -->
<section class="hero">
  <div class="hero-bg"></div>

  <div class="hero-inner">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10 text-center">
          <div class="hero-eyebrow">
            <i class="bi bi-geo-alt-fill"></i> Trusted Car Rental Platform
          </div>
          <h1 class="hero-title">Drive anywhere.<br><span>Book with confidence.</span></h1>
          <p class="hero-sub mx-auto">Find and book verified rental cars from trusted providers across Southeast Asia. Fast, secure, and hassle-free.</p>

          <!-- Search widget -->
          <div class="search-widget mx-auto">
            <div class="s-tabs">
              <button class="s-tab active" onclick="setTab(this,'sedan')"><i class="bi bi-car-front"></i><span>Sedan</span></button>
              <button class="s-tab" onclick="setTab(this,'suv')"><i class="bi bi-truck"></i><span>SUV</span></button>
              <button class="s-tab" onclick="setTab(this,'van')"><i class="bi bi-bus-front"></i><span>Van</span></button>
              <button class="s-tab" onclick="setTab(this,'all')"><i class="bi bi-grid-3x3-gap"></i><span>All types</span></button>
            </div>
            <form id="searchForm" onsubmit="handleSearch(event)">
              <input type="hidden" id="carType" value="sedan">
              <div class="s-fields">
                <div class="s-group">
                  <label>Pickup location</label>
                  <div class="s-wrap">
                    <i class="bi bi-geo-alt"></i>
                    <input type="text" class="s-input" id="pickupLoc" placeholder="City, area or airport…">
                  </div>
                </div>
                <div class="s-group">
                  <label>Pickup date</label>
                  <div class="s-wrap">
                    <i class="bi bi-calendar3"></i>
                    <input type="date" class="s-input" id="pickupDate">
                  </div>
                </div>
                <div class="s-group">
                  <label>Return date</label>
                  <div class="s-wrap">
                    <i class="bi bi-calendar3"></i>
                    <input type="date" class="s-input" id="returnDate">
                  </div>
                </div>
                <div class="s-group">
                  <label>Passengers</label>
                  <div class="s-wrap">
                    <i class="bi bi-people"></i>
                    <input type="number" class="s-input" id="passengers" placeholder="2" min="1" max="20">
                  </div>
                </div>
                <div class="s-group">
                  <label>&nbsp;</label>
                  <button type="submit" class="btn-search"><i class="bi bi-search"></i> Search</button>
                </div>
              </div>
            </form>
          </div>
          <!-- End search widget -->
        </div>
      </div>
    </div>
  </div>

  <div class="scroll-hint">
    <div class="scroll-dot"></div>
    Scroll
  </div>
</section>

<!-- ─── STATS ─── -->
<div class="stats-bar">
  <div class="container">
    <div class="row g-4">
      <div class="col-6 col-md-3"><div class="stat-item"><div class="stat-num">100<span class="acc">+</span></div><div class="stat-lbl">Vehicles listed</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-item"><div class="stat-num">12<span class="acc">+</span></div><div class="stat-lbl">Cities covered</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-item"><div class="stat-num">98<span class="acc">%</span></div><div class="stat-lbl">Satisfaction rate</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-item"><div class="stat-num">24<span class="acc">/7</span></div><div class="stat-lbl">Support</div></div></div>
    </div>
  </div>
</div>

<!-- ─── FLEET ─── -->
<section id="fleet">
  <div class="container">
    <div class="row align-items-end mb-5">
      <div class="col-lg-6">
        <div class="s-eyebrow"><i class="bi bi-car-front"></i> Featured Cars</div>
        <h2 class="s-title">Popular vehicles<br>this season</h2>
        <p class="s-sub">Browse our curated selection of well-maintained cars from verified providers.</p>
      </div>
      <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
        <a href="/Traveloka/CustomerDashboard/pages/search.php" style="color:var(--blue);font-weight:600;font-size:13.5px;text-decoration:none">
          Browse all cars <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-sm-6 col-lg-3">
        <div class="car-card">
          <img src="https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=500&q=80" alt="Sedan">
          <div class="car-card-body">
            <span class="car-badge">Sedan</span>
            <div class="car-name">Toyota Vios 2023</div>
            <div class="car-meta"><span><i class="bi bi-people"></i> 5 seats</span><span><i class="bi bi-fuel-pump"></i> Gasoline</span><span><i class="bi bi-gear"></i> Auto</span></div>
            <div class="car-price"><strong>₱1,500</strong><span>/ day</span></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="car-card">
          <img src="https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=500&q=80" alt="SUV">
          <div class="car-card-body">
            <span class="car-badge" style="background:var(--orange)">SUV</span>
            <div class="car-name">Ford Everest 2022</div>
            <div class="car-meta"><span><i class="bi bi-people"></i> 7 seats</span><span><i class="bi bi-fuel-pump"></i> Diesel</span><span><i class="bi bi-gear"></i> Auto</span></div>
            <div class="car-price"><strong>₱2,800</strong><span>/ day</span></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="car-card">
          <img src="https://images.unsplash.com/photo-1606664515524-ed2f786a0bd6?w=500&q=80" alt="Van">
          <div class="car-card-body">
            <span class="car-badge" style="background:#10B981">Van</span>
            <div class="car-name">Toyota Hi-Ace 2023</div>
            <div class="car-meta"><span><i class="bi bi-people"></i> 12 seats</span><span><i class="bi bi-fuel-pump"></i> Diesel</span><span><i class="bi bi-gear"></i> Manual</span></div>
            <div class="car-price"><strong>₱3,500</strong><span>/ day</span></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="car-card">
          <img src="https://images.unsplash.com/photo-1617531653332-bd46c16f4d68?w=500&q=80" alt="Hatchback">
          <div class="car-card-body">
            <span class="car-badge" style="background:#8B5CF6">Hatchback</span>
            <div class="car-name">Honda Jazz 2022</div>
            <div class="car-meta"><span><i class="bi bi-people"></i> 5 seats</span><span><i class="bi bi-fuel-pump"></i> Gasoline</span><span><i class="bi bi-gear"></i> Auto</span></div>
            <div class="car-price"><strong>₱1,200</strong><span>/ day</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── HOW IT WORKS ─── -->
<section id="how" class="hiw-section">
  <div class="container">
    <div class="text-center mb-5">
      <div class="s-eyebrow justify-content-center"><i class="bi bi-lightning-charge"></i> Simple Process</div>
      <h2 class="s-title">How Traveloka works</h2>
      <p class="s-sub mx-auto">Rent a car in three easy steps. No hidden fees, no surprises.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4 text-center">
        <div class="step-wrap">
          <div class="step-icon"><i class="bi bi-search"></i></div>
          <div class="step-num">1</div>
        </div>
        <h3 class="step-title">Search & pick</h3>
        <p class="step-desc">Browse verified cars filtered by type, location, and date. Compare rates side-by-side.</p>
      </div>
      <div class="col-md-4 text-center">
        <div class="step-wrap">
          <div class="step-icon" style="background:var(--orange)"><i class="bi bi-calendar-check"></i></div>
          <div class="step-num">2</div>
        </div>
        <h3 class="step-title">Book & pay securely</h3>
        <p class="step-desc">Confirm your booking in minutes. Get an instant e-ticket with a QR code sent to your account.</p>
      </div>
      <div class="col-md-4 text-center">
        <div class="step-wrap">
          <div class="step-icon" style="background:#10B981"><i class="bi bi-car-front-fill"></i></div>
          <div class="step-num">3</div>
        </div>
        <h3 class="step-title">Pick up & drive</h3>
        <p class="step-desc">Show your e-ticket at pickup. Enjoy the ride — our 24/7 support team is always with you.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── PROVIDERS ─── -->
<section id="providers" class="prov-section">
  <div class="container">
    <div class="row align-items-center gy-5">
      <div class="col-lg-5">
        <div class="s-eyebrow" style="color:rgba(255,255,255,0.55)"><i class="bi bi-building"></i> For Providers</div>
        <h2 class="s-title" style="color:#fff">Grow your fleet business<br>with <span style="color:var(--orange)">Traveloka</span></h2>
        <p class="s-sub" style="color:rgba(255,255,255,0.55)">List your vehicles, manage bookings, assign drivers, and track revenue — all from one dashboard.</p>
        <div class="d-flex gap-3 flex-wrap mt-4">
          <a href="/Traveloka/auth/provider_register.php" class="btn-cta-w">
            <i class="bi bi-building-add"></i> Apply as provider
          </a>
          <a href="/Traveloka/auth/signin.php" class="btn-cta-o">
            <i class="bi bi-box-arrow-in-right"></i> Provider login
          </a>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-6">
            <div class="prov-card">
              <div class="prov-card-icon"><i class="bi bi-car-front"></i></div>
              <div class="prov-card-title">Fleet management</div>
              <div class="prov-card-desc">Add, edit, and remove vehicles. Set pricing, availability, and car specs.</div>
            </div>
          </div>
          <div class="col-6">
            <div class="prov-card">
              <div class="prov-card-icon"><i class="bi bi-clipboard2-check"></i></div>
              <div class="prov-card-title">Order management</div>
              <div class="prov-card-desc">Accept or decline incoming bookings. Track active and completed orders.</div>
            </div>
          </div>
          <div class="col-6">
            <div class="prov-card">
              <div class="prov-card-icon"><i class="bi bi-person-badge"></i></div>
              <div class="prov-card-title">Driver assignment</div>
              <div class="prov-card-desc">Manage your driver roster and assign them to orders with one click.</div>
            </div>
          </div>
          <div class="col-6">
            <div class="prov-card">
              <div class="prov-card-icon"><i class="bi bi-graph-up"></i></div>
              <div class="prov-card-title">Revenue insights</div>
              <div class="prov-card-desc">View booking stats, revenue charts, and performance metrics at a glance.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── REVIEWS ─── -->
<section id="reviews" class="review-section">
  <div class="container">
    <div class="text-center mb-5">
      <div class="s-eyebrow justify-content-center"><i class="bi bi-star"></i> Reviews</div>
      <h2 class="s-title">What our customers say</h2>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="review-card">
          <div class="stars">★★★★★</div>
          <p class="review-text">"Booking was super easy and the car was exactly as described. The driver assigned was professional and on time. Will definitely book again!"</p>
          <div class="reviewer">
            <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&q=80" class="reviewer-av" alt="">
            <div>
              <div class="reviewer-name">Maria Santos</div>
              <div class="reviewer-loc"><i class="bi bi-geo-alt" style="font-size:10px"></i> Cebu City, PH</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="review-card">
          <div class="stars">★★★★★</div>
          <p class="review-text">"Used Traveloka for our family trip to Baguio. The 7-seater SUV was spotless and comfortable. The e-ticket made pickup completely seamless."</p>
          <div class="reviewer">
            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&q=80" class="reviewer-av" alt="">
            <div>
              <div class="reviewer-name">Carlo Reyes</div>
              <div class="reviewer-loc"><i class="bi bi-geo-alt" style="font-size:10px"></i> Manila, PH</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="review-card">
          <div class="stars">★★★★☆</div>
          <p class="review-text">"Great platform for comparing rental rates. Found a car ₱800 cheaper than other sites. The coupon code I got as a registered user saved me even more!"</p>
          <div class="reviewer">
            <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=80&q=80" class="reviewer-av" alt="">
            <div>
              <div class="reviewer-name">Ana Villanueva</div>
              <div class="reviewer-loc"><i class="bi bi-geo-alt" style="font-size:10px"></i> Davao City, PH</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── CTA BAND ─── -->
<section class="cta-band">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center gy-4">
      <div class="col-lg-7">
        <h2 class="cta-band-title">Ready to hit the road?</h2>
        <p class="cta-band-sub">Join thousands of travelers who trust Traveloka for their car rental needs.</p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="/Traveloka/auth/signin.php" class="btn-cta-w"><i class="bi bi-search"></i> Browse cars</a>
          <a href="/Traveloka/auth/provider_register.php" class="btn-cta-o"><i class="bi bi-building-add"></i> Become a provider</a>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-flex justify-content-end gap-3">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:260px">
          <?php foreach([['100+','Vehicles'],['12+','Cities'],['₱1.2M','Revenue paid'],['4.9★','Rating']] as $s): ?>
          <div style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);border-radius:14px;padding:16px;text-align:center">
            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.4rem;color:#fff"><?= $s[0] ?></div>
            <div style="font-size:11.5px;color:rgba(255,255,255,0.55);margin-top:3px"><?= $s[1] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── FOOTER ─── -->
<footer>
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-3 col-md-6">
        <div class="ft-brand"><span class="t">t</span>raveloka</div>
        <p class="ft-desc">Your trusted car rental platform connecting customers with verified providers across Southeast Asia.</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
          <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
        </div>
      </div>
      <div class="col-lg-2 col-md-6 col-6">
        <div class="ft-title">Customers</div>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" class="ft-link">Browse cars</a>
        <a href="/Traveloka/auth/customer_login.php?tab=register" class="ft-link">Create account</a>
        <a href="#how" class="ft-link">How it works</a>
        <a href="#reviews" class="ft-link">Reviews</a>
      </div>
      <div class="col-lg-2 col-md-6 col-6">
        <div class="ft-title">Providers</div>
        <a href="/Traveloka/auth/provider_register.php" class="ft-link">Apply now</a>
        <a href="/Traveloka/auth/signin.php" class="ft-link">Provider login</a>
        <a href="#providers" class="ft-link">Features</a>
      </div>
      <div class="col-lg-2 col-md-6 col-6">
        <div class="ft-title">Company</div>
        <a href="#" class="ft-link">About us</a>
        <a href="#" class="ft-link">Contact</a>
        <a href="#" class="ft-link">Privacy policy</a>
        <a href="#" class="ft-link">Terms of service</a>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="ft-title">Get in touch</div>
        <p style="font-size:13px;margin-bottom:14px">Have questions? Our support team is available 24/7.</p>
        <a href="mailto:support@traveloka.ph" style="display:inline-flex;align-items:center;gap:8px;color:rgba(255,255,255,.55);font-size:13px;text-decoration:none;transition:color .14s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.55)'">
          <i class="bi bi-envelope"></i> support@traveloka.ph
        </a>
      </div>
    </div>
    <div class="ft-bottom">
      <span>&copy; <?= date('Y') ?> Traveloka Car Rental. All rights reserved.</span>
      <div style="display:flex;gap:16px">
        <a href="#" class="ft-link" style="margin:0">Privacy</a>
        <a href="#" class="ft-link" style="margin:0">Terms</a>
      </div>
    </div>
  </div>
</footer>

<!-- Auth-gate modal -->
<div class="modal fade" id="gateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
    <div class="modal-content text-center position-relative">
      <div class="modal-body" style="padding:40px 32px">
        <button type="button" class="btn-close position-absolute" style="top:14px;right:14px" data-bs-dismiss="modal"></button>
        <div style="width:60px;height:60px;border-radius:18px;background:#EFF6FF;color:var(--blue);font-size:26px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <i class="bi bi-lock"></i>
        </div>
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;margin-bottom:8px">Sign in to search</h5>
        <p style="font-size:13.5px;color:var(--muted);margin-bottom:22px">Create a free account or sign in to search and book cars.</p>
        <a href="/Traveloka/auth/signin.php" class="btn-login text-decoration-none" data-bs-dismiss="modal">
          <i class="bi bi-person-check"></i> Sign in / Sign up
        </a>
        <div style="margin-top:14px">
          <form method="post" action="/Traveloka/auth/signin.php" style="margin:0">
            <input type="hidden" name="action" value="guest">
            <button type="submit" style="background:none;border:none;color:var(--blue);font-size:13px;font-weight:600;cursor:pointer;text-decoration:underline">Continue as guest instead</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Navbar scroll
  window.addEventListener('scroll', () => {
    document.getElementById('mainNav').classList.toggle('scrolled', scrollY > 40);
  });

  // Tab switch
  function setTab(el, type) {
    document.querySelectorAll('.s-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('carType').value = type;
  }

  // Search — auth gate
  function handleSearch(e) {
    e.preventDefault();
    const loc = document.getElementById('pickupLoc').value.trim();
    if (!loc) { toast('Please enter a pickup location.', 'warn'); return; }
    // Redirect to search page with params (works for both guests and logged-in)
    new bootstrap.Modal(document.getElementById('gateModal')).show();
  }

  // Toast
  function toast(msg, type = 'info') {
    const colors = { success:'#10B981', error:'#EF4444', warn:'#F59E0B', info:'var(--blue)' };
    const icons  = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warn:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
    const id = 't_' + Date.now();
    const el = document.createElement('div');
    el.style.cssText = `background:#fff;border-left:4px solid ${colors[type]};border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,0.12);padding:13px 16px;display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:500;color:#0D1B30;min-width:270px;max-width:340px;margin-top:8px;animation:toastIn .22s ease`;
    el.innerHTML = `<i class="bi ${icons[type]}" style="color:${colors[type]};font-size:17px;flex-shrink:0"></i><span style="flex:1">${msg}</span><button onclick="this.closest('div').remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:17px;line-height:1;padding:0">&times;</button>`;
    document.getElementById('toastWrap').appendChild(el);
    setTimeout(() => el.remove(), 4200);
  }
</script>
</body>
</html>
