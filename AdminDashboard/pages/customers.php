<?php
require_once '../includes/db.php';
$pageTitle  = 'Customers';
$activePage = 'customers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'update') {
    $pdo->prepare("UPDATE customer SET cust_title=?,cust_firstname=?,cust_middlename=?,cust_lastname=?,cust_mobilenumber=?,cust_email=? WHERE cust_id=?")
      ->execute([
        trim($_POST['cust_title'] ?? ''), trim($_POST['cust_firstname']), trim($_POST['cust_middlename'] ?? ''),
        trim($_POST['cust_lastname']), trim($_POST['cust_mobilenumber']), trim($_POST['cust_email']),
        intval($_POST['cust_id'])
      ]);
    $msg = 'success:Customer updated.';
  } elseif ($action === 'delete') {
    $id  = intval($_POST['cust_id']);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM rental_order WHERE rent_custid=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      $msg = 'error:Cannot delete this customer — they have rental orders on record. Remove those orders first.';
    } else {
      $pdo->prepare("DELETE FROM customer WHERE cust_id=?")->execute([$id]);
      $msg = 'success:Customer removed.';
    }
  }
}

$search = trim($_GET['q'] ?? '');
try {
  if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM customer WHERE cust_firstname LIKE ? OR cust_lastname LIKE ? OR cust_email LIKE ? ORDER BY cust_id DESC");
    $stmt->execute(["%$search%","%$search%","%$search%"]);
  } else {
    $stmt = $pdo->query("SELECT * FROM customer ORDER BY cust_id DESC");
  }
  $customers = $stmt->fetchAll();
} catch (Exception $e) { $customers = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Customers</h1>
    <p class="page-subtitle"><?= count($customers) ?> registered customer<?= count($customers) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All customers</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search customers…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($customers)): ?>
    <div class="empty-state"><i class="bi bi-people d-block"></i><p>No customers found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $c['cust_id'] ?></strong></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($c['cust_firstname'],0,1).substr($c['cust_lastname'],0,1)) ?>
              </div>
              <div>
                <strong><?= htmlspecialchars(($c['cust_title'] ? $c['cust_title'].' ' : '').trim($c['cust_firstname'].' '.($c['cust_middlename'] ? $c['cust_middlename'].' ' : '').$c['cust_lastname'])) ?></strong>
              </div>
            </div>
          </td>
          <td><?= htmlspecialchars($c['cust_email']) ?></td>
          <td><?= htmlspecialchars($c['cust_mobilenumber']) ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" onclick='openEdit(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_cust_<?= $c['cust_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="cust_id" value="<?= $c['cust_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete" onclick='confirmDelete("customer","del_cust_<?= $c['cust_id'] ?>",<?= json_encode(trim($c['cust_firstname'].' '.$c['cust_lastname']), JSON_HEX_APOS) ?>)'><i class="bi bi-trash3"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="customerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"  value="update">
        <input type="hidden" name="cust_id" id="fm_id" value="">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label-tv">Title</label>
            <select name="cust_title" id="fm_title" class="tv-select">
              <option value="">—</option><option>Mr.</option><option>Ms.</option><option>Mrs.</option>
            </select>
          </div>
          <div class="col-md-9">
            <label class="form-label-tv">First name *</label>
            <input type="text" name="cust_firstname" id="fm_first" class="tv-input" required maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Middle name</label>
            <input type="text" name="cust_middlename" id="fm_mid" class="tv-input" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Last name *</label>
            <input type="text" name="cust_lastname" id="fm_last" class="tv-input" required maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Mobile *</label>
            <input type="text" name="cust_mobilenumber" id="fm_mobile" class="tv-input" required maxlength="20">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Email *</label>
            <input type="email" name="cust_email" id="fm_email" class="tv-input" required maxlength="100">
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
function openEdit(c) {
  document.getElementById('fm_id').value     = c.cust_id;
  document.getElementById('fm_title').value  = c.cust_title || '';
  document.getElementById('fm_first').value  = c.cust_firstname;
  document.getElementById('fm_mid').value    = c.cust_middlename || '';
  document.getElementById('fm_last').value   = c.cust_lastname;
  document.getElementById('fm_mobile').value = c.cust_mobilenumber;
  document.getElementById('fm_email').value  = c.cust_email;
  new bootstrap.Modal(document.getElementById('customerModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>