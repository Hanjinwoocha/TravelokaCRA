<?php
require_once '../includes/db.php';
$pageTitle  = 'Cars';
$activePage = 'cars';
$msg = '';

// Admin view-only: cars are managed exclusively by their providers.

$search   = trim($_GET['q'] ?? '');
$vehicles = fb()->listDocs('vehicles');
if ($search) {
    $sq = strtolower($search);
    $vehicles = array_filter($vehicles, fn($c) =>
        str_contains(strtolower($c['name'] ?? ''), $sq) ||
        str_contains(strtolower($c['category'] ?? ''), $sq) ||
        str_contains(strtolower($c['vendorName'] ?? ''), $sq)
    );
}
$vehicles = array_values($vehicles);

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Cars</h1>
    <p class="page-subtitle"><?= count($vehicles) ?> car<?= count($vehicles) !== 1 ? 's' : '' ?> in fleet</p>
  </div>
</div>

<?php if ($msg): [$type,$text] = explode(':', $msg, 2); ?>
<div class="alert-tv <?= $type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px">
  <i class="bi bi-<?= $type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
  <?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All cars</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search cars…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($vehicles)): ?>
    <div class="empty-state"><i class="bi bi-car-front d-block"></i><p>No cars found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Model</th><th>Type</th><th>Seats</th><th>Baggage (kg)</th><th>Rate/day</th><th>Provider</th></tr>
      </thead>
      <tbody>
        <?php foreach ($vehicles as $c):
          $thumb = ($c['imageUrls'] ?? [])[0] ?? '';
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                     style="width:48px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0;border:1px solid var(--border)">
              <?php else: ?>
                <div style="width:48px;height:36px;border-radius:6px;background:#F1F5F9;color:#94A3B8;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <i class="bi bi-car-front"></i>
                </div>
              <?php endif; ?>
              <strong><?= htmlspecialchars($c['name'] ?? $c['model'] ?? '') ?></strong>
            </div>
          </td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($c['category'] ?? '') ?></span></td>
          <td><?= intval($c['seatingCapacity'] ?? 0) ?> seats</td>
          <td><?= number_format(floatval($c['baggageLoad'] ?? 0), 1) ?> kg</td>
          <td><strong style="color:var(--tv-blue)">₱<?= number_format(floatval($c['pricePerDay'] ?? 0), 2) ?></strong></td>
          <td><?= htmlspecialchars($c['vendorName'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>


<?php include '../includes/footer.php'; ?>
