<?php
require_once 'includes/db.php';
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = [];
foreach ([
  'providers' => 'SELECT COUNT(*) FROM car_provider',
  'cars'       => 'SELECT COUNT(*) FROM car',
  'customers'  => 'SELECT COUNT(*) FROM customer',
  'drivers'    => 'SELECT COUNT(*) FROM driver_details',
  'rentals'    => 'SELECT COUNT(*) FROM rental_order',
  'payments'   => 'SELECT COUNT(*) FROM payment',
  'tickets'    => 'SELECT COUNT(*) FROM eticket',
  'active'     => "SELECT COUNT(*) FROM eticket WHERE tick_status = 'Active'",
  'pending_providers' => "SELECT COUNT(*) FROM car_provider WHERE prov_status = 'pending'",
] as $key => $sql) {
  try { $counts[$key] = $pdo->query($sql)->fetchColumn(); }
  catch (Exception $e) { $counts[$key] = 0; }
}

// ── Recent rental orders ──────────────────────────────────────────────────────
try {
  $recentOrders = $pdo->query("
    SELECT ro.rent_id, ro.rent_dateissued, ro.rent_datedue,
           CONCAT(c.cust_firstname,' ',c.cust_lastname) AS customer_name,
           car.car_model, car.car_type,
           et.tick_status
    FROM   rental_order ro
    JOIN   customer c   ON ro.rent_custid = c.cust_id
    JOIN   car          ON ro.rent_carid  = car.car_id
    LEFT JOIN payment p ON p.pay_rentid   = ro.rent_id
    LEFT JOIN eticket et ON et.tick_payid = p.pay_id
    ORDER BY ro.rent_id DESC
    LIMIT 8
  ")->fetchAll();
} catch (Exception $e) { $recentOrders = []; }

// ── Monthly rentals for chart (last 6 months) ─────────────────────────────────
try {
  $chartData = $pdo->query("
    SELECT DATE_FORMAT(rent_dateissued,'%b') AS month,
           COUNT(*) AS total
    FROM rental_order
    WHERE rent_dateissued >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY MONTH(rent_dateissued), DATE_FORMAT(rent_dateissued,'%b')
    ORDER BY MONTH(rent_dateissued)
  ")->fetchAll();
  $chartLabels = array_column($chartData, 'month');
  $chartValues = array_column($chartData, 'total');
} catch (Exception $e) { $chartLabels = []; $chartValues = []; }

include 'includes/header.php';
?>

<!-- Page header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, Administrator &mdash; <?= date('l, F j, Y') ?></p>
  </div>
</div>

<!-- Stat grid -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-building"></i></div>
    <div class="stat-value"><?= number_format($counts['providers']) ?></div>
    <div class="stat-label">Car providers</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i class="bi bi-car-front"></i></div>
    <div class="stat-value"><?= number_format($counts['cars']) ?></div>
    <div class="stat-label">Listed cars</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="bi bi-people"></i></div>
    <div class="stat-value"><?= number_format($counts['customers']) ?></div>
    <div class="stat-label">Registered customers</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon"><i class="bi bi-clipboard2-check"></i></div>
    <div class="stat-value"><?= number_format($counts['rentals']) ?></div>
    <div class="stat-label">Total rental orders</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="bi bi-credit-card"></i></div>
    <div class="stat-value"><?= number_format($counts['payments']) ?></div>
    <div class="stat-label">Payments processed</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
    <div class="stat-value"><?= number_format($counts['active']) ?></div>
    <div class="stat-label">Active e-tickets</div>
  </div>
  <?php if ($counts['pending_providers'] > 0): ?>
  <div class="stat-card orange" style="cursor:pointer" onclick="location.href='/Traveloka/AdminDashboard/pages/providers.php?tab=applications'">
    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
    <div class="stat-value"><?= number_format($counts['pending_providers']) ?></div>
    <div class="stat-label">Pending applications</div>
  </div>
  <?php endif; ?>
</div>

<!-- Chart + quick links row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="content-card" style="margin-bottom:0">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Rental orders — last 6 months</h2>
      </div>
      <div class="card-body-tv">
        <canvas id="rentalsChart" height="80"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="content-card" style="margin-bottom:0; height:100%">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Quick actions</h2>
      </div>
      <div class="card-body-tv d-flex flex-column gap-2">
        <a href="pages/providers.php?action=add" class="btn-tv-primary w-100 justify-content-center">
          <i class="bi bi-plus-lg"></i> Add car provider
        </a>
        <a href="pages/cars.php?action=add" class="btn-tv-orange w-100 justify-content-center" style="border-radius:6px; padding:8px 18px; font-size:13.5px; font-weight:600; display:flex; align-items:center; gap:6px; justify-content:center; text-decoration:none;">
          <i class="bi bi-plus-lg"></i> Add car
        </a>
        <a href="pages/drivers.php?action=add" class="btn-tv-ghost w-100 justify-content-center">
          <i class="bi bi-plus-lg"></i> Add driver
        </a>
        <a href="pages/locations.php?action=add" class="btn-tv-ghost w-100 justify-content-center">
          <i class="bi bi-plus-lg"></i> Add location
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Recent orders table -->
<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Recent rental orders</h2>
    <a href="pages/rentals.php" class="btn-tv-ghost">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (empty($recentOrders)): ?>
    <div class="empty-state">
      <i class="bi bi-clipboard2-x d-block"></i>
      <p>No rental orders yet.</p>
    </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Date issued</th>
          <th>Due date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $r): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= htmlspecialchars($r['rent_id']) ?></strong></td>
          <td><?= htmlspecialchars($r['customer_name']) ?></td>
          <td>
            <?= htmlspecialchars($r['car_model']) ?>
            <span style="font-size:12px;color:var(--text-secondary);margin-left:4px"><?= htmlspecialchars($r['car_type']) ?></span>
          </td>
          <td><?= htmlspecialchars($r['rent_dateissued']) ?></td>
          <td><?= htmlspecialchars($r['rent_datedue']) ?></td>
          <td>
            <?php
              $status = $r['tick_status'] ?? 'Pending';
              $cls = match($status) {
                'Active'    => 'badge-active',
                'Completed' => 'badge-complete',
                'Cancelled' => 'badge-cancel',
                default     => 'badge-pending',
              };
            ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($status) ?></span>
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
const ctx = document.getElementById('rentalsChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Rental orders',
      data: values,
      backgroundColor: '#0064D2',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#001A3C',
        titleFont: { family: 'DM Sans', size: 12 },
        bodyFont:  { family: 'DM Sans', size: 13 },
        padding: 10,
        cornerRadius: 8,
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 12 }, color: '#637083' } },
      y: { grid: { color: '#F0F3F8' }, ticks: { font: { family: 'DM Sans', size: 12 }, color: '#637083', stepSize: 1 }, beginAtZero: true }
    }
  }
});
</script>

<?php include 'includes/footer.php'; ?>