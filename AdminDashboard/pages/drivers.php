<?php
require_once '../includes/db.php';
$pageTitle  = 'Drivers';
$activePage = 'drivers';

// Admin view-only: drivers are managed by their respective providers.

$search  = trim($_GET['q'] ?? '');

// Fetch all drivers and vendors
$drivers = fb()->listDocs('drivers');
$vendors = fb()->listDocs('vendors');

// Build vendorId → businessName map
$vendorMap = [];
foreach ($vendors as $v) {
    $vid = $v['vendorId'] ?? $v['id'];
    $vendorMap[$vid] = $v['businessName'] ?? '—';
}

if ($search) {
    $sq = strtolower($search);
    $drivers = array_filter($drivers, fn($d) =>
        str_contains(strtolower($d['fullName'] ?? ''), $sq) ||
        str_contains(strtolower($d['phone'] ?? ''), $sq) ||
        str_contains(strtolower($vendorMap[$d['vendorId'] ?? ''] ?? ''), $sq)
    );
}
$drivers = array_values($drivers);

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Drivers</h1>
    <p class="page-subtitle"><?= count($drivers) ?> driver<?= count($drivers) !== 1 ? 's' : '' ?> registered &mdash; managed by their providers</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All drivers</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search name, phone, or provider…" value="<?= htmlspecialchars($search) ?>" style="width:260px">
    </form>
  </div>
  <?php if (empty($drivers)): ?>
    <div class="empty-state"><i class="bi bi-person-badge d-block"></i><p>No drivers found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Title</th><th>Full name</th><th>Mobile</th><th>Provider</th><th>Availability</th></tr>
      </thead>
      <tbody>
        <?php foreach ($drivers as $d):
          $vid          = $d['vendorId'] ?? '';
          $providerName = $vid ? ($vendorMap[$vid] ?? '<em style="color:var(--text-muted)">Unassigned</em>') : '<em style="color:var(--text-muted)">Unassigned</em>';
          $isAvail      = $d['isAvailable'] ?? true;
        ?>
        <tr>
          <td><?= htmlspecialchars($d['title'] ?? '') ?></td>
          <td><strong><?= htmlspecialchars($d['fullName'] ?? '') ?></strong></td>
          <td><?= htmlspecialchars($d['phone'] ?? '') ?></td>
          <td>
            <?php if ($vid && isset($vendorMap[$vid])): ?>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="width:28px;height:28px;border-radius:6px;background:var(--tv-orange-light);color:var(--tv-orange);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0">
                  <?= strtoupper(substr($vendorMap[$vid], 0, 2)) ?>
                </div>
                <span><?= htmlspecialchars($vendorMap[$vid]) ?></span>
              </div>
            <?php else: ?>
              <em style="color:var(--text-muted);font-size:12.5px">Unassigned</em>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isAvail): ?>
              <span class="badge-tv badge-active">Available</span>
            <?php else: ?>
              <span class="badge-tv badge-pending">On duty</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
