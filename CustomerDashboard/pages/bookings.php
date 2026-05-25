<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'My Bookings';
$activePage = 'bookings';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$custId = $_SESSION['customer_id'] ?? '';

$filter = $_GET['filter'] ?? 'all';

// Status filter map (tab key → Firestore bookingStatus)
$fsStatusMap = [
    'active'    => 'Ongoing',
    'pending'   => 'Upcoming',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$filters = [['field' => 'userId', 'op' => 'EQUAL', 'value' => $custId]];
$allBookings = fb()->query('bookings', $filters);
usort($allBookings, fn($a, $b) => intval($b['createdAt'] ?? 0) <=> intval($a['createdAt'] ?? 0));

// Count per status for tab badges
$counts = ['all' => count($allBookings)];
foreach ($allBookings as $b) {
    $s = $b['bookingStatus'] ?? 'Upcoming';
    $tabKey = array_search($s, $fsStatusMap);
    if ($tabKey) $counts[$tabKey] = ($counts[$tabKey] ?? 0) + 1;
}

// Apply filter
if ($filter !== 'all' && isset($fsStatusMap[$filter])) {
    $fsTarget = $fsStatusMap[$filter];
    $bookings = array_values(array_filter($allBookings, fn($b) => ($b['bookingStatus'] ?? '') === $fsTarget));
} else {
    $bookings = $allBookings;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div style="margin-bottom:24px">
  <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--text-primary);margin-bottom:4px">
    My Bookings
  </h1>
  <p style="font-size:13.5px;color:var(--text-secondary);margin:0">
    View and manage all your current and past rental journeys.
  </p>
</div>

<!-- Filter tabs -->
<div class="filter-tabs" style="margin-bottom:24px">
  <?php foreach (['all'=>'All','active'=>'Active','pending'=>'Upcoming','completed'=>'Completed','cancelled'=>'Cancelled'] as $val=>$label): ?>
  <a href="?filter=<?= $val ?>" class="filter-tab <?= $filter===$val?'active':'' ?>">
    <?= $label ?>
    <?php if (isset($counts[$val]) && $counts[$val] > 0): ?>
    <span style="background:<?= $filter===$val?'rgba(255,255,255,.3)':'var(--tv-blue-light)' ?>;color:<?= $filter===$val?'#fff':'var(--tv-blue)' ?>;padding:1px 7px;border-radius:99px;font-size:10.5px;font-weight:700">
      <?= $counts[$val] ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
<div class="content-card">
  <div class="empty-state">
    <i class="bi bi-calendar-x"></i>
    <h3>No bookings <?= $filter !== 'all' ? 'in this category' : 'yet' ?></h3>
    <p><?= $filter !== 'all' ? 'Try switching to "All" to see all your bookings.' : 'Find and book your perfect car to get started.' ?></p>
    <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-primary">
      <i class="bi bi-search"></i> Browse cars
    </a>
  </div>
</div>
<?php else: ?>

<!-- Booking cards -->
<div class="bookings-grid-visual">
  <?php foreach ($bookings as $b):
    $status      = $b['bookingStatus'] ?? 'Upcoming';
    $statusCls   = Firebase::statusBadge($status);
    $statusLabel = Firebase::statusLabel($status);
    $statusColor = match($status) {
        'Ongoing'   => '#16A34A',
        'Completed' => '#0064D2',
        'Cancelled' => '#DC2626',
        default     => '#D97706',
    };
    $driver = !empty($b['driverName']) ? $b['driverName'] : 'Self-drive';
    $days   = intval($b['totalDays'] ?? 1);
    $start  = Firebase::msToDate($b['startDateMs'] ?? 0);
    $end    = Firebase::msToDate($b['endDateMs']   ?? 0);
    $bookingId = $b['id'];
  ?>
  <div class="booking-card-v">
    <!-- Car image header -->
    <div class="booking-card-img">
      <i class="bi bi-car-front-fill booking-card-img-icon"></i>
      <div style="position:relative;z-index:1;text-align:center">
        <div class="booking-card-img-car"><?= htmlspecialchars($b['vehicleName'] ?? '') ?></div>
        <div class="booking-card-img-sub"><?= htmlspecialchars($b['vehicleCategory'] ?? '') ?> &middot; <?= htmlspecialchars($b['vendorName'] ?? '') ?></div>
      </div>
      <div class="booking-card-img-label" style="background:<?= $statusColor ?>;border-color:<?= $statusColor ?>">
        <?= htmlspecialchars($statusLabel) ?>
      </div>
      <?php if (!empty($b['totalPrice'])): ?>
      <div class="booking-card-img-amount">₱<?= number_format(floatval($b['totalPrice']), 0) ?></div>
      <?php endif; ?>
    </div>
    <!-- Flag notice -->
    <?php if (!empty($b['isFlagged'])): ?>
    <div style="background:#FEF3C7;border-left:4px solid #D97706;padding:10px 14px;font-size:12.5px;color:#78350F;display:flex;gap:8px;align-items:flex-start">
      <i class="bi bi-flag-fill" style="flex-shrink:0;margin-top:2px;color:#D97706"></i>
      <div>
        <strong>This booking has been flagged for review.</strong>
        <?php if (!empty($b['flagReason'])): ?>
          <div style="margin-top:3px;opacity:.85"><?= htmlspecialchars($b['flagReason']) ?></div>
        <?php endif; ?>
        <div style="margin-top:4px;font-size:11.5px;opacity:.7">Please contact support if you have questions.</div>
      </div>
    </div>
    <?php endif; ?>
    <!-- Details -->
    <div class="booking-card-v-body">
      <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:12px">
        <?= htmlspecialchars(substr($bookingId, 0, 8)) ?>… &middot; <?= intval($b['seatingCapacity'] ?? 0) ?> seats &middot; <?= $days ?> day<?= $days !== 1 ? 's' : '' ?>
      </div>
      <div class="booking-card-v-meta">
        <div>
          <div class="booking-card-v-meta-label"><i class="bi bi-calendar-check"></i> Pick-up</div>
          <div class="booking-card-v-meta-value"><?= htmlspecialchars($start) ?></div>
        </div>
        <div>
          <div class="booking-card-v-meta-label"><i class="bi bi-calendar-x"></i> Return</div>
          <div class="booking-card-v-meta-value"><?= htmlspecialchars($end) ?></div>
        </div>
        <div>
          <div class="booking-card-v-meta-label"><i class="bi bi-person-badge"></i> Driver</div>
          <div class="booking-card-v-meta-value"><?= htmlspecialchars($driver) ?></div>
        </div>
        <div>
          <div class="booking-card-v-meta-label"><i class="bi bi-cash"></i> Amount</div>
          <div class="booking-card-v-meta-value" style="color:var(--tv-blue)">
            <?= !empty($b['totalPrice']) ? '₱'.number_format(floatval($b['totalPrice']),2) : 'Unpaid' ?>
          </div>
        </div>
      </div>
    </div>
    <!-- Actions -->
    <div class="booking-card-v-footer">
      <?php if (!empty($b['qrCode'])): ?>
        <a href="/Traveloka/CustomerDashboard/pages/ticket.php?id=<?= htmlspecialchars($bookingId) ?>" class="btn-tv-primary" style="flex:1;justify-content:center">
          <i class="bi bi-ticket-perforated"></i> View Ticket
        </a>
      <?php else: ?>
        <a href="/Traveloka/CustomerDashboard/pages/payment.php?booking_id=<?= htmlspecialchars($bookingId) ?>" class="btn-tv-orange" style="flex:1;justify-content:center">
          <i class="bi bi-credit-card"></i> Pay Now
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
