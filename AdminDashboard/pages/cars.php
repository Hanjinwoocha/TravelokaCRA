<?php
require_once '../includes/db.php';
$pageTitle  = 'Cars';
$activePage = 'cars';
$msg = '';

try { $providers = $pdo->query("SELECT prov_id, prov_name FROM car_provider ORDER BY prov_name")->fetchAll(); }
catch (Exception $e) { $providers = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $fields = [
    trim($_POST['car_model']       ?? ''),
    trim($_POST['car_type']        ?? ''),
    intval($_POST['car_capacity']  ?? 0),
    floatval($_POST['car_baggageload'] ?? 0),
    floatval($_POST['car_rentalrate']  ?? 0),
    intval($_POST['car_provid']    ?? 0),
  ];

  if ($action === 'create') {
    $pdo->prepare("INSERT INTO car (car_model,car_type,car_capacity,car_baggageload,car_rentalrate,car_provid) VALUES (?,?,?,?,?,?)")->execute($fields);
    $msg = 'success:Car added successfully.';
  } elseif ($action === 'update') {
    $id = intval($_POST['car_id']);
    $pdo->prepare("UPDATE car SET car_model=?,car_type=?,car_capacity=?,car_baggageload=?,car_rentalrate=?,car_provid=? WHERE car_id=?")->execute([...$fields, $id]);
    $msg = 'success:Car updated.';
  } elseif ($action === 'delete') {
    $id  = intval($_POST['car_id']);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM rental_order WHERE rent_carid=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      $msg = 'error:Cannot delete this car — it has rental orders linked to it. Remove those orders first.';
    } else {
      $pdo->prepare("DELETE FROM car WHERE car_id=?")->execute([$id]);
      $msg = 'success:Car deleted.';
    }
  }
}

$search = trim($_GET['q'] ?? '');
try {
  if ($search) {
    $stmt = $pdo->prepare("SELECT c.*, p.prov_name FROM car c LEFT JOIN car_provider p ON p.prov_id = c.car_provid WHERE c.car_model LIKE ? OR c.car_type LIKE ? OR p.prov_name LIKE ? ORDER BY c.car_id DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
  } else {
    $stmt = $pdo->query("SELECT c.*, p.prov_name FROM car c LEFT JOIN car_provider p ON p.prov_id = c.car_provid ORDER BY c.car_id DESC");
  }
  $cars = $stmt->fetchAll();
} catch (Exception $e) { $cars = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Cars</h1>
    <p class="page-subtitle"><?= count($cars) ?> car<?= count($cars) !== 1 ? 's' : '' ?> in fleet</p>
  </div>
  <button class="btn-tv-primary" onclick="openModal()">
    <i class="bi bi-plus-lg"></i> Add car
  </button>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All cars</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search cars…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($cars)): ?>
    <div class="empty-state"><i class="bi bi-car-front d-block"></i><p>No cars found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>ID</th><th>Model</th><th>Type</th><th>Seats</th><th>Baggage (kg)</th><th>Rate/day</th><th>Provider</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cars as $c): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $c['car_id'] ?></strong></td>
          <td><strong><?= htmlspecialchars($c['car_model']) ?></strong></td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($c['car_type']) ?></span></td>
          <td><?= $c['car_capacity'] ?> seats</td>
          <td><?= number_format($c['car_baggageload'], 1) ?> kg</td>
          <td><strong style="color:var(--tv-blue)">₱<?= number_format($c['car_rentalrate'], 2) ?></strong></td>
          <td><?= htmlspecialchars($c['prov_name'] ?? '—') ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" onclick='openEdit(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_car_<?= $c['car_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="car_id" value="<?= $c['car_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete" onclick='confirmDelete("car","del_car_<?= $c['car_id'] ?>",<?= json_encode($c['car_model'], JSON_HEX_APOS) ?>)'><i class="bi bi-trash3"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="carModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="carModalTitle">Add car</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="fm_action" value="create">
        <input type="hidden" name="car_id" id="fm_id" value="">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-tv">Model name *</label>
            <input type="text" name="car_model" id="fm_model" class="tv-input" required maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Car type *</label>
            <select name="car_type" id="fm_type" class="tv-select" required>
              <option value="">Select type…</option>
              <option>Sedan</option><option>SUV</option><option>Van</option><option>MPV</option><option>Pickup</option><option>Hatchback</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Provider *</label>
            <select name="car_provid" id="fm_prov" class="tv-select" required>
              <option value="">Select provider…</option>
              <?php foreach ($providers as $p): ?>
              <option value="<?= $p['prov_id'] ?>"><?= htmlspecialchars($p['prov_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label-tv">Seat capacity *</label>
            <input type="number" name="car_capacity" id="fm_cap" class="tv-input" min="1" max="99" required>
          </div>
          <div class="col-md-4">
            <label class="form-label-tv">Baggage (kg) *</label>
            <input type="number" name="car_baggageload" id="fm_bag" class="tv-input" min="0" step="0.01" required>
          </div>
          <div class="col-md-4">
            <label class="form-label-tv">Rate/day (₱) *</label>
            <input type="number" name="car_rentalrate" id="fm_rate" class="tv-input" min="0" step="0.01" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('carModalTitle').textContent = 'Add car';
  document.getElementById('fm_action').value = 'create';
  ['fm_id','fm_model','fm_cap','fm_bag','fm_rate'].forEach(id => document.getElementById(id).value = '');
  ['fm_type','fm_prov'].forEach(id => document.getElementById(id).value = '');
  new bootstrap.Modal(document.getElementById('carModal')).show();
}
function openEdit(c) {
  document.getElementById('carModalTitle').textContent = 'Edit car';
  document.getElementById('fm_action').value = 'update';
  document.getElementById('fm_id').value     = c.car_id;
  document.getElementById('fm_model').value  = c.car_model;
  document.getElementById('fm_type').value   = c.car_type;
  document.getElementById('fm_prov').value   = c.car_provid;
  document.getElementById('fm_cap').value    = c.car_capacity;
  document.getElementById('fm_bag').value    = c.car_baggageload;
  document.getElementById('fm_rate').value   = c.car_rentalrate;
  new bootstrap.Modal(document.getElementById('carModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>