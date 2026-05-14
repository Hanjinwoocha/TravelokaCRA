<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Find a Car';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$custId = $_SESSION['customer_id'] ?? 0;

// Fetch locations for filter
try { $locations = $pdo->query("SELECT * FROM location ORDER BY loctn_name")->fetchAll(); }
catch (Exception $e) { $locations = []; }

// Search filters
$filterType  = $_GET['type']     ?? '';
$filterProv  = $_GET['provider'] ?? '';
$filterMin   = $_GET['min']      ?? '';
$filterMax   = $_GET['max']      ?? '';
$filterSeats = $_GET['seats']    ?? '';

// Fetch providers for filter
try { $providers = $pdo->query("SELECT prov_id, prov_name FROM car_provider ORDER BY prov_name")->fetchAll(); }
catch (Exception $e) { $providers = []; }

// Build query
try {
  $where = ['1=1'];
  $params = [];
  if ($filterType)  { $where[] = 'c.car_type = ?';       $params[] = $filterType; }
  if ($filterProv)  { $where[] = 'c.car_provid = ?';     $params[] = $filterProv; }
  if ($filterMin)   { $where[] = 'c.car_rentalrate >= ?'; $params[] = floatval($filterMin); }
  if ($filterMax)   { $where[] = 'c.car_rentalrate <= ?'; $params[] = floatval($filterMax); }
  if ($filterSeats) { $where[] = 'c.car_capacity >= ?';   $params[] = intval($filterSeats); }
  $wc = implode(' AND ', $where);

  $stmt = $pdo->prepare("
    SELECT c.*, cp.prov_name, cp.prov_withdriver
    FROM car c
    JOIN car_provider cp ON cp.prov_id = c.car_provid
    WHERE $wc
    ORDER BY c.car_rentalrate ASC
  ");
  $stmt->execute($params);
  $cars = $stmt->fetchAll();
} catch (Exception $e) { $cars = []; }

include __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div class="section-header" style="margin-bottom:24px">
  <div>
    <h1 class="section-title" style="font-size:22px">Find your car</h1>
    <p style="font-size:13.5px;color:var(--text-secondary);margin-top:4px">
      <?= count($cars) ?> car<?= count($cars) !== 1 ? 's' : '' ?> available
    </p>
  </div>
</div>

<div class="row g-4">
  <!-- Filter sidebar -->
  <div class="col-lg-3">
    <div class="content-card" style="position:sticky;top:80px">
      <div class="card-header-tv">
        <h2 class="card-title-tv" style="font-size:14px">Filters</h2>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" style="font-size:12px;color:var(--tv-blue);text-decoration:none">Reset</a>
      </div>
      <div class="card-body-tv">
        <form method="get">
          <div class="mb-3">
            <label class="form-label-tv">Car type</label>
            <select name="type" class="tv-select">
              <option value="">All types</option>
              <?php foreach (['Sedan','SUV','Van','MPV','Pickup','Hatchback'] as $t): ?>
              <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label-tv">Provider</label>
            <select name="provider" class="tv-select">
              <option value="">All providers</option>
              <?php foreach ($providers as $p): ?>
              <option value="<?= $p['prov_id'] ?>" <?= $filterProv==$p['prov_id']?'selected':'' ?>><?= htmlspecialchars($p['prov_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label-tv">Min seats</label>
            <select name="seats" class="tv-select">
              <option value="">Any</option>
              <option value="2"  <?= $filterSeats=='2' ?'selected':'' ?>>2+</option>
              <option value="4"  <?= $filterSeats=='4' ?'selected':'' ?>>4+</option>
              <option value="6"  <?= $filterSeats=='6' ?'selected':'' ?>>6+</option>
              <option value="8"  <?= $filterSeats=='8' ?'selected':'' ?>>8+</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label-tv">Rate/day (₱)</label>
            <div style="display:flex;gap:8px">
              <input type="number" name="min" class="tv-input" placeholder="Min" value="<?= htmlspecialchars($filterMin) ?>" style="width:50%">
              <input type="number" name="max" class="tv-input" placeholder="Max" value="<?= htmlspecialchars($filterMax) ?>" style="width:50%">
            </div>
          </div>
          <button type="submit" class="btn-tv-primary w-100 justify-content-center">
            <i class="bi bi-funnel"></i> Apply filters
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Car grid -->
  <div class="col-lg-9">
    <?php if (empty($cars)): ?>
    <div class="empty-state">
      <i class="bi bi-car-front"></i>
      <h3>No cars found</h3>
      <p>Try adjusting your filters.</p>
      <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-ghost">Clear filters</a>
    </div>
    <?php else: ?>
    <div class="car-grid">
      <?php foreach ($cars as $car): ?>
      <div class="car-card">
        <div class="car-card-img">
          <i class="bi bi-car-front-fill"></i>
          <span class="badge-tv badge-complete car-card-type-badge"><?= htmlspecialchars($car['car_type']) ?></span>
          <?php if ($car['prov_withdriver']): ?>
          <span class="badge-tv" style="background:#EDE9FE;color:#5B21B6;position:absolute;bottom:10px;left:12px;font-size:10px">With driver</span>
          <?php endif; ?>
        </div>
        <div class="car-card-body">
          <div class="car-card-model"><?= htmlspecialchars($car['car_model']) ?></div>
          <div class="car-card-provider"><i class="bi bi-building" style="font-size:11px"></i> <?= htmlspecialchars($car['prov_name']) ?></div>
          <div class="car-card-specs">
            <div class="car-spec"><i class="bi bi-people"></i><?= $car['car_capacity'] ?> seats</div>
            <div class="car-spec"><i class="bi bi-luggage"></i><?= number_format($car['car_baggageload'],0) ?>kg</div>
          </div>
        </div>
        <div class="car-card-footer">
          <div>
            <div class="car-rate">₱<?= number_format($car['car_rentalrate'],2) ?></div>
            <div class="car-rate-label">per day</div>
          </div>
          <a href="/Traveloka/CustomerDashboard/pages/book.php?car_id=<?= $car['car_id'] ?>" class="btn-tv-primary" style="padding:8px 16px;font-size:13px">
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