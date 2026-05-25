<?php
require_once '../includes/db.php';
$pageTitle  = 'Rental orders';
$activePage = 'rentals';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $bookingId = trim($_POST['booking_id'] ?? '');

    if ($action === 'flag' && $bookingId) {
        $flagReason = trim($_POST['flag_reason'] ?? 'This order has been flagged for review by an administrator.');
        fb()->updateDoc('bookings', $bookingId, [
            'isFlagged'  => true,
            'flagReason' => $flagReason,
        ]);
        $msg = 'success:Order flagged successfully.';
    }

    if ($action === 'unflag' && $bookingId) {
        fb()->updateDoc('bookings', $bookingId, [
            'isFlagged'  => false,
            'flagReason' => '',
        ]);
        $msg = 'success:Flag removed from order.';
    }
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

// Map filter pill value → Firestore bookingStatus
$filterMap = ['Pending' => 'Upcoming', 'Active' => 'Ongoing', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
$filters = [];
if ($status_filter && isset($filterMap[$status_filter])) {
    $filters[] = ['field' => 'bookingStatus', 'op' => 'EQUAL', 'value' => $filterMap[$status_filter]];
}

$orders = fb()->query('bookings', $filters);
usort($orders, fn($a, $b) => intval($b['createdAt'] ?? 0) <=> intval($a['createdAt'] ?? 0));

if ($search) {
    $sq = strtolower($search);
    $orders = array_filter($orders, fn($o) =>
        str_contains(strtolower($o['renterName']    ?? ''), $sq) ||
        str_contains(strtolower($o['vehicleName']   ?? ''), $sq) ||
        str_contains(strtolower($o['renterEmail']   ?? ''), $sq)
    );
}
$orders = array_values($orders);

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Rental orders</h1>
    <p class="page-subtitle"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<?php if ($msg): [$type,$text] = explode(':', $msg, 2); ?>
<div class="alert-tv <?= $type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px">
  <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All orders</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?php foreach ([''=>'All','Pending'=>'Upcoming','Active'=>'Ongoing','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val=>$label): ?>
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
      <thead><tr><th>Booking ID</th><th>Customer</th><th>Vehicle</th><th>Dates</th><th>Driver</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o):
          $startDate = Firebase::msToDate($o['startDateMs'] ?? 0);
          $endDate   = Firebase::msToDate($o['endDateMs']   ?? 0);
          $status    = $o['bookingStatus'] ?? 'Upcoming';
          $badgeCls  = Firebase::statusBadge($status);
          $shortId   = substr($o['id'], 0, 8);
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue);font-family:monospace;font-size:12px"><?= htmlspecialchars($shortId) ?>…</strong></td>
          <td>
            <strong><?= htmlspecialchars($o['renterName'] ?? '—') ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($o['renterEmail'] ?? '') ?></span>
          </td>
          <td>
            <?= htmlspecialchars($o['vehicleName'] ?? '—') ?><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($o['vendorName'] ?? '') ?></span>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($startDate) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($endDate) ?></span>
          </td>
          <td>
            <?php if (!empty($o['driverName'])): ?>
              <span class="badge-tv badge-driver"><?= htmlspecialchars($o['driverName']) ?></span>
            <?php else: ?>
              <span class="badge-tv badge-nodriver">Self-drive</span>
            <?php endif; ?>
          </td>
          <td><?= isset($o['totalPrice']) ? '<strong>₱'.number_format(floatval($o['totalPrice']),2).'</strong>' : '—' ?></td>
          <td>
            <span class="badge-tv <?= $badgeCls ?>"><?= htmlspecialchars(Firebase::statusLabel($status)) ?></span>
            <?php if (!empty($o['isFlagged'])): ?>
              <span class="badge-tv" style="background:#FEF3C7;color:#92400E;border-color:#FDE68A;margin-top:4px;display:inline-flex">
                <i class="bi bi-flag-fill" style="margin-right:3px"></i>Flagged
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (empty($o['isFlagged'])): ?>
              <button class="btn-icon" title="Flag order"
                onclick='openFlag(<?= json_encode(["booking_id"=>$o["id"],"shortId"=>$shortId]) ?>)'>
                <i class="bi bi-flag"></i>
              </button>
            <?php else: ?>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"     value="unflag">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($o['id']) ?>">
                <button type="submit" class="btn-icon" title="Remove flag" style="color:#92400E;border-color:#FDE68A">
                  <i class="bi bi-flag-fill"></i>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>


<!-- Flag order modal -->
<div class="modal fade" id="flagModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-flag-fill" style="color:#D97706;margin-right:6px"></i>Flag order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"     value="flag">
        <input type="hidden" name="booking_id" id="fm_booking_id" value="">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
          Flagging order <strong id="fm_short_id"></strong> will mark it for review and notify the customer.
        </p>
        <label class="form-label-tv">Reason *</label>
        <textarea name="flag_reason" id="fm_flag_reason" class="tv-input" rows="3" maxlength="300" required
          placeholder="e.g. Suspected fraudulent payment…" style="resize:vertical;height:auto;padding:8px 12px"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary" style="background:#D97706;border-color:#D97706"><i class="bi bi-flag-fill"></i> Flag order</button>
      </div>
    </form>
  </div>
</div>

<script>
function openFlag(data) {
  document.getElementById('fm_booking_id').value = data.booking_id;
  document.getElementById('fm_short_id').textContent = data.shortId + '…';
  document.getElementById('fm_flag_reason').value = '';
  new bootstrap.Modal(document.getElementById('flagModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
