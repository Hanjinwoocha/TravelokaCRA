<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Drivers';
$activePage = 'drivers';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId  = $_SESSION['provider_id'] ?? '';
$msg     = '';

// Block access if provider does not offer with-driver service
$vendor     = fb()->getDoc('vendors', $provId);
$withDriver = !empty($vendor['withDriver']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$withDriver) { header('Location: /Traveloka/ProviderDashboard/pages/drivers.php'); exit; }
    $action = $_POST['action'] ?? '';
    $title  = trim($_POST['drive_title']        ?? '');
    $first  = trim($_POST['drive_firstname']    ?? '');
    $mid    = trim($_POST['drive_middlename']   ?? '');
    $last   = trim($_POST['drive_lastname']     ?? '');
    $phone  = trim($_POST['drive_mobilenumber'] ?? '');
    $fullName = trim("$first $last");

    if ($action === 'create') {
        $driverId = fb()->newId();
        fb()->setDoc('drivers', $driverId, [
            'driverId'    => $driverId,
            'title'       => $title,
            'firstName'   => $first,
            'middleName'  => $mid,
            'lastName'    => $last,
            'fullName'    => $fullName,
            'phone'       => $phone,
            'vendorId'    => $provId,
            'isAvailable' => true,
            'createdAt'   => Firebase::nowMs(),
        ]);
        $msg = 'success:Driver added.';
    } elseif ($action === 'update') {
        $driverId = trim($_POST['drive_id'] ?? '');
        fb()->updateDoc('drivers', $driverId, [
            'title'      => $title,
            'firstName'  => $first,
            'middleName' => $mid,
            'lastName'   => $last,
            'fullName'   => $fullName,
            'phone'      => $phone,
        ]);
        $msg = 'success:Driver updated.';
    } elseif ($action === 'delete') {
        $driverId = trim($_POST['drive_id'] ?? '');
        // Block delete if driver has active/upcoming bookings
        $activeBookings = fb()->query('bookings', [
            ['field' => 'driverId',      'op' => 'EQUAL', 'value' => $driverId],
            ['field' => 'bookingStatus', 'op' => 'EQUAL', 'value' => 'Ongoing'],
        ]);
        $upcomingBookings = fb()->query('bookings', [
            ['field' => 'driverId',      'op' => 'EQUAL', 'value' => $driverId],
            ['field' => 'bookingStatus', 'op' => 'EQUAL', 'value' => 'Upcoming'],
        ]);
        if (count($activeBookings) > 0 || count($upcomingBookings) > 0) {
            $msg = 'error:Cannot remove this driver — they\'re assigned to active or upcoming orders.';
        } else {
            fb()->deleteDoc('drivers', $driverId);
            $msg = 'success:Driver removed.';
        }
    }
}

$search  = trim($_GET['q'] ?? '');
$drivers = fb()->query('drivers', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);
if ($search) {
    $sq      = strtolower($search);
    $drivers = array_values(array_filter($drivers, fn($d) =>
        str_contains(strtolower($d['fullName'] ?? ''), $sq) ||
        str_contains(strtolower($d['phone']    ?? ''), $sq)
    ));
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Drivers</h1>
    <p class="page-subtitle"><?= $withDriver ? count($drivers).' driver'.(count($drivers)!==1?'s':'').' registered' : 'Not available' ?></p>
  </div>
  <?php if ($withDriver): ?>
  <button class="btn-tv-primary" onclick="openModal()"><i class="bi bi-plus-lg"></i> Add driver</button>
  <?php endif; ?>
</div>

<?php if (!$withDriver): ?>
<div class="content-card">
  <div class="empty-state">
    <i class="bi bi-person-badge d-block" style="color:var(--text-muted)"></i>
    <h3>With-driver service not enabled</h3>
    <p>Your account is set to self-drive only. To manage drivers, enable the with-driver option in your profile first.</p>
    <a href="/Traveloka/ProviderDashboard/pages/profile.php" class="btn-tv-primary" style="display:inline-flex;margin-top:8px">
      <i class="bi bi-gear"></i> Update profile
    </a>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; exit; ?>
<?php endif; ?>

<?php if ($msg): [$type,$text] = explode(':',$msg,2); ?>
<div class="alert-tv <?= $type ?>"><i class="bi bi-<?= $type==='success'?'check':'exclamation' ?>-circle-fill"></i><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All drivers</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search drivers…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($drivers)): ?>
    <div class="empty-state"><i class="bi bi-person-badge d-block"></i><p>No drivers yet.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>Full name</th><th>Mobile</th><th>Available</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($drivers as $d):
          $dId      = $d['driverId'] ?? $d['id'];
          $initials = strtoupper(substr($d['firstName'] ?? '', 0, 1) . substr($d['lastName'] ?? '', 0, 1));
          $dispName = trim(($d['title'] ? $d['title'].' ' : '') . ($d['fullName'] ?? ''));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0">
                <?= htmlspecialchars($initials ?: '?') ?>
              </div>
              <strong><?= htmlspecialchars($dispName) ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($d['phone'] ?? '') ?></td>
          <td><?= !empty($d['isAvailable']) ? '<span class="badge-tv badge-active">Yes</span>' : '<span class="badge-tv badge-cancel">No</span>' ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" onclick='openEdit(<?= json_encode(["drive_id"=>$dId,"drive_title"=>$d["title"]??"",'drive_firstname'=>$d["firstName"]??"",'drive_middlename'=>$d["middleName"]??"",'drive_lastname'=>$d["lastName"]??"",'drive_mobilenumber'=>$d["phone"]??""]) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_drv_<?= htmlspecialchars($dId) ?>" method="post" style="display:none">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="drive_id" value="<?= htmlspecialchars($dId) ?>">
            </form>
            <button class="btn-icon danger" onclick='confirmDelete("driver","del_drv_<?= htmlspecialchars($dId) ?>",<?= json_encode($dispName, JSON_HEX_APOS) ?>)'><i class="bi bi-trash3"></i></button>
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
        <h5 class="modal-title" id="drvTitle">Add driver</h5>
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
  document.getElementById('drvTitle').textContent = 'Add driver';
  document.getElementById('fm_action').value = 'create';
  ['fm_id','fm_first','fm_mid','fm_last','fm_mobile'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fm_title').value = '';
  new bootstrap.Modal(document.getElementById('driverModal')).show();
}
function openEdit(d) {
  document.getElementById('drvTitle').textContent  = 'Edit driver';
  document.getElementById('fm_action').value        = 'update';
  document.getElementById('fm_id').value            = d.drive_id;
  document.getElementById('fm_title').value         = d.drive_title || '';
  document.getElementById('fm_first').value         = d.drive_firstname;
  document.getElementById('fm_mid').value           = d.drive_middlename || '';
  document.getElementById('fm_last').value          = d.drive_lastname;
  document.getElementById('fm_mobile').value        = d.drive_mobilenumber;
  new bootstrap.Modal(document.getElementById('driverModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
