<?php
require_once '../includes/db.php';
$pageTitle  = 'Active cities';
$activePage = 'locations';

// Derive active cities from approved vendors
$vendors = fb()->query('vendors', [['field' => 'status', 'op' => 'EQUAL', 'value' => 'approved']]);

// Count providers and collect vehicle counts per city
$allVehicles = fb()->listDocs('vehicles');
$vcountsByVendor = [];
foreach ($allVehicles as $v) {
    $vid = $v['vendorId'] ?? '';
    if ($vid) $vcountsByVendor[$vid] = ($vcountsByVendor[$vid] ?? 0) + 1;
}

// Group vendors by city — use coverageAreas array, fall back to locationName
$cities = [];
foreach ($vendors as $v) {
    $vid         = $v['vendorId'] ?? $v['id'];
    $provName    = $v['businessName'] ?? '—';
    $carCount    = $vcountsByVendor[$vid] ?? 0;
    $coverage    = $v['coverageAreas'] ?? [];
    if (empty($coverage) && !empty($v['locationName'])) {
        $coverage = [trim($v['locationName'])];
    }
    foreach ($coverage as $city) {
        $city = trim($city);
        if (!$city) continue;
        if (!isset($cities[$city])) {
            $cities[$city] = ['providers' => [], 'cars' => 0];
        }
        // Only add provider name once per city
        if (!in_array($provName, $cities[$city]['providers'])) {
            $cities[$city]['providers'][] = $provName;
            $cities[$city]['cars'] += $carCount;
        }
    }
}
ksort($cities);

$search = trim($_GET['q'] ?? '');
if ($search) {
    $sq = strtolower($search);
    $cities = array_filter($cities, fn($_, $k) => str_contains(strtolower($k), $sq), ARRAY_FILTER_USE_BOTH);
}

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Active cities</h1>
    <p class="page-subtitle"><?= count($cities) ?> cit<?= count($cities) !== 1 ? 'ies' : 'y' ?> with active providers</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Cities served</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search cities…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>

  <?php if (empty($cities)): ?>
    <div class="empty-state"><i class="bi bi-geo-alt d-block"></i><p>No cities yet. Cities appear here once providers register and are approved.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>City</th><th>Providers</th><th>Total cars</th><th>Provider names</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cities as $city => $data): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:8px;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="bi bi-geo-alt-fill"></i>
              </div>
              <strong><?= htmlspecialchars($city) ?></strong>
            </div>
          </td>
          <td><span class="badge-tv badge-active"><?= count($data['providers']) ?> provider<?= count($data['providers']) !== 1 ? 's' : '' ?></span></td>
          <td><strong style="color:var(--tv-blue)"><?= $data['cars'] ?> car<?= $data['cars'] !== 1 ? 's' : '' ?></strong></td>
          <td style="font-size:12.5px;color:var(--text-secondary)"><?= htmlspecialchars(implode(', ', $data['providers'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="content-card" style="margin-top:0">
  <div class="card-body-tv" style="padding:14px 20px">
    <p style="font-size:12.5px;color:var(--text-secondary);margin:0">
      <i class="bi bi-info-circle" style="color:var(--tv-blue);margin-right:6px"></i>
      Cities are automatically derived from approved providers' operating cities. A provider with multiple cities appears under each one.
    </p>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
