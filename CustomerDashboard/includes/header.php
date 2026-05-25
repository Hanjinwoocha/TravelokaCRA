<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = !empty($_SESSION['customer_logged_in']);
$isGuest    = !$isLoggedIn && !empty($_SESSION['is_guest']);
if (!$isLoggedIn && !$isGuest) {
    header('Location: /Traveloka/index.php');
    exit;
}
$custId   = $_SESSION['customer_id']   ?? '';
$custName = $_SESSION['customer_name'] ?? 'Guest';
$custInitials = $isLoggedIn ? strtoupper(substr($custName, 0, 2)) : 'G';

// Live deactivation check — kick mid-session if admin has deactivated this customer
if ($isLoggedIn && $custId) {
    $__user = fb()->getDoc('users', $custId);
    if (!$__user || ($__user['isActive'] ?? true) === false) {
        session_destroy();
        header('Location: /Traveloka/index.php?deactivated=1');
        exit;
    }
}
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

<div class="app-wrapper">

  <!-- Sidebar overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- Sidebar -->
  <aside class="app-sidebar" id="appSidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
      <a href="/Traveloka/CustomerDashboard/index.php" class="sidebar-brand-link">
        <span class="sidebar-brand-t">t</span><span class="sidebar-brand-rest">raveloka</span>
      </a>
    </div>

    <!-- User card -->
    <?php if ($isLoggedIn): ?>
    <div class="sidebar-user-card">
      <div class="sidebar-user-avatar"><?= $custInitials ?></div>
      <div style="min-width:0">
        <div class="sidebar-user-name"><?= htmlspecialchars($custName) ?></div>
        <div class="sidebar-user-role">Customer</div>
      </div>
    </div>
    <?php elseif ($isGuest): ?>
    <div class="sidebar-user-card" style="background:#FFF0E8">
      <div class="sidebar-user-avatar" style="background:var(--tv-orange)">G</div>
      <div style="min-width:0">
        <div class="sidebar-user-name">Guest User</div>
        <div class="sidebar-user-role" style="color:var(--tv-orange)">Browsing</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <div class="sidebar-nav-section-label">Menu</div>

      <a href="/Traveloka/CustomerDashboard/index.php"
         class="sidebar-nav-link <?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">
        <i class="bi bi-house"></i> Dashboard
      </a>

      <?php if ($isLoggedIn): ?>
      <a href="/Traveloka/CustomerDashboard/pages/bookings.php"
         class="sidebar-nav-link <?= ($activePage ?? '') === 'bookings' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2"></i> My Bookings
      </a>
      <?php endif; ?>

      <a href="/Traveloka/CustomerDashboard/pages/search.php"
         class="sidebar-nav-link <?= ($activePage ?? '') === 'search' ? 'active' : '' ?>">
        <i class="bi bi-search"></i> Find a Car
      </a>

      <?php if ($isLoggedIn): ?>
      <a href="/Traveloka/CustomerDashboard/pages/profile.php"
         class="sidebar-nav-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <i class="bi bi-person"></i> Profile
      </a>
      <?php endif; ?>

      <div class="sidebar-divider"></div>
      <div class="sidebar-nav-section-label">General</div>

      <a href="/Traveloka/index.php" class="sidebar-nav-link">
        <i class="bi bi-house-door"></i> Back to Home
      </a>


    </nav>

    <!-- Bottom actions -->
    <div class="sidebar-bottom">
      <?php if ($isLoggedIn): ?>
      <a href="javascript:void(0)"
         data-bs-toggle="modal" data-bs-target="#logoutModal"
         class="sidebar-nav-link danger">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
      <?php else: ?>
      <a href="/Traveloka/auth/signin.php" class="sidebar-nav-link" style="color:var(--tv-blue);font-weight:700">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </a>
      <?php endif; ?>
    </div>

  </aside>

  <!-- Main area -->
  <div class="app-main">

    <!-- Top bar -->
    <div class="page-topbar">
      <button class="topbar-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
      </button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar-right">
        <?php if ($isGuest): ?>
          <span class="badge-tv badge-pending" style="font-size:10.5px">Guest</span>
          <a href="/Traveloka/auth/signin.php" class="btn-tv-primary btn-sm">
            <i class="bi bi-box-arrow-in-right"></i> Sign in
          </a>
        <?php else: ?>
          <a href="/Traveloka/CustomerDashboard/pages/profile.php"
             style="text-decoration:none;display:flex;align-items:center;gap:8px">
            <div class="topbar-avatar"><?= $custInitials ?></div>
            <span style="font-size:13px;font-weight:600;color:var(--text-secondary)"><?= htmlspecialchars($custName) ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Page content -->
    <main class="page-content">
