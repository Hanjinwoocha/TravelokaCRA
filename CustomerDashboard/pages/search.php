<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Find a Car';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}

// Fetch vendors for provider filter
$vendors = fb()->query('vendors', [['field' => 'status', 'op' => 'EQUAL', 'value' => 'approved']]);

// Search filters
$filterType  = $_GET['type']     ?? '';
$filterProv  = $_GET['provider'] ?? '';
$filterMin   = $_GET['min']      ?? '';
$filterMax   = $_GET['max']      ?? '';
$filterSeats = $_GET['seats']    ?? '';
$filterCity  = trim($_GET['city'] ?? '');

// Fetch all active vehicles
$vehicles = fb()->query('vehicles', [['field' => 'isActive', 'op' => 'EQUAL', 'value' => true]]);

// Collect unique available cities from all vehicles (for the filter dropdown)
$allCities = [];
foreach ($vehicles as $v) {
    foreach ($v['availableCities'] ?? [] as $city) {
        $city = trim($city);
        if ($city) $allCities[$city] = true;
    }
}
ksort($allCities);
$allCities = array_keys($allCities);

// Apply filters in PHP (Firestore free tier has composite index limits)
if ($filterType)  $vehicles = array_filter($vehicles, fn($c) => ($c['category'] ?? '') === $filterType);
if ($filterProv)  $vehicles = array_filter($vehicles, fn($c) => ($c['vendorId'] ?? '') === $filterProv);
if ($filterMin)   $vehicles = array_filter($vehicles, fn($c) => floatval($c['pricePerDay'] ?? 0) >= floatval($filterMin));
if ($filterMax)   $vehicles = array_filter($vehicles, fn($c) => floatval($c['pricePerDay'] ?? 0) <= floatval($filterMax));
if ($filterSeats) $vehicles = array_filter($vehicles, fn($c) => intval($c['seatingCapacity'] ?? 0) >= intval($filterSeats));
if ($filterCity)  $vehicles = array_filter($vehicles, function($c) use ($filterCity) {
    foreach ($c['availableCities'] ?? [] as $city) {
        if (strtolower(trim($city)) === strtolower($filterCity)) return true;
    }
    return false;
});

// Sort by price asc
usort($vehicles, fn($a, $b) => floatval($a['pricePerDay'] ?? 0) <=> floatval($b['pricePerDay'] ?? 0));
$vehicles = array_values($vehicles);

include __DIR__ . '/../includes/header.php';
?>

<!-- Search top bar -->
<div class="search-bar-tv">
  <div class="search-bar-info">
    <div class="search-bar-label"><i class="bi bi-car-front"></i> Available vehicles</div>
    <div class="search-bar-value"><?= count($vehicles) ?> car<?= count($vehicles) !== 1 ? 's' : '' ?> found</div>
  </div>
  <?php if ($filterType || $filterProv || $filterMin || $filterMax || $filterSeats || $filterCity): ?>
  <div class="search-bar-divider"></div>
  <div class="search-bar-count">Filters active</div>
  <a href="/Traveloka/CustomerDashboard/pages/search.php"
     style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;flex-shrink:0">
    <i class="bi bi-x-circle"></i> Clear
  </a>
  <?php endif; ?>
</div>

<div class="row g-4">
  <!-- Filter sidebar -->
  <div class="col-lg-3">
    <div class="filter-sidebar">
      <div class="filter-sidebar-header">
        <span class="filter-sidebar-title"><i class="bi bi-sliders"></i> Filters</span>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" style="font-size:12px;color:var(--tv-blue);text-decoration:none;font-weight:600">Reset</a>
      </div>
      <div class="filter-sidebar-body">
        <form method="get">
          <div class="filter-group">
            <label class="filter-group-label">Car type</label>
            <select name="type" class="tv-select">
              <option value="">All types</option>
              <?php foreach (['Sedan','SUV','Van','MPV','Pickup','Hatchback'] as $t): ?>
              <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-group-label">Provider</label>
            <select name="provider" class="tv-select">
              <option value="">All providers</option>
              <?php foreach ($vendors as $v):
                $vid = $v['vendorId'] ?? $v['id'];
              ?>
              <option value="<?= htmlspecialchars($vid) ?>" <?= $filterProv===$vid?'selected':'' ?>>
                <?= htmlspecialchars($v['businessName'] ?? '') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-group-label">City / Location</label>
            <select name="city" class="tv-select">
              <option value="">All cities</option>
              <?php foreach ($allCities as $city): ?>
              <option value="<?= htmlspecialchars($city) ?>" <?= $filterCity===$city?'selected':'' ?>>
                <?= htmlspecialchars($city) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-group-label">Min. seats</label>
            <select name="seats" class="tv-select">
              <option value="">Any capacity</option>
              <option value="2" <?= $filterSeats=='2'?'selected':'' ?>>2+ seats</option>
              <option value="4" <?= $filterSeats=='4'?'selected':'' ?>>4+ seats</option>
              <option value="6" <?= $filterSeats=='6'?'selected':'' ?>>6+ seats</option>
              <option value="8" <?= $filterSeats=='8'?'selected':'' ?>>8+ seats</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-group-label">Rate per day (₱)</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="number" name="min" class="tv-input" placeholder="Min" value="<?= htmlspecialchars($filterMin) ?>" style="flex:1">
              <span style="color:var(--text-muted);font-size:12px;flex-shrink:0">–</span>
              <input type="number" name="max" class="tv-input" placeholder="Max" value="<?= htmlspecialchars($filterMax) ?>" style="flex:1">
            </div>
          </div>
          <button type="submit" class="btn-tv-primary" style="width:100%;justify-content:center">
            <i class="bi bi-funnel"></i> Apply filters
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Car results -->
  <div class="col-lg-9">
    <?php if (empty($vehicles)): ?>
    <div class="content-card">
      <div class="empty-state">
        <i class="bi bi-car-front"></i>
        <h3>No cars match your filters</h3>
        <p>Try adjusting your filters or clear them to see all available cars.</p>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-ghost">Clear all filters</a>
      </div>
    </div>
    <?php else: ?>
    <div class="car-grid">
      <?php foreach ($vehicles as $car):
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
          <div class="car-card-model"><?= htmlspecialchars($car['name'] ?? $car['model'] ?? '') ?></div>
          <div class="car-card-provider"><i class="bi bi-building"></i><?= htmlspecialchars($car['vendorName'] ?? '') ?></div>
          <?php $cities = $car['availableCities'] ?? []; if (!empty($cities)): ?>
          <div class="car-card-provider" style="margin-top:2px"><i class="bi bi-geo-alt"></i><?= htmlspecialchars(implode(', ', array_slice($cities, 0, 3))) ?><?= count($cities) > 3 ? ' +'.( count($cities)-3).' more' : '' ?></div>
          <?php endif; ?>
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
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
