<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in']) || $_SESSION['provider_logged_in'] !== true) {
    header('Location: /Traveloka/index.php'); exit;
}
$provId   = $_SESSION['provider_id']   ?? '';
$provName = $_SESSION['provider_name'] ?? 'Provider';

// ── Stats (scoped to this provider) ──────────────────────────────────────────
$myVehicles = fb()->query('vehicles', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);
$myBookings = fb()->query('bookings', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);
$paidBookings = array_filter($myBookings, fn($b) => !empty($b['qrCode']));

$stats = [
    'cars'    => count($myVehicles),
    'drivers' => count(fb()->query('drivers', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]])),
    'orders'  => count($paidBookings),
    'active'  => count(array_filter($paidBookings, fn($b) => ($b['bookingStatus'] ?? '') === 'Ongoing')),
    'revenue' => array_sum(array_map(fn($b) => floatval($b['totalPrice'] ?? 0), $paidBookings)),
    'pending' => count(array_filter($paidBookings, fn($b) => ($b['bookingStatus'] ?? '') === 'Upcoming')),
];

// ── Recent orders (last 7 paid bookings) ─────────────────────────────────────
$recentOrders = array_slice(array_values($paidBookings), 0, 7);

// ── Monthly chart (last 6 months) ────────────────────────────────────────────
$chartMap     = [];
$sixMonthsAgo = strtotime('-6 months') * 1000;
foreach ($paidBookings as $b) {
    $ms = intval($b['startDateMs'] ?? 0);
    if ($ms < $sixMonthsAgo) continue;
    $month = date('M', intdiv($ms, 1000));
    $chartMap[$month] = ($chartMap[$month] ?? 0) + 1;
}
$chartLabels = array_keys($chartMap);
$chartValues = array_values($chartMap);

// ── Fleet preview (first 5 vehicles) ─────────────────────────────────────────
$fleetPreview = array_slice($myVehicles, 0, 5);

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
    <div class="stat-label">Registered drivers</div>
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
          <?php $fThumb = ($car['imageUrls'] ?? [])[0] ?? ''; ?>
          <div style="display:flex;align-items:center;gap:12px;padding:10px 22px;border-bottom:1px solid #F0F3F8">
            <?php if ($fThumb): ?>
              <img src="<?= htmlspecialchars($fThumb) ?>" alt=""
                   style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid var(--border)">
            <?php else: ?>
            <div style="width:36px;height:36px;border-radius:8px;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="bi bi-car-front"></i>
            </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($car['name'] ?? '') ?></div>
              <div style="font-size:11.5px;color:var(--text-secondary)"><?= htmlspecialchars($car['category'] ?? '') ?> &middot; <?= intval($car['seatingCapacity'] ?? 0) ?> seats</div>
            </div>
            <div style="font-size:12.5px;font-weight:600;color:var(--tv-blue);flex-shrink:0">₱<?= number_format(floatval($car['pricePerDay'] ?? 0),0) ?>/day</div>
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
        <tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Dates</th><th>Driver</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $o):
          $status   = $o['bookingStatus'] ?? 'Upcoming';
          $badgeCls = Firebase::statusBadge($status);
          $start    = Firebase::msToDate($o['startDateMs'] ?? 0);
          $end      = Firebase::msToDate($o['endDateMs']   ?? 0);
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue);font-family:monospace;font-size:12px"><?= htmlspecialchars(substr($o['id'],0,8)) ?>…</strong></td>
          <td><?= htmlspecialchars($o['renterName'] ?? '—') ?></td>
          <td>
            <?= htmlspecialchars($o['vehicleName'] ?? '—') ?>
            <span style="font-size:12px;color:var(--text-secondary);margin-left:4px"><?= htmlspecialchars($o['vehicleCategory'] ?? '') ?></span>
          </td>
          <td style="font-size:12.5px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($o['pickupLocation'] ?? '—') ?></td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($start) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($end) ?></span>
          </td>
          <td>
            <?php if (!empty($o['driverName'])): ?>
              <span class="badge-tv badge-driver"><?= htmlspecialchars($o['driverName']) ?></span>
            <?php else: ?>
              <span class="badge-tv badge-nodriver">Self-drive</span>
            <?php endif; ?>
          </td>
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
