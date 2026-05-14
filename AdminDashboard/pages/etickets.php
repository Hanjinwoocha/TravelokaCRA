<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'E-tickets';
$activePage = 'etickets';
$msg = '';

// Update ticket status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  $pdo->prepare("UPDATE eticket SET tick_status=? WHERE tick_id=?")
    ->execute([trim($_POST['tick_status']), intval($_POST['tick_id'])]);
  $msg = 'success:Ticket status updated.';
}

// Delete ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  $pdo->prepare("DELETE FROM eticket WHERE tick_id=?")->execute([intval($_POST['tick_id'])]);
  $msg = 'success:Ticket deleted.';
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

try {
  $where  = [];
  $params = [];
  if ($status_filter) { $where[] = 'et.tick_status = ?'; $params[] = $status_filter; }
  if ($search) {
    $where[] = '(CONCAT(cu.cust_firstname," ",cu.cust_lastname) LIKE ? OR et.tick_qrcode LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
  }
  $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

  $stmt = $pdo->prepare("
    SELECT et.*,
           p.pay_amount, p.pay_method,
           ro.rent_id, ro.rent_dateissued, ro.rent_datedue,
           ro.rent_pickuplocation, ro.rent_dropofflocation,
           CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
           cu.cust_email,
           ca.car_model, ca.car_type
    FROM eticket et
    JOIN payment p      ON p.pay_id     = et.tick_payid
    JOIN rental_order ro ON ro.rent_id  = p.pay_rentid
    JOIN customer cu    ON cu.cust_id   = ro.rent_custid
    JOIN car ca         ON ca.car_id    = ro.rent_carid
    $wc
    ORDER BY et.tick_id DESC
  ");
  $stmt->execute($params);
  $tickets = $stmt->fetchAll();
} catch (Exception $e) { $tickets = []; }

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">E-tickets</h1>
    <p class="page-subtitle"><?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?> found</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All e-tickets</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
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
        <input type="text" name="q" class="tv-input" placeholder="Search tickets…" value="<?= htmlspecialchars($search) ?>" style="width:180px">
      </form>
    </div>
  </div>

  <?php if (empty($tickets)): ?>
    <div class="empty-state">
      <i class="bi bi-ticket-perforated d-block"></i>
      <p>No e-tickets found.</p>
    </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr>
          <th>Ticket ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Order #</th>
          <th>Rental period</th>
          <th>Amount</th>
          <th>Date issued</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td>
            <strong style="color:var(--tv-blue)">#<?= $t['tick_id'] ?></strong><br>
            <span style="font-size:11px;color:var(--text-muted);font-family:monospace"><?= htmlspecialchars(substr($t['tick_qrcode'],0,20)) ?>…</span>
          </td>
          <td>
            <strong><?= htmlspecialchars($t['customer_name']) ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($t['cust_email']) ?></span>
          </td>
          <td>
            <?= htmlspecialchars($t['car_model']) ?><br>
            <span class="badge-tv badge-complete" style="margin-top:2px"><?= htmlspecialchars($t['car_type']) ?></span>
          </td>
          <td>
            <a href="/Traveloka/AdminDashboard/pages/rentals.php" style="color:var(--tv-blue);text-decoration:none;font-weight:600">
              #<?= $t['rent_id'] ?>
            </a>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($t['rent_dateissued']) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($t['rent_datedue']) ?></span>
          </td>
          <td><strong>₱<?= number_format($t['pay_amount'], 2) ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($t['pay_method']) ?></span>
          </td>
          <td><?= htmlspecialchars($t['tick_dateissued']) ?></td>
          <td>
            <?php
              $st  = $t['tick_status'];
              $cls = match($st) {
                'Active'    => 'badge-active',
                'Completed' => 'badge-complete',
                'Cancelled' => 'badge-cancel',
                default     => 'badge-pending',
              };
            ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" title="Update status"
              onclick='openEdit(<?= json_encode(["tick_id"=>$t["tick_id"],"tick_status"=>$t["tick_status"]]) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <form id="del_tick_<?= $t['tick_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="tick_id"  value="<?= $t['tick_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete"
              onclick='confirmDelete("e-ticket","del_tick_<?= $t['tick_id'] ?>",<?= json_encode('#'.$t['tick_id'].' — '.$t['customer_name'], JSON_HEX_APOS) ?>)'>
              <i class="bi bi-trash3"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Edit status modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Update ticket status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"   value="update">
        <input type="hidden" name="tick_id"  id="fm_id" value="">
        <label class="form-label-tv">Status</label>
        <select name="tick_status" id="fm_status" class="tv-select">
          <option>Pending</option>
          <option>Active</option>
          <option>Completed</option>
          <option>Cancelled</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(t) {
  document.getElementById('fm_id').value     = t.tick_id;
  document.getElementById('fm_status').value = t.tick_status;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>