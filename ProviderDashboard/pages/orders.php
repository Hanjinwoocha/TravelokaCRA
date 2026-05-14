<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Rental orders';
$activePage = 'orders';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? 0;
$msg = '';

// Assign driver to order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_driver') {
  $rentId  = intval($_POST['rent_id']);
  $driveId = intval($_POST['drive_id']) ?: null;

  // Verify order belongs to this provider AND is in an assignable state
  $check = $pdo->prepare("
    SELECT ro.rent_id, et.tick_status
    FROM rental_order ro
    JOIN car ca           ON ca.car_id    = ro.rent_carid
    LEFT JOIN payment p   ON p.pay_rentid  = ro.rent_id
    LEFT JOIN eticket et  ON et.tick_payid = p.pay_id
    WHERE ro.rent_id = ? AND ca.car_provid = ?
  ");
  $check->execute([$rentId, $provId]);
  $row = $check->fetch();

  if (!$row) {
    $msg = 'error:Order not found or does not belong to your fleet.';
  } elseif (in_array($row['tick_status'], ['Completed', 'Cancelled'])) {
    $msg = 'error:Cannot reassign driver — order is already ' . strtolower($row['tick_status']) . '.';
  } else {
    $pdo->prepare("UPDATE rental_order SET rent_driveid=? WHERE rent_id=?")->execute([$driveId, $rentId]);
    $msg = 'success:Driver assigned successfully.';
  }
}

// Update ticket status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
  $tickId    = intval($_POST['tick_id']);
  $newStatus = $_POST['new_status'] ?? '';

  // Allowed transitions: where you can go FROM each status
  $allowed = [
    'Pending'   => ['Active', 'Cancelled'],
    'Active'    => ['Completed', 'Cancelled'],
    'Completed' => [],  // terminal
    'Cancelled' => [],  // terminal
  ];

  if (!in_array($newStatus, ['Active','Completed','Cancelled'])) {
    $msg = 'error:Invalid status value.';
  } else {
    // Verify ticket belongs to this provider AND get current status
    $check = $pdo->prepare("
      SELECT et.tick_id, et.tick_status FROM eticket et
      JOIN payment p      ON p.pay_id    = et.tick_payid
      JOIN rental_order ro ON ro.rent_id = p.pay_rentid
      JOIN car ca          ON ca.car_id  = ro.rent_carid
      WHERE et.tick_id = ? AND ca.car_provid = ?
    ");
    $check->execute([$tickId, $provId]);
    $row = $check->fetch();

    if (!$row) {
      $msg = 'error:Order not found or does not belong to your fleet.';
    } elseif (!in_array($newStatus, $allowed[$row['tick_status']] ?? [])) {
      $msg = 'error:Cannot change status from ' . $row['tick_status'] . ' to ' . $newStatus . '.';
    } else {
      $pdo->prepare("UPDATE eticket SET tick_status=? WHERE tick_id=?")->execute([$newStatus, $tickId]);
      $msg = 'success:Order ' . ($newStatus === 'Active' ? 'accepted' : strtolower($newStatus)) . ' successfully.';
    }
  }
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

try {
  $where  = ['ca.car_provid = ?'];
  $params = [$provId];
  if ($status_filter) { $where[] = 'et.tick_status = ?'; $params[] = $status_filter; }
  if ($search) {
    $where[] = '(CONCAT(cu.cust_firstname," ",cu.cust_lastname) LIKE ? OR ca.car_model LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
  }
  $wc = 'WHERE ' . implode(' AND ', $where);

  $stmt = $pdo->prepare("
    SELECT ro.*,
           CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
           cu.cust_email, cu.cust_mobilenumber,
           ca.car_model, ca.car_type,
           p.pay_amount, p.pay_method,
           et.tick_id, et.tick_status,
           dd.drive_id AS assigned_drive_id,
           CONCAT(dd.drive_firstname,' ',dd.drive_lastname) AS driver_name
    FROM rental_order ro
    JOIN car ca      ON ca.car_id   = ro.rent_carid
    JOIN customer cu ON cu.cust_id  = ro.rent_custid
    LEFT JOIN payment p   ON p.pay_rentid  = ro.rent_id
    LEFT JOIN eticket et  ON et.tick_payid = p.pay_id
    LEFT JOIN driver_details dd ON dd.drive_id = ro.rent_driveid
    $wc
    ORDER BY ro.rent_id DESC
  ");
  $stmt->execute($params);
  $orders = $stmt->fetchAll();
} catch (Exception $e) { $orders = []; }

// Drivers for assignment dropdown
try { $drivers = $pdo->query("SELECT drive_id, drive_firstname, drive_lastname FROM driver_details ORDER BY drive_firstname")->fetchAll(); }
catch (Exception $e) { $drivers = []; }

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Rental orders</h1>
    <p class="page-subtitle"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> for your fleet</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All orders</h2>
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
        <input type="text" name="q" class="tv-input" placeholder="Search…" value="<?= htmlspecialchars($search) ?>" style="width:180px">
      </form>
    </div>
  </div>
  <?php if (empty($orders)): ?>
    <div class="empty-state"><i class="bi bi-clipboard2-x d-block"></i><p>No orders found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Order ID</th><th>Customer</th><th>Vehicle</th><th>Pickup → Dropoff</th><th>Dates</th><th>Driver</th><th>Amount</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $o['rent_id'] ?></strong></td>
          <td>
            <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
            <span style="font-size:11.5px;color:var(--text-secondary)"><?= htmlspecialchars($o['cust_mobilenumber']) ?></span>
          </td>
          <td>
            <?= htmlspecialchars($o['car_model']) ?><br>
            <span class="badge-tv badge-complete" style="margin-top:2px"><?= htmlspecialchars($o['car_type']) ?></span>
          </td>
          <td style="font-size:12px;max-width:160px">
            <span style="color:var(--text-primary)"><?= htmlspecialchars(substr($o['rent_pickuplocation'],0,30)) ?>…</span><br>
            <span style="color:var(--text-secondary)">→ <?= htmlspecialchars(substr($o['rent_dropofflocation'],0,30)) ?>…</span>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($o['rent_dateissued']) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($o['rent_datedue']) ?></span>
          </td>
          <td>
            <?php if ($o['driver_name'] && trim($o['driver_name']) !== ' '): ?>
              <span class="badge-tv badge-driver"><?= htmlspecialchars($o['driver_name']) ?></span>
            <?php else: ?>
              <span class="badge-tv badge-nodriver">Unassigned</span>
            <?php endif; ?>
          </td>
          <td><?= $o['pay_amount'] ? '<strong>₱'.number_format($o['pay_amount'],2).'</strong>' : '—' ?></td>
          <td>
            <?php $st=$o['tick_status']??'Pending'; $cls=match($st){'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel',default=>'badge-pending'}; ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
          <td>
            <div style="display:flex;gap:4px;align-items:center;flex-wrap:nowrap">
              <!-- Assign driver -->
              <button class="btn-icon" title="Assign driver"
                onclick='openAssign(<?= json_encode(["rent_id"=>$o["rent_id"],"assigned_drive_id"=>$o["assigned_drive_id"]]) ?>)'>
                <i class="bi bi-person-check"></i>
              </button>

              <?php if ($o['tick_id'] && $o['tick_status'] === 'Pending'): ?>
              <!-- Accept -->
              <form method="post" style="display:contents"
                    data-confirm-title="Accept order"
                    data-confirm-message="Accept order #<?= $o['rent_id'] ?> and mark it Active?"
                    data-confirm-variant="success"
                    data-confirm-action="Accept"
                    data-confirm-action-icon="bi-check-lg">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="tick_id"    value="<?= $o['tick_id'] ?>">
                <input type="hidden" name="new_status" value="Active">
                <button type="submit" class="btn-icon" title="Accept order"
                        style="color:#16A34A;border-color:#86EFAC">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <!-- Decline -->
              <form method="post" style="display:contents"
                    data-confirm-title="Decline order"
                    data-confirm-message="Decline order #<?= $o['rent_id'] ?>? This cannot be undone."
                    data-confirm-variant="danger"
                    data-confirm-action="Decline"
                    data-confirm-action-icon="bi-x-lg">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="tick_id"    value="<?= $o['tick_id'] ?>">
                <input type="hidden" name="new_status" value="Cancelled">
                <button type="submit" class="btn-icon danger" title="Decline order">
                  <i class="bi bi-x-lg"></i>
                </button>
              </form>

              <?php elseif ($o['tick_id'] && $o['tick_status'] === 'Active'): ?>
              <!-- Mark completed -->
              <form method="post" style="display:contents"
                    data-confirm-title="Complete order"
                    data-confirm-message="Mark order #<?= $o['rent_id'] ?> as Completed?"
                    data-confirm-variant="info"
                    data-confirm-action="Complete"
                    data-confirm-action-icon="bi-flag-fill">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="tick_id"    value="<?= $o['tick_id'] ?>">
                <input type="hidden" name="new_status" value="Completed">
                <button type="submit" class="btn-icon" title="Mark as completed"
                        style="color:var(--tv-blue);border-color:#93C5FD">
                  <i class="bi bi-flag-fill"></i>
                </button>
              </form>
              <!-- Cancel active -->
              <form method="post" style="display:contents"
                    data-confirm-title="Cancel order"
                    data-confirm-message="Cancel active order #<?= $o['rent_id'] ?>?"
                    data-confirm-variant="danger"
                    data-confirm-action="Cancel order"
                    data-confirm-action-icon="bi-x-lg">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="tick_id"    value="<?= $o['tick_id'] ?>">
                <input type="hidden" name="new_status" value="Cancelled">
                <button type="submit" class="btn-icon danger" title="Cancel order">
                  <i class="bi bi-x-lg"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Assign driver modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignTitle">Assign driver</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"  value="assign_driver">
        <input type="hidden" name="rent_id" id="fm_rent_id" value="">
        <label class="form-label-tv">Select driver</label>
        <select name="drive_id" id="fm_drive_id" class="tv-select">
          <option value="">— Self-drive (no driver) —</option>
          <?php foreach ($drivers as $d): ?>
          <option value="<?= $d['drive_id'] ?>"><?= htmlspecialchars($d['drive_firstname'].' '.$d['drive_lastname']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Assign</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAssign(data) {
  document.getElementById('assignTitle').textContent = 'Assign driver — Order #' + data.rent_id;
  document.getElementById('fm_rent_id').value  = data.rent_id;
  document.getElementById('fm_drive_id').value = data.assigned_drive_id || '';
  new bootstrap.Modal(document.getElementById('assignModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>