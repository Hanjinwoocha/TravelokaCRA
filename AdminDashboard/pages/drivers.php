<?php
require_once '../includes/db.php';
$pageTitle  = 'Drivers';
$activePage = 'drivers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $fields = [
    trim($_POST['drive_title']      ?? ''),
    trim($_POST['drive_firstname']  ?? ''),
    trim($_POST['drive_middlename'] ?? ''),
    trim($_POST['drive_lastname']   ?? ''),
    trim($_POST['drive_mobilenumber'] ?? ''),
  ];

  if ($action === 'create') {
    $pdo->prepare("INSERT INTO driver_details (drive_title,drive_firstname,drive_middlename,drive_lastname,drive_mobilenumber) VALUES (?,?,?,?,?)")->execute($fields);
    $msg = 'success:Driver added.';
  } elseif ($action === 'update') {
    $pdo->prepare("UPDATE driver_details SET drive_title=?,drive_firstname=?,drive_middlename=?,drive_lastname=?,drive_mobilenumber=? WHERE drive_id=?")->execute([...$fields, intval($_POST['drive_id'])]);
    $msg = 'success:Driver updated.';
  } elseif ($action === 'delete') {
    $id  = intval($_POST['drive_id']);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM rental_order WHERE rent_driveid=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      $msg = 'error:Cannot delete this driver — they are assigned to one or more rental orders.';
    } else {
      $pdo->prepare("DELETE FROM driver_details WHERE drive_id=?")->execute([$id]);
      $msg = 'success:Driver deleted.';
    }
  }
}

$search = trim($_GET['q'] ?? '');
try {
  if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM driver_details WHERE drive_firstname LIKE ? OR drive_lastname LIKE ? OR drive_mobilenumber LIKE ? ORDER BY drive_id DESC");
    $stmt->execute(["%$search%","%$search%","%$search%"]);
  } else {
    $stmt = $pdo->query("SELECT * FROM driver_details ORDER BY drive_id DESC");
  }
  $drivers = $stmt->fetchAll();
} catch (Exception $e) { $drivers = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Drivers</h1>
    <p class="page-subtitle"><?= count($drivers) ?> driver<?= count($drivers) !== 1 ? 's' : '' ?> registered</p>
  </div>
  <button class="btn-tv-primary" onclick="openModal()"><i class="bi bi-plus-lg"></i> Add driver</button>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All drivers</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search drivers…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($drivers)): ?>
    <div class="empty-state"><i class="bi bi-person-badge d-block"></i><p>No drivers found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>ID</th><th>Title</th><th>Full name</th><th>Mobile</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($drivers as $d): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $d['drive_id'] ?></strong></td>
          <td><?= htmlspecialchars($d['drive_title'] ?? '') ?></td>
          <td><strong><?= htmlspecialchars(trim($d['drive_firstname'].' '.($d['drive_middlename'] ? $d['drive_middlename'].' ' : '').$d['drive_lastname'])) ?></strong></td>
          <td><?= htmlspecialchars($d['drive_mobilenumber']) ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" onclick='openEdit(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_drv_<?= $d['drive_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="drive_id" value="<?= $d['drive_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete" onclick='confirmDelete("driver","del_drv_<?= $d['drive_id'] ?>",<?= json_encode(trim($d['drive_firstname'].' '.$d['drive_lastname']), JSON_HEX_APOS) ?>)'><i class="bi bi-trash3"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="driverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="drvModalTitle">Add driver</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"   id="fm_action" value="create">
        <input type="hidden" name="drive_id" id="fm_id"     value="">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label-tv">Title</label>
            <select name="drive_title" id="fm_title" class="tv-select">
              <option value="">—</option><option>Mr.</option><option>Ms.</option><option>Mrs.</option>
            </select>
          </div>
          <div class="col-md-9">
            <label class="form-label-tv">First name *</label>
            <input type="text" name="drive_firstname" id="fm_first" class="tv-input" required maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Middle name</label>
            <input type="text" name="drive_middlename" id="fm_mid" class="tv-input" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Last name *</label>
            <input type="text" name="drive_lastname" id="fm_last" class="tv-input" required maxlength="50">
          </div>
          <div class="col-12">
            <label class="form-label-tv">Mobile number *</label>
            <input type="text" name="drive_mobilenumber" id="fm_mobile" class="tv-input" required maxlength="20">
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
  document.getElementById('drvModalTitle').textContent = 'Add driver';
  document.getElementById('fm_action').value = 'create';
  ['fm_id','fm_first','fm_mid','fm_last','fm_mobile'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fm_title').value = '';
  new bootstrap.Modal(document.getElementById('driverModal')).show();
}
function openEdit(d) {
  document.getElementById('drvModalTitle').textContent = 'Edit driver';
  document.getElementById('fm_action').value = 'update';
  document.getElementById('fm_id').value     = d.drive_id;
  document.getElementById('fm_title').value  = d.drive_title || '';
  document.getElementById('fm_first').value  = d.drive_firstname;
  document.getElementById('fm_mid').value    = d.drive_middlename || '';
  document.getElementById('fm_last').value   = d.drive_lastname;
  document.getElementById('fm_mobile').value = d.drive_mobilenumber;
  new bootstrap.Modal(document.getElementById('driverModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>