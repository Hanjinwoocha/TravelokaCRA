<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle  = 'Dashboard';
$activePage = 'home';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in'])) {
    header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit;
}
if (empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$custId   = $_SESSION['customer_id']   ?? '';
$custName = $_SESSION['customer_name'] ?? '';

// ── All bookings for this customer ────────────────────────────────────────────
$myBookings = fb()->query('bookings', [['field' => 'userId', 'op' => 'EQUAL', 'value' => $custId]]);

$totalOrders  = count($myBookings);
$activeOrders = count(array_filter($myBookings, fn($b) => ($b['bookingStatus'] ?? '') === 'Ongoing'));
$totalSpent   = array_sum(array_map(fn($b) => floatval($b['totalPrice'] ?? 0), array_filter($myBookings, fn($b) => !empty($b['qrCode']))));

// ── Spotlight booking (Ongoing first, then Upcoming) ─────────────────────────
$activeBooking   = null;
$upcomingBooking = null;
foreach ($myBookings as $b) {
    $st = $b['bookingStatus'] ?? '';
    if ($st === 'Ongoing'  && !$activeBooking)   $activeBooking   = $b;
    if ($st === 'Upcoming' && !$upcomingBooking)  $upcomingBooking = $b;
}
$spotlightBooking = $activeBooking ?? $upcomingBooking;

// ── Recommended cars ──────────────────────────────────────────────────────────
$recommended = fb()->query('vehicles', [['field' => 'isActive', 'op' => 'EQUAL', 'value' => true]]);
usort($recommended, fn($a, $b) => floatval($a['pricePerDay'] ?? 0) <=> floatval($b['pricePerDay'] ?? 0));
$recommended = array_slice($recommended, 0, 6);

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome bar -->
<div class="welcome-bar">
  <div class="welcome-eyebrow"><i class="bi bi-person-check-fill"></i> Customer Dashboard</div>
  <h1 class="welcome-title">Hello, <span><?= htmlspecialchars($custName) ?></span>!</h1>
  <p class="welcome-sub">Ready for your next journey? Manage your rentals and book your next trip.</p>
  <div class="welcome-stats">
    <a href="/Traveloka/CustomerDashboard/pages/bookings.php" class="stat-card" style="text-decoration:none">
      <div class="stat-card-num"><?= $totalOrders ?></div>
      <div class="stat-card-lbl">Total bookings</div>
    </a>
    <a href="/Traveloka/CustomerDashboard/pages/bookings.php?filter=active" class="stat-card" style="text-decoration:none">
      <div class="stat-card-num orange"><?= $activeOrders ?></div>
      <div class="stat-card-lbl">Active rentals</div>
    </a>
    <div class="stat-card">
      <div class="stat-card-num">₱<?= number_format($totalSpent, 0) ?></div>
      <div class="stat-card-lbl">Total spent</div>
    </div>
    <div style="margin-left:auto;position:relative;z-index:1;display:flex;align-items:center">
      <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-orange">
        <i class="bi bi-plus-lg"></i> Book a car
      </a>
    </div>
  </div>
</div>

<!-- Quick car search -->
<div class="content-card" style="margin-bottom:28px">
  <div class="card-body-tv" style="padding:20px 24px">
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:14px">
      <i class="bi bi-search" style="color:var(--tv-blue)"></i> Quick car search
    </div>
    <form method="get" action="/Traveloka/CustomerDashboard/pages/search.php">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label-tv">Car type</label>
          <select name="type" class="tv-select">
            <option value="">Any type</option>
            <?php foreach (['Sedan','SUV','Van','MPV','Pickup','Hatchback'] as $t): ?>
            <option value="<?= $t ?>"><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label-tv">Min. seats</label>
          <select name="seats" class="tv-select">
            <option value="">Any capacity</option>
            <option value="2">2+ seats</option>
            <option value="4">4+ seats</option>
            <option value="6">6+ seats</option>
            <option value="8">8+ seats</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label-tv">Max rate / day (₱)</label>
          <input type="number" name="max" class="tv-input" placeholder="e.g. 3000">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn-tv-primary" style="width:100%;justify-content:center;height:44px">
            <i class="bi bi-search"></i> Search Cars
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Active / upcoming booking spotlight -->
<div class="section-header">
  <h2 class="section-title">
    <?= $activeBooking ? 'Active Booking' : ($upcomingBooking ? 'Upcoming Booking' : 'Recent Activity') ?>
  </h2>
  <a href="/Traveloka/CustomerDashboard/pages/bookings.php" class="section-link">View all <i class="bi bi-arrow-right"></i></a>
</div>

<?php if ($spotlightBooking):
  $sb     = $spotlightBooking;
  $sbStatus = $sb['bookingStatus'] ?? 'Upcoming';
  $sbDriver = !empty($sb['driverName']) ? $sb['driverName'] : 'Self-drive';
  $days   = intval($sb['totalDays'] ?? 1);
  $start  = Firebase::msToDate($sb['startDateMs'] ?? 0);
  $end    = Firebase::msToDate($sb['endDateMs']   ?? 0);
?>
<div class="spotlight-card">
  <div class="spotlight-img">
    <?php $sbImg = $sb['vehicleImageUrl'] ?? ''; ?>
    <?php if ($sbImg): ?>
      <img src="<?= htmlspecialchars($sbImg) ?>" alt=""
           style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit">
    <?php else: ?>
      <i class="bi bi-car-front-fill spotlight-img-bg"></i>
    <?php endif; ?>
    <div class="spotlight-status-pill <?= $sbStatus === 'Ongoing' ? 'active' : 'pending' ?>">
      <?= $sbStatus === 'Ongoing' ? '<i class="bi bi-check-circle-fill"></i> Active' : '<i class="bi bi-clock"></i> Upcoming' ?>
    </div>
  </div>
  <div class="spotlight-body">
    <div class="spotlight-header-row">
      <div>
        <div class="spotlight-title"><?= htmlspecialchars($sb['vehicleName'] ?? '') ?></div>
        <div class="spotlight-subtitle"><?= htmlspecialchars($sb['vehicleCategory'] ?? '') ?> &middot; <?= htmlspecialchars($sb['vendorName'] ?? '') ?></div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px">Booking <?= htmlspecialchars(substr($sb['id'], 0, 8)) ?>…</div>
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--tv-blue)">
          ₱<?= number_format(floatval($sb['totalPrice'] ?? 0), 2) ?>
        </div>
        <div style="font-size:11.5px;color:var(--text-muted)">Total price</div>
      </div>
    </div>

    <div class="spotlight-meta">
      <div class="spotlight-meta-item">
        <div class="spotlight-meta-label"><i class="bi bi-geo-alt"></i> Pick-up</div>
        <div class="spotlight-meta-value"><?= htmlspecialchars($sb['pickupLocation'] ?? '—') ?></div>
        <div class="spotlight-meta-sub"><?= htmlspecialchars($start) ?></div>
      </div>
      <div class="spotlight-meta-arrow"><i class="bi bi-arrow-right"></i></div>
      <div class="spotlight-meta-item">
        <div class="spotlight-meta-label"><i class="bi bi-geo"></i> Drop-off</div>
        <div class="spotlight-meta-value"><?= htmlspecialchars($sb['returnLocation'] ?? '—') ?></div>
        <div class="spotlight-meta-sub"><?= htmlspecialchars($end) ?></div>
      </div>
      <div class="spotlight-meta-item">
        <div class="spotlight-meta-label"><i class="bi bi-person-badge"></i> Driver</div>
        <div class="spotlight-meta-value"><?= htmlspecialchars($sbDriver) ?></div>
        <div class="spotlight-meta-sub"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?> rental</div>
      </div>
    </div>

    <div class="spotlight-actions">
      <?php if (!empty($sb['qrCode'])): ?>
      <a href="/Traveloka/CustomerDashboard/pages/ticket.php?id=<?= htmlspecialchars($sb['id']) ?>" class="btn-tv-ghost">
        <i class="bi bi-ticket-perforated"></i> View Ticket
      </a>
      <?php else: ?>
      <a href="/Traveloka/CustomerDashboard/pages/payment.php?booking_id=<?= htmlspecialchars($sb['id']) ?>" class="btn-tv-orange">
        <i class="bi bi-credit-card"></i> Complete Payment
      </a>
      <?php endif; ?>
      <a href="/Traveloka/CustomerDashboard/pages/bookings.php" class="btn-tv-primary">
        <i class="bi bi-grid-1x2"></i> All Bookings
      </a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="content-card">
  <div class="empty-state">
    <i class="bi bi-calendar-plus"></i>
    <h3>No active bookings</h3>
    <p>You don't have any active rentals right now. Browse available cars and book your next trip!</p>
    <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-primary">
      <i class="bi bi-search"></i> Browse cars
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Recommended cars -->
<?php if (!empty($recommended)): ?>
<div class="section-header" style="margin-top:32px">
  <h2 class="section-title">Available Cars</h2>
  <a href="/Traveloka/CustomerDashboard/pages/search.php" class="section-link">Browse all <i class="bi bi-arrow-right"></i></a>
</div>
<div class="car-grid">
  <?php foreach ($recommended as $car):
    $carId      = $car['vehicleId'] ?? $car['id'];
    $withDriver = !empty($car['withDriver']);
  ?>
  <?php $carImg = ($car['imageUrls'] ?? [])[0] ?? ''; ?>
  <div class="car-card">
    <div class="car-card-img" style="<?= $carImg ? 'background:#000;' : '' ?>">
      <?php if ($carImg): ?>
        <img src="<?= htmlspecialchars($carImg) ?>" alt="<?= htmlspecialchars($car['name'] ?? '') ?>"
             style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block">
      <?php else: ?>
        <i class="bi bi-car-front-fill car-card-img-icon"></i>
        <i class="bi bi-car-front car-card-img-main"></i>
      <?php endif; ?>
      <span class="car-card-badge"><?= htmlspecialchars($car['category'] ?? '') ?></span>
      <?php if ($withDriver): ?>
      <span class="car-card-driver-badge"><i class="bi bi-person-badge"></i> Driver</span>
      <?php endif; ?>
    </div>
    <div class="car-card-body">
      <div class="car-card-model"><?= htmlspecialchars($car['name'] ?? '') ?></div>
      <div class="car-card-provider"><i class="bi bi-building"></i><?= htmlspecialchars($car['vendorName'] ?? '') ?></div>
      <div class="car-card-specs">
        <div class="car-spec"><i class="bi bi-people"></i> <?= intval($car['seatingCapacity'] ?? 0) ?> seats</div>
        <div class="car-spec"><i class="bi bi-luggage"></i> <?= number_format(floatval($car['baggageLoad'] ?? 0),0) ?> kg</div>
        <?php if ($withDriver): ?>
        <div class="car-spec"><i class="bi bi-person-check"></i> Driver incl.</div>
        <?php else: ?>
        <div class="car-spec"><i class="bi bi-key"></i> Self-drive</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="car-card-footer">
      <div>
        <div class="car-rate">₱<?= number_format(floatval($car['pricePerDay'] ?? 0),2) ?></div>
        <div class="car-rate-per">per day</div>
      </div>
      <a href="/Traveloka/CustomerDashboard/pages/book.php?car_id=<?= htmlspecialchars($carId) ?>" class="btn-tv-orange btn-sm">
        Book now <i class="bi bi-arrow-right"></i>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
