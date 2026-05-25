<?php
// Auth guard — path: includes/ -> ../../ -> Traveloka/ -> auth/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /Traveloka/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Admin' ?> — Traveloka Car Rental</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
  <!-- Admin CSS — absolute path -->
  <link href="/Traveloka/AdminDashboard/assets/css/admin.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">
      <span class="brand-t">t</span><span class="brand-rest">raveloka</span>
    </div>
    <div class="brand-sub">Admin Portal</div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="/Traveloka/AdminDashboard/index.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
    </a>

    <div class="nav-section-label">Management</div>
    <a href="/Traveloka/AdminDashboard/pages/providers.php" class="nav-item <?= ($activePage ?? '') === 'providers' ? 'active' : '' ?>">
      <i class="bi bi-building"></i><span>Car providers</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/cars.php" class="nav-item <?= ($activePage ?? '') === 'cars' ? 'active' : '' ?>">
      <i class="bi bi-car-front"></i><span>Cars</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/drivers.php" class="nav-item <?= ($activePage ?? '') === 'drivers' ? 'active' : '' ?>">
      <i class="bi bi-person-badge"></i><span>Drivers</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/locations.php" class="nav-item <?= ($activePage ?? '') === 'locations' ? 'active' : '' ?>">
      <i class="bi bi-geo-alt"></i><span>Cities</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/customers.php" class="nav-item <?= ($activePage ?? '') === 'customers' ? 'active' : '' ?>">
      <i class="bi bi-people"></i><span>Customers</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/promos.php" class="nav-item <?= ($activePage ?? '') === 'promos' ? 'active' : '' ?>">
      <i class="bi bi-ticket-detailed"></i><span>Promo codes</span>
    </a>

    <div class="nav-section-label">Transactions</div>
    <a href="/Traveloka/AdminDashboard/pages/rentals.php" class="nav-item <?= ($activePage ?? '') === 'rentals' ? 'active' : '' ?>">
      <i class="bi bi-clipboard2-check"></i><span>Rental orders</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/payments.php" class="nav-item <?= ($activePage ?? '') === 'payments' ? 'active' : '' ?>">
      <i class="bi bi-credit-card"></i><span>Payments</span>
    </a>
    <a href="/Traveloka/AdminDashboard/pages/etickets.php" class="nav-item <?= ($activePage ?? '') === 'etickets' ? 'active' : '' ?>">
      <i class="bi bi-ticket-perforated"></i><span>E-tickets</span>
    </a>

    <div style="padding: 16px 10px 8px; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.08);">
      <a href="/Traveloka/auth/logout.php"
         class="nav-item"
         style="color: rgba(255,120,120,0.8);"
         onclick="return confirmLogout()">
        <i class="bi bi-box-arrow-left"></i><span>Log out</span>
      </a>
    </div>
  </nav>
</div>

<!-- Main wrapper -->
<div class="main-wrapper">
  <!-- Top navbar -->
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-breadcrumb">
      <span class="breadcrumb-page"><?= $pageTitle ?? 'Dashboard' ?></span>
    </div>
    <div class="topbar-right">
      <div class="admin-avatar"><i class="bi bi-person-fill"></i></div>
      <span class="admin-label"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator') ?></span>
      <a href="/Traveloka/auth/logout.php"
         onclick="return confirmLogout()"
         title="Log out"
         style="color:#637083; font-size:18px; text-decoration:none; margin-left:4px;">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </header>

  <!-- Page content -->
  <main class="page-content">