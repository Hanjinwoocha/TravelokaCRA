<?php
require_once '../includes/db.php';
$pageTitle  = 'Rental orders';
$activePage = 'rentals';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
  $pdo->prepare("UPDATE eticket SET tick_status=? WHERE tick_id=?")->execute([
    $_POST['tick_status'], intval($_POST['tick_id'])
  ]);
  $msg = 'success:Ticket status updated.';
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

try {
  $where = [];
  $params = [];
  if ($status_filter) { $where[] = 'et.tick_status = ?'; $params[] = $status_filter; }
  if ($search) {
    $where[] = '(CONCAT(cu.cust_firstname," ",cu.cust_lastname) LIKE ? OR ca.car_model LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
  }
  $whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';

  $stmt = $pdo->prepare("
    SELECT ro.*, et.tick_id, et.tick_status, et.tick_dateissued,
           CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
           cu.cust_email,
           ca.car_model, ca.car_type,
           p.pay_amount, p.pay_method,
           CONCAT(dd.drive_firstname,' ',dd.drive_lastname) AS driver_name
    FROM rental_order ro
    JOIN customer cu ON cu.cust_id = ro.rent_custid
    JOIN car ca      ON ca.car_id  = ro.rent_carid
    LEFT JOIN payment p  ON p.pay_rentid  = ro.rent_id
    LEFT JOIN eticket et ON et.tick_payid = p.pay_id
    LEFT JOIN driver_details dd ON dd.drive_id = ro.rent_driveid
    $whereClause
    ORDER BY ro.rent_id DESC
  ");
  $stmt->execute($params);
  $orders = $stmt->fetchAll();
} catch (Exception $e) { $orders = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Rental orders</h1>
    <p class="page-subtitle"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All orders</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <!-- Status filter pills -->
      <?php foreach ([''=>'All','Pending'=>'Pending','Active'=>'Active','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val=>$label): ?>
        <a href="?status=<?= urlencode($val) ?>&q=<?= urlencode($search) ?>"
           style="padding:4px 14px;border-radius:99px;font-size:12px;font-weight:600;text-decoration:none;
                  <?= $status_filter===$val ? 'background:var(--tv-blue);color:#fff' : 'background:#F0F3F8;color:var(--text-secondary)' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
      <form method="get" class="search-wrap" style="margin-left:8px">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <i class="bi bi-search"></i>
        <input type="text" name="q" class="tv-input" placeholder="Search…" value="<?= htmlspecialchars($search) ?>" style="width:180px">
      </form>
    </div>
  </div>
  <?php if (empty($orders)): ?>
    <div class="empty-state"><i class="bi bi-clipboard2-x d-block"></i><p>No orders found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>Order ID</th><th>Customer</th><th>Vehicle</th><th>Dates</th><th>Driver</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $o['rent_id'] ?></strong></td>
          <td>
            <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($o['cust_email']) ?></span>
          </td>
          <td>
            <?= htmlspecialchars($o['car_model']) ?><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($o['car_type']) ?></span>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($o['rent_dateissued']) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($o['rent_datedue']) ?></span>
          </td>
          <td><?= $o['driver_name'] ? '<span class="badge-tv badge-driver">'.htmlspecialchars($o['driver_name']).'</span>' : '<span class="badge-tv badge-nodriver">Self-drive</span>' ?></td>
          <td><?= $o['pay_amount'] ? '<strong>₱'.number_format($o['pay_amount'],2).'</strong>' : '—' ?></td>
          <td>
            <?php $st = $o['tick_status'] ?? 'Pending';
              $cls = match($st){ 'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel',default=>'badge-pending' }; ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
          <td>
            <?php if ($o['tick_id']): ?>
            <button class="btn-icon" title="Update status" onclick='openStatus(<?= json_encode(['tick_id'=>$o['tick_id'],'tick_status'=>$o['tick_status'],'rent_id'=>$o['rent_id']]) ?>)'>
              <i class="bi bi-pencil-square"></i>
            </button>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Status update modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="statusModalTitle">Update status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"   value="update_status">
        <input type="hidden" name="tick_id"  id="fm_tick_id" value="">
        <label class="form-label-tv">Ticket status</label>
        <select name="tick_status" id="fm_tick_status" class="tv-select">
          <option>Pending</option><option>Active</option><option>Completed</option><option>Cancelled</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openStatus(data) {
  document.getElementById('statusModalTitle').textContent = 'Update status — Order #' + data.rent_id;
  document.getElementById('fm_tick_id').value = data.tick_id;
  document.getElementById('fm_tick_status').value = data.tick_status || 'Pending';
  new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>