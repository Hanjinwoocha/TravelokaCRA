<?php
// Inline session guard — allow both logged-in customers AND guests
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = !empty($_SESSION['customer_logged_in']);
$isGuest    = !$isLoggedIn && !empty($_SESSION['is_guest']);
if (!$isLoggedIn && !$isGuest) {
    header('Location: /Traveloka/index.php');
    exit;
}
$custId   = $_SESSION['customer_id']   ?? 0;
$custName = $_SESSION['customer_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Traveloka' ?> — Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="/Traveloka/CustomerDashboard/assets/css/customer.css" rel="stylesheet">
</head>
<body>

<!-- Top Navbar -->
<nav class="cust-navbar">
  <div class="cust-navbar-inner">
    <!-- Brand -->
    <a href="/Traveloka/CustomerDashboard/index.php" class="cust-brand">
      <span class="brand-t">t</span><span class="brand-rest">raveloka</span>
    </a>

    <!-- Nav links -->
    <div class="cust-nav-links">
      <?php if ($isLoggedIn): ?>
      <a href="/Traveloka/CustomerDashboard/index.php" class="cust-nav-link <?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">
        <i class="bi bi-house"></i> My Bookings
      </a>
      <?php endif; ?>
      <a href="/Traveloka/CustomerDashboard/pages/search.php" class="cust-nav-link <?= ($activePage ?? '') === 'search' ? 'active' : '' ?>">
        <i class="bi bi-search"></i> Find a Car
      </a>
      <?php if ($isLoggedIn): ?>
      <a href="/Traveloka/CustomerDashboard/pages/profile.php" class="cust-nav-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <i class="bi bi-person"></i> Profile
      </a>
      <?php endif; ?>
    </div>

    <!-- User menu -->
    <div class="cust-user-menu">
      <?php if ($isGuest): ?>
        <span class="badge-tv badge-pending" style="font-size:10.5px">Guest</span>
        <a href="/Traveloka/auth/signin.php" class="cust-nav-link" style="padding:7px 14px;background:var(--tv-blue);color:#fff">
          <i class="bi bi-box-arrow-in-right"></i> Sign in
        </a>
      <?php else: ?>
        <div class="cust-avatar"><?= strtoupper(substr($custName, 0, 2)) ?></div>
        <span class="cust-username"><?= htmlspecialchars($custName) ?></span>
        <a href="javascript:void(0)"
           data-bs-toggle="modal" data-bs-target="#logoutModal"
           class="cust-logout" title="Log out">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      <?php endif; ?>
    </div>

    <!-- Mobile hamburger -->
    <button class="cust-hamburger" onclick="toggleMobileMenu()">
      <i class="bi bi-list"></i>
    </button>
  </div>

  <!-- Mobile menu -->
  <div class="cust-mobile-menu" id="mobileMenu">
    <?php if ($isLoggedIn): ?>
    <a href="/Traveloka/CustomerDashboard/index.php" class="cust-mobile-link">
      <i class="bi bi-house"></i> My Bookings
    </a>
    <?php endif; ?>
    <a href="/Traveloka/CustomerDashboard/pages/search.php" class="cust-mobile-link">
      <i class="bi bi-search"></i> Find a Car
    </a>
    <?php if ($isLoggedIn): ?>
    <a href="/Traveloka/CustomerDashboard/pages/profile.php" class="cust-mobile-link">
      <i class="bi bi-person"></i> Profile
    </a>
    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal" class="cust-mobile-link" style="color:#DC2626">
      <i class="bi bi-box-arrow-right"></i> Log out
    </a>
    <?php else: ?>
    <a href="/Traveloka/auth/signin.php" class="cust-mobile-link" style="color:var(--tv-blue)">
      <i class="bi bi-box-arrow-in-right"></i> Sign in / Register
    </a>
    <?php endif; ?>
  </div>
</nav>

<!-- Page wrapper -->
<div class="cust-page-wrapper">
  <main class="cust-main">