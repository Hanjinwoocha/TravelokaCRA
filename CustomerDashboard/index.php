<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle  = 'My Bookings';
$activePage = 'home';
if (session_status() === PHP_SESSION_NONE) session_start();
// Guests have no bookings — send them to search.
if (!empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in'])) {
    header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit;
}
if (empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$custId   = $_SESSION['customer_id']   ?? 0;
$custName = $_SESSION['customer_name'] ?? '';

// ── Stats ─────────────────────────────────────────────────────────────────────
try {
  $totalOrders = $pdo->prepare("SELECT COUNT(*) FROM rental_order WHERE rent_custid=?");
  $totalOrders->execute([$custId]); $totalOrders = $totalOrders->fetchColumn();

  $activeOrders = $pdo->prepare("SELECT COUNT(*) FROM eticket et JOIN payment p ON p.pay_id=et.tick_payid JOIN rental_order ro ON ro.rent_id=p.pay_rentid WHERE ro.rent_custid=? AND et.tick_status='Active'");
  $activeOrders->execute([$custId]); $activeOrders = $activeOrders->fetchColumn();

  $totalSpent = $pdo->prepare("SELECT COALESCE(SUM(p.pay_amount),0) FROM payment p JOIN rental_order ro ON ro.rent_id=p.pay_rentid WHERE ro.rent_custid=?");
  $totalSpent->execute([$custId]); $totalSpent = $totalSpent->fetchColumn();
} catch (Exception $e) { $totalOrders = $activeOrders = $totalSpent = 0; }

// ── Bookings ──────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
try {
  $where  = ['ro.rent_custid = ?'];
  $params = [$custId];
  if ($filter === 'active')    { $where[] = "et.tick_status = 'Active'"; }
  if ($filter === 'pending')   { $where[] = "et.tick_status = 'Pending'"; }
  if ($filter === 'completed') { $where[] = "et.tick_status = 'Completed'"; }
  $wc = 'WHERE ' . implode(' AND ', $where);

  $stmt = $pdo->prepare("
    SELECT ro.*, ca.car_model, ca.car_type, ca.car_capacity,
           cp.prov_name,
           p.pay_amount, p.pay_method, p.pay_id,
           et.tick_id, et.tick_status, et.tick_qrcode,
           dd.drive_firstname, dd.drive_lastname
    FROM rental_order ro
    JOIN car ca      ON ca.car_id   = ro.rent_carid
    JOIN car_provider cp ON cp.prov_id = ca.car_provid
    LEFT JOIN payment p   ON p.pay_rentid  = ro.rent_id
    LEFT JOIN eticket et  ON et.tick_payid = p.pay_id
    LEFT JOIN driver_details dd ON dd.drive_id = ro.rent_driveid
    $wc
    ORDER BY ro.rent_id DESC
  ");
  $stmt->execute($params);
  $bookings = $stmt->fetchAll();
} catch (Exception $e) { $bookings = []; }

include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div class="cust-hero">
  <h1 class="cust-hero-title">
    Hello, <span><?= htmlspecialchars($custName) ?></span>! 👋
  </h1>
  <p class="cust-hero-sub">Track your rentals, view e-tickets, and book your next trip.</p>
  <div style="display:flex;gap:24px;position:relative;z-index:1">
    <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:14px 22px;text-align:center">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:700;color:#fff"><?= $totalOrders ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">Total bookings</div>
    </div>
    <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:14px 22px;text-align:center">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:700;color:#FF6000"><?= $activeOrders ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">Active rentals</div>
    </div>
    <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:14px 22px;text-align:center">
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:700;color:#fff">₱<?= number_format($totalSpent, 0) ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">Total spent</div>
    </div>
  </div>
</div>

<!-- Bookings section -->
<div class="section-header">
  <h2 class="section-title">My bookings</h2>
  <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-orange">
    <i class="bi bi-plus-lg"></i> Book a car
  </a>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach (['all'=>'All bookings','active'=>'Active','pending'=>'Pending','completed'=>'Completed'] as $val=>$label): ?>
    <a href="?filter=<?= $val ?>"
       style="padding:6px 16px;border-radius:99px;font-size:13px;font-weight:600;text-decoration:none;
              <?= $filter===$val ? 'background:var(--tv-blue);color:#fff' : 'background:#fff;color:var(--text-secondary);border:1px solid var(--border)' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
<div class="empty-state">
  <i class="bi bi-car-front"></i>
  <h3>No bookings yet</h3>
  <p>Find and book your perfect car to get started.</p>
  <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-primary">
    <i class="bi bi-search"></i> Browse cars
  </a>
</div>
<?php else: ?>
<div class="booking-grid">
  <?php foreach ($bookings as $b):
    $status = $b['tick_status'] ?? 'Pending';
    $cls = match($status) { 'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel',default=>'badge-pending' };
    $driver = ($b['drive_firstname'] ?? '') ? trim($b['drive_firstname'].' '.$b['drive_lastname']) : 'Self-drive';
  ?>
  <div class="booking-card">
    <div class="booking-card-top">
      <div class="booking-car-icon"><i class="bi bi-car-front"></i></div>
      <div style="flex:1">
        <div class="booking-car-model"><?= htmlspecialchars($b['car_model']) ?></div>
        <div class="booking-car-type"><?= htmlspecialchars($b['car_type']) ?> &middot; <?= htmlspecialchars($b['prov_name']) ?></div>
      </div>
      <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($status) ?></span>
    </div>
    <div class="booking-card-body">
      <div class="booking-meta">
        <div class="booking-meta-item">
          <div class="booking-meta-label">Pickup</div>
          <div class="booking-meta-value"><?= htmlspecialchars($b['rent_dateissued']) ?></div>
        </div>
        <div class="booking-meta-item">
          <div class="booking-meta-label">Return</div>
          <div class="booking-meta-value"><?= htmlspecialchars($b['rent_datedue']) ?></div>
        </div>
        <div class="booking-meta-item">
          <div class="booking-meta-label">Driver</div>
          <div class="booking-meta-value" style="font-size:12px"><?= htmlspecialchars($driver) ?></div>
        </div>
        <div class="booking-meta-item">
          <div class="booking-meta-label">Total paid</div>
          <div class="booking-meta-value" style="color:var(--tv-blue)">
            <?= $b['pay_amount'] ? '₱'.number_format($b['pay_amount'],2) : '—' ?>
          </div>
        </div>
      </div>
    </div>
    <div class="booking-card-footer">
      <span style="font-size:12px;color:var(--text-secondary)">Order #<?= $b['rent_id'] ?></span>
      <?php if ($b['tick_id']): ?>
        <a href="/Traveloka/CustomerDashboard/pages/ticket.php?id=<?= $b['tick_id'] ?>" class="btn-tv-primary" style="padding:6px 14px;font-size:12.5px">
          <i class="bi bi-ticket-perforated"></i> View ticket
        </a>
      <?php else: ?>
        <a href="/Traveloka/CustomerDashboard/pages/payment.php?rent_id=<?= $b['rent_id'] ?>" class="btn-tv-orange" style="padding:6px 14px;font-size:12.5px">
          <i class="bi bi-credit-card"></i> Pay now
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>