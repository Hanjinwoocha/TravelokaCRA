<?php
require_once 'includes/db.php';
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// ── Summary counts ────────────────────────────────────────────────────────────
$allVendors   = fb()->query('vendors',   []);
$allVehicles  = fb()->query('vehicles',  []);
$allUsers     = fb()->query('users',     [['field' => 'role', 'op' => 'EQUAL', 'value' => 'customer']]);
$allDrivers   = fb()->query('drivers',   []);
$allBookings  = fb()->query('bookings',  []);

$counts = [
    'providers'         => count($allVendors),
    'cars'              => count($allVehicles),
    'customers'         => count($allUsers),
    'drivers'           => count($allDrivers),
    'rentals'           => count($allBookings),
    'payments'          => count(array_filter($allBookings, fn($b) => !empty($b['qrCode']))),
    'tickets'           => count(array_filter($allBookings, fn($b) => !empty($b['qrCode']))),
    'active'            => count(array_filter($allBookings, fn($b) => ($b['bookingStatus'] ?? '') === 'Ongoing')),
    'pending_providers' => count(array_filter($allVendors,  fn($v) => ($v['status'] ?? '') === 'pending')),
];

// ── Recent bookings (last 8, paid) ───────────────────────────────────────────
$recentOrders = array_slice(
    array_filter($allBookings, fn($b) => !empty($b['qrCode'])),
    0, 8
);

// ── Monthly chart (last 6 months) ────────────────────────────────────────────
$chartMap = [];
$sixMonthsAgo = strtotime('-6 months') * 1000;
foreach ($allBookings as $b) {
    $ms = intval($b['startDateMs'] ?? 0);
    if ($ms < $sixMonthsAgo) continue;
    $month = date('M', intdiv($ms, 1000));
    $chartMap[$month] = ($chartMap[$month] ?? 0) + 1;
}
$chartLabels = array_keys($chartMap);
$chartValues = array_values($chartMap);

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
        <a href="pages/providers.php" class="btn-tv-primary w-100 justify-content-center">
          <i class="bi bi-plus-lg"></i> Add car provider
        </a>
        <a href="pages/cars.php" class="btn-tv-orange w-100 justify-content-center" style="border-radius:6px;padding:8px 18px;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none">
          <i class="bi bi-plus-lg"></i> Add car
        </a>
        <a href="pages/drivers.php" class="btn-tv-ghost w-100 justify-content-center">
          <i class="bi bi-plus-lg"></i> Add driver
        </a>
        <a href="pages/locations.php" class="btn-tv-ghost w-100 justify-content-center">
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
        <tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Pickup date</th><th>Return date</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $r):
          $status   = $r['bookingStatus'] ?? 'Upcoming';
          $badgeCls = Firebase::statusBadge($status);
          $start    = Firebase::msToDate($r['startDateMs'] ?? 0);
          $end      = Firebase::msToDate($r['endDateMs']   ?? 0);
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue);font-family:monospace;font-size:12px"><?= htmlspecialchars(substr($r['id'], 0, 8)) ?>…</strong></td>
          <td><?= htmlspecialchars($r['renterName'] ?? '—') ?></td>
          <td>
            <?= htmlspecialchars($r['vehicleName'] ?? '—') ?>
            <span style="font-size:12px;color:var(--text-secondary);margin-left:4px"><?= htmlspecialchars($r['vehicleCategory'] ?? '') ?></span>
          </td>
          <td><?= htmlspecialchars($start) ?></td>
          <td><?= htmlspecialchars($end) ?></td>
          <td><span class="badge-tv <?= $badgeCls ?>"><?= htmlspecialchars(Firebase::statusLabel($status)) ?></span></td>
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
