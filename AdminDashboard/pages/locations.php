<?php
require_once '../includes/db.php';
$pageTitle  = 'Locations';
$activePage = 'locations';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $name   = trim($_POST['loctn_name']           ?? '');
  $class  = trim($_POST['loctn_classification'] ?? '');

  if ($action === 'create') {
    $pdo->prepare("INSERT INTO location (loctn_name, loctn_classification) VALUES (?,?)")->execute([$name, $class]);
    $msg = 'success:Location added.';
  } elseif ($action === 'update') {
    $pdo->prepare("UPDATE location SET loctn_name=?, loctn_classification=? WHERE loctn_id=?")->execute([$name, $class, intval($_POST['loctn_id'])]);
    $msg = 'success:Location updated.';
  } elseif ($action === 'delete') {
    $id  = intval($_POST['loctn_id']);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM car_provider WHERE prov_loctnid=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      $msg = 'error:Cannot delete this location — one or more car providers are assigned to it. Reassign those providers first.';
    } else {
      $pdo->prepare("DELETE FROM location WHERE loctn_id=?")->execute([$id]);
      $msg = 'success:Location deleted.';
    }
  }
}

$search = trim($_GET['q'] ?? '');
try {
  if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM location WHERE loctn_name LIKE ? OR loctn_classification LIKE ? ORDER BY loctn_id DESC");
    $stmt->execute(["%$search%", "%$search%"]);
  } else {
    $stmt = $pdo->query("SELECT * FROM location ORDER BY loctn_id DESC");
  }
  $locations = $stmt->fetchAll();
} catch (Exception $e) { $locations = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Locations</h1>
    <p class="page-subtitle"><?= count($locations) ?> location<?= count($locations) !== 1 ? 's' : '' ?></p>
  </div>
  <button class="btn-tv-primary" onclick="openModal()"><i class="bi bi-plus-lg"></i> Add location</button>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All locations</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search locations…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($locations)): ?>
    <div class="empty-state"><i class="bi bi-geo-alt d-block"></i><p>No locations found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>ID</th><th>Name</th><th>Classification</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($locations as $l): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $l['loctn_id'] ?></strong></td>
          <td><strong><?= htmlspecialchars($l['loctn_name']) ?></strong></td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($l['loctn_classification']) ?></span></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" onclick='openEdit(<?= json_encode($l) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_loc_<?= $l['loctn_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="loctn_id" value="<?= $l['loctn_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete" onclick='confirmDelete("location","del_loc_<?= $l['loctn_id'] ?>",<?= json_encode($l['loctn_name'], JSON_HEX_APOS) ?>)'><i class="bi bi-trash3"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="locationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="locModalTitle">Add location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"   id="fm_action" value="create">
        <input type="hidden" name="loctn_id" id="fm_id"     value="">
        <div class="mb-3">
          <label class="form-label-tv">Location name *</label>
          <input type="text" name="loctn_name" id="fm_name" class="tv-input" required maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label-tv">Classification *</label>
          <select name="loctn_classification" id="fm_class" class="tv-select" required>
            <option value="">Select…</option>
            <option>Airport</option><option>City</option><option>Hotel</option><option>Port</option><option>Terminal</option><option>Mall</option><option>Other</option>
          </select>
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
  document.getElementById('locModalTitle').textContent = 'Add location';
  document.getElementById('fm_action').value = 'create';
  document.getElementById('fm_id').value     = '';
  document.getElementById('fm_name').value   = '';
  document.getElementById('fm_class').value  = '';
  new bootstrap.Modal(document.getElementById('locationModal')).show();
}
function openEdit(l) {
  document.getElementById('locModalTitle').textContent = 'Edit location';
  document.getElementById('fm_action').value = 'update';
  document.getElementById('fm_id').value     = l.loctn_id;
  document.getElementById('fm_name').value   = l.loctn_name;
  document.getElementById('fm_class').value  = l.loctn_classification;
  new bootstrap.Modal(document.getElementById('locationModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>