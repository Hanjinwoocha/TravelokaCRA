<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Session guard inline
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in']) || $_SESSION['provider_logged_in'] !== true) {
    header('Location: /Traveloka/index.php'); exit;
}
$provId   = $_SESSION['provider_id']   ?? 0;
$provName = $_SESSION['provider_name'] ?? 'Provider';

// ── Stats (scoped to this provider) ──────────────────────────────────────────
$stats = [];
$queries = [
  'cars'     => "SELECT COUNT(*) FROM car WHERE car_provid = ?",
  'drivers'  => "SELECT COUNT(*) FROM driver_details dd
                  JOIN rental_order ro ON ro.rent_driveid = dd.drive_id
                  JOIN car c ON c.car_id = ro.rent_carid
                  WHERE c.car_provid = ?",
  'orders'   => "SELECT COUNT(*) FROM rental_order ro
                  JOIN car c ON c.car_id = ro.rent_carid WHERE c.car_provid = ?",
  'active'   => "SELECT COUNT(*) FROM eticket et
                  JOIN payment p ON p.pay_id = et.tick_payid
                  JOIN rental_order ro ON ro.rent_id = p.pay_rentid
                  JOIN car c ON c.car_id = ro.rent_carid
                  WHERE c.car_provid = ? AND et.tick_status = 'Active'",
  'revenue'  => "SELECT COALESCE(SUM(p.pay_amount),0) FROM payment p
                  JOIN rental_order ro ON ro.rent_id = p.pay_rentid
                  JOIN car c ON c.car_id = ro.rent_carid WHERE c.car_provid = ?",
  'pending'  => "SELECT COUNT(*) FROM eticket et
                  JOIN payment p ON p.pay_id = et.tick_payid
                  JOIN rental_order ro ON ro.rent_id = p.pay_rentid
                  JOIN car c ON c.car_id = ro.rent_carid
                  WHERE c.car_provid = ? AND et.tick_status = 'Pending'",
];
foreach ($queries as $key => $sql) {
  try { $stmt = $pdo->prepare($sql); $stmt->execute([$provId]); $stats[$key] = $stmt->fetchColumn(); }
  catch (Exception $e) { $stats[$key] = 0; }
}

// ── Recent orders ─────────────────────────────────────────────────────────────
try {
  $stmt = $pdo->prepare("
    SELECT ro.rent_id, ro.rent_dateissued, ro.rent_datedue,
           ro.rent_pickuplocation, ro.rent_dropofflocation,
           CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
           ca.car_model, ca.car_type,
           et.tick_status,
           CONCAT(dd.drive_firstname,' ',dd.drive_lastname) AS driver_name
    FROM rental_order ro
    JOIN car ca      ON ca.car_id   = ro.rent_carid
    JOIN customer cu ON cu.cust_id  = ro.rent_custid
    LEFT JOIN payment p   ON p.pay_rentid  = ro.rent_id
    LEFT JOIN eticket et  ON et.tick_payid = p.pay_id
    LEFT JOIN driver_details dd ON dd.drive_id = ro.rent_driveid
    WHERE ca.car_provid = ?
    ORDER BY ro.rent_id DESC
    LIMIT 7
  ");
  $stmt->execute([$provId]);
  $recentOrders = $stmt->fetchAll();
} catch (Exception $e) { $recentOrders = []; }

// ── Monthly chart (last 6 months, this provider) ──────────────────────────────
try {
  $stmt = $pdo->prepare("
    SELECT DATE_FORMAT(ro.rent_dateissued,'%b') AS month,
           COUNT(*) AS total
    FROM rental_order ro
    JOIN car c ON c.car_id = ro.rent_carid
    WHERE c.car_provid = ?
      AND ro.rent_dateissued >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY MONTH(ro.rent_dateissued), DATE_FORMAT(ro.rent_dateissued,'%b')
    ORDER BY MONTH(ro.rent_dateissued)
  ");
  $stmt->execute([$provId]);
  $chart = $stmt->fetchAll();
  $chartLabels = array_column($chart, 'month');
  $chartValues = array_column($chart, 'total');
} catch (Exception $e) { $chartLabels = []; $chartValues = []; }

// ── Fleet availability ────────────────────────────────────────────────────────
try {
  $stmt = $pdo->prepare("SELECT car_model, car_type, car_capacity, car_rentalrate FROM car WHERE car_provid = ? ORDER BY car_id DESC LIMIT 5");
  $stmt->execute([$provId]);
  $fleetPreview = $stmt->fetchAll();
} catch (Exception $e) { $fleetPreview = []; }

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= htmlspecialchars($provName) ?> &mdash; <?= date('l, F j, Y') ?></p>
  </div>
  <a href="/Traveloka/ProviderDashboard/pages/fleet.php" class="btn-tv-orange">
    <i class="bi bi-plus-lg"></i> Add car
  </a>
</div>

<!-- Stat cards -->
<div class="stat-grid">
  <div class="stat-card orange">
    <div class="stat-icon"><i class="bi bi-car-front"></i></div>
    <div class="stat-value"><?= number_format($stats['cars']) ?></div>
    <div class="stat-label">Cars in fleet</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-clipboard2-check"></i></div>
    <div class="stat-value"><?= number_format($stats['orders']) ?></div>
    <div class="stat-label">Total orders</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
    <div class="stat-value"><?= number_format($stats['active']) ?></div>
    <div class="stat-label">Active rentals</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
    <div class="stat-value"><?= number_format($stats['pending']) ?></div>
    <div class="stat-label">Pending orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
    <div class="stat-value"><?= number_format($stats['drivers']) ?></div>
    <div class="stat-label">Assigned drivers</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
    <div class="stat-value" style="font-size:20px">₱<?= number_format($stats['revenue'], 0) ?></div>
    <div class="stat-label">Total revenue</div>
  </div>
</div>

<!-- Chart + fleet preview -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="content-card" style="margin-bottom:0">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Rental orders — last 6 months</h2>
      </div>
      <div class="card-body-tv">
        <canvas id="ordersChart" height="85"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="content-card" style="margin-bottom:0;height:100%">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Fleet preview</h2>
        <a href="/Traveloka/ProviderDashboard/pages/fleet.php" class="btn-tv-ghost" style="font-size:12px;padding:5px 12px">View all</a>
      </div>
      <?php if (empty($fleetPreview)): ?>
        <div class="empty-state"><i class="bi bi-car-front d-block"></i><p>No cars yet.</p></div>
      <?php else: ?>
        <div style="padding:8px 0">
          <?php foreach ($fleetPreview as $car): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:10px 22px;border-bottom:1px solid #F0F3F8">
            <div style="width:36px;height:36px;border-radius:8px;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="bi bi-car-front"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($car['car_model']) ?></div>
              <div style="font-size:11.5px;color:var(--text-secondary)"><?= htmlspecialchars($car['car_type']) ?> &middot; <?= $car['car_capacity'] ?> seats</div>
            </div>
            <div style="font-size:12.5px;font-weight:600;color:var(--tv-blue);flex-shrink:0">₱<?= number_format($car['car_rentalrate'],0) ?>/day</div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent orders table -->
<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Recent rental orders</h2>
    <a href="/Traveloka/ProviderDashboard/pages/orders.php" class="btn-tv-ghost">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (empty($recentOrders)): ?>
    <div class="empty-state"><i class="bi bi-clipboard2-x d-block"></i><p>No orders yet.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Order ID</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Dates</th><th>Driver</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $o['rent_id'] ?></strong></td>
          <td><?= htmlspecialchars($o['customer_name']) ?></td>
          <td>
            <?= htmlspecialchars($o['car_model']) ?>
            <span style="font-size:12px;color:var(--text-secondary);margin-left:4px"><?= htmlspecialchars($o['car_type']) ?></span>
          </td>
          <td style="font-size:12.5px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($o['rent_pickuplocation']) ?></td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($o['rent_dateissued']) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($o['rent_datedue']) ?></span>
          </td>
          <td>
            <?php if ($o['driver_name'] && trim($o['driver_name']) !== ' '): ?>
              <span class="badge-tv badge-driver"><?= htmlspecialchars($o['driver_name']) ?></span>
            <?php else: ?>
              <span class="badge-tv badge-nodriver">Self-drive</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
              $st  = $o['tick_status'] ?? 'Pending';
              $cls = match($st) { 'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel',default=>'badge-pending' };
            ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const labels = <?= json_encode($chartLabels ?: ['Jan','Feb','Mar','Apr','May','Jun']) ?>;
const values = <?= json_encode($chartValues ?: [0,0,0,0,0,0]) ?>;
new Chart(document.getElementById('ordersChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Orders',
      data: values,
      backgroundColor: '#FF6000',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: { backgroundColor:'#001A3C', titleFont:{family:'DM Sans',size:12}, bodyFont:{family:'DM Sans',size:13}, padding:10, cornerRadius:8 }
    },
    scales: {
      x: { grid:{display:false}, ticks:{font:{family:'DM Sans',size:12},color:'#637083'} },
      y: { grid:{color:'#F0F3F8'}, ticks:{font:{family:'DM Sans',size:12},color:'#637083',stepSize:1}, beginAtZero:true }
    }
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>