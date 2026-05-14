<?php
// Inline session guard
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in']) || $_SESSION['provider_logged_in'] !== true) {
    header('Location: /Traveloka/index.php');
    exit;
}
$provId   = $_SESSION['provider_id']   ?? 0;
$provName = $_SESSION['provider_name'] ?? 'Provider';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Provider' ?> — Traveloka Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
  <link href="/Traveloka/ProviderDashboard/assets/css/provider.css" rel="stylesheet">
</head>
<body>

<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">
      <span class="brand-t">t</span><span class="brand-rest">raveloka</span>
    </div>
    <div class="brand-sub">Provider Portal</div>
  </div>

  <!-- Provider identity chip -->
  <div class="provider-chip">
    <div class="provider-avatar"><?= strtoupper(substr($provName, 0, 2)) ?></div>
    <div class="provider-info">
      <div class="provider-name"><?= htmlspecialchars($provName) ?></div>
      <div class="provider-role">Car Provider</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="/Traveloka/ProviderDashboard/index.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
    </a>

    <div class="nav-section-label">My Fleet</div>
    <a href="/Traveloka/ProviderDashboard/pages/fleet.php" class="nav-item <?= ($activePage ?? '') === 'fleet' ? 'active' : '' ?>">
      <i class="bi bi-car-front"></i><span>Fleet management</span>
    </a>
    <a href="/Traveloka/ProviderDashboard/pages/drivers.php" class="nav-item <?= ($activePage ?? '') === 'drivers' ? 'active' : '' ?>">
      <i class="bi bi-person-badge"></i><span>Drivers</span>
    </a>

    <div class="nav-section-label">Bookings</div>
    <a href="/Traveloka/ProviderDashboard/pages/orders.php" class="nav-item <?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
      <i class="bi bi-clipboard2-check"></i><span>Rental orders</span>
    </a>
    <a href="/Traveloka/ProviderDashboard/pages/etickets.php" class="nav-item <?= ($activePage ?? '') === 'etickets' ? 'active' : '' ?>">
      <i class="bi bi-ticket-perforated"></i><span>E-tickets</span>
    </a>

    <div class="nav-section-label">Account</div>
    <a href="/Traveloka/ProviderDashboard/pages/profile.php" class="nav-item <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
      <i class="bi bi-building-gear"></i><span>My profile</span>
    </a>

    <div style="padding:16px 10px 8px;margin-top:8px;border-top:1px solid rgba(255,255,255,0.08)">
      <a href="javascript:void(0)"
         data-bs-toggle="modal" data-bs-target="#logoutModal"
         class="nav-item"
         style="color:rgba(255,120,120,0.8)">
        <i class="bi bi-box-arrow-left"></i><span>Log out</span>
      </a>
    </div>
  </nav>
</div>

<div class="main-wrapper">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-breadcrumb">
      <span class="breadcrumb-page"><?= $pageTitle ?? 'Dashboard' ?></span>
    </div>
    <div class="topbar-right">
      <div class="provider-avatar-sm"><?= strtoupper(substr($provName, 0, 2)) ?></div>
      <span class="admin-label"><?= htmlspecialchars($provName) ?></span>
      <a href="javascript:void(0)"
         data-bs-toggle="modal" data-bs-target="#logoutModal"
         title="Log out"
         style="color:#637083;font-size:18px;text-decoration:none;margin-left:4px">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </header>

  <main class="page-content">