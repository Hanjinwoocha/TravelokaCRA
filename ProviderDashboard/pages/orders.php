<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Rental orders';
$activePage = 'orders';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? '';
$msg = '';

// Assign driver to booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_driver') {
    $bookingId  = trim($_POST['booking_id'] ?? '');
    $driverId   = trim($_POST['drive_id']   ?? '');
    $driverName = trim($_POST['driver_name'] ?? '');

    $booking = fb()->getDoc('bookings', $bookingId);
    if (!$booking || ($booking['vendorId'] ?? '') !== $provId) {
        $msg = 'error:Booking not found or does not belong to your fleet.';
    } elseif (in_array($booking['bookingStatus'] ?? '', ['Completed', 'Cancelled'])) {
        $msg = 'error:Cannot reassign driver — booking is already ' . strtolower($booking['bookingStatus']) . '.';
    } elseif ($driverId) {
        // Check for date conflicts with this driver's other bookings
        $newStart       = intval($booking['startDateMs'] ?? 0);
        $newEnd         = intval($booking['endDateMs']   ?? 0);
        $conflict       = null;
        $driverBookings = fb()->query('bookings', [['field' => 'driverId', 'op' => 'EQUAL', 'value' => $driverId]]);
        foreach ($driverBookings as $db) {
            $dbId = $db['bookingId'] ?? $db['id'] ?? '';
            if ($dbId === $bookingId) continue;
            $dbStatus = $db['bookingStatus'] ?? '';
            if (in_array($dbStatus, ['Cancelled', 'Completed'])) continue;
            $dbStart = intval($db['startDateMs'] ?? 0);
            $dbEnd   = intval($db['endDateMs']   ?? 0);
            if ($dbStart <= $newEnd && $dbEnd >= $newStart) { $conflict = $db; break; }
        }
        if ($conflict) {
            $msg = 'error:This driver is already booked from '
                . Firebase::msToDate($conflict['startDateMs'] ?? 0) . ' to '
                . Firebase::msToDate($conflict['endDateMs']   ?? 0) . '. Choose another driver or different dates.';
        } else {
            fb()->updateDoc('bookings', $bookingId, ['driverId' => $driverId, 'driverName' => $driverName]);
            $msg = 'success:Driver assigned successfully.';
        }
    } else {
        fb()->updateDoc('bookings', $bookingId, ['driverId' => '', 'driverName' => '']);
        $msg = 'success:Driver unassigned. Booking set to self-drive.';
    }
}

// Update booking status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $bookingId = trim($_POST['booking_id'] ?? '');
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowed = [
        'Upcoming'  => ['Ongoing', 'Cancelled'],
        'Ongoing'   => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];

    if (!in_array($newStatus, ['Ongoing','Completed','Cancelled'])) {
        $msg = 'error:Invalid status value.';
    } else {
        $booking = fb()->getDoc('bookings', $bookingId);
        if (!$booking || ($booking['vendorId'] ?? '') !== $provId) {
            $msg = 'error:Booking not found or does not belong to your fleet.';
        } elseif (!in_array($newStatus, $allowed[$booking['bookingStatus'] ?? ''] ?? [])) {
            $msg = 'error:Cannot change status from ' . ($booking['bookingStatus'] ?? '') . ' to ' . $newStatus . '.';
        } else {
            fb()->updateDoc('bookings', $bookingId, ['bookingStatus' => $newStatus]);
            $msg = 'success:Booking ' . ($newStatus === 'Ongoing' ? 'accepted' : strtolower($newStatus)) . ' successfully.';
        }
    }
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$fsStatusMap = ['Pending' => 'Upcoming', 'Active' => 'Ongoing', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
$filters = [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]];
$allOrders = fb()->query('bookings', $filters);
usort($allOrders, fn($a, $b) => intval($b['createdAt'] ?? 0) <=> intval($a['createdAt'] ?? 0));

if ($status_filter && isset($fsStatusMap[$status_filter])) {
    $fsTarget  = $fsStatusMap[$status_filter];
    $allOrders = array_values(array_filter($allOrders, fn($b) => ($b['bookingStatus'] ?? '') === $fsTarget));
}
if ($search) {
    $sq        = strtolower($search);
    $allOrders = array_values(array_filter($allOrders, fn($b) =>
        str_contains(strtolower($b['renterName']  ?? ''), $sq) ||
        str_contains(strtolower($b['vehicleName'] ?? ''), $sq)
    ));
}
$orders = $allOrders;

// Build driver schedule map for UI conflict highlighting (before display filters)
$driverSchedules = [];
foreach ($allOrders as $ao) {
    $did = $ao['driverId'] ?? '';
    if (!$did) continue;
    $aoStatus = $ao['bookingStatus'] ?? '';
    if (in_array($aoStatus, ['Cancelled', 'Completed'])) continue;
    $driverSchedules[$did][] = [
        'bookingId' => $ao['bookingId'] ?? $ao['id'] ?? '',
        'start'     => intval($ao['startDateMs'] ?? 0),
        'end'       => intval($ao['endDateMs']   ?? 0),
    ];
}

// Drivers for assignment dropdown (scoped to this vendor)
$drivers = fb()->query('drivers', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Rental orders</h1>
    <p class="page-subtitle"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> for your fleet</p>
  </div>
</div>

<?php if ($msg): [$mType,$mText] = explode(':',$msg,2); ?>
<div class="alert-tv <?= $mType ?>"><i class="bi bi-<?= $mType==='success'?'check':'exclamation' ?>-circle-fill"></i><?= htmlspecialchars($mText) ?></div>
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
      <thead>
        <tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Dates</th><th>Driver</th><th>Amount</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $o):
          $status     = $o['bookingStatus'] ?? 'Upcoming';
          $badgeCls   = Firebase::statusBadge($status);
          $statusLabel= Firebase::statusLabel($status);
          $start      = Firebase::msToDate($o['startDateMs'] ?? 0);
          $end        = Firebase::msToDate($o['endDateMs']   ?? 0);
          $bookingId  = $o['id'];
          $shortId    = substr($bookingId, 0, 8);
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue);font-family:monospace;font-size:12px"><?= htmlspecialchars($shortId) ?>…</strong></td>
          <td>
            <strong><?= htmlspecialchars($o['renterName'] ?? '—') ?></strong><br>
            <span style="font-size:11.5px;color:var(--text-secondary)"><?= htmlspecialchars($o['renterPhone'] ?? '') ?></span>
          </td>
          <td>
            <?= htmlspecialchars($o['vehicleName'] ?? '—') ?><br>
            <span class="badge-tv badge-complete" style="margin-top:2px"><?= htmlspecialchars($o['vehicleCategory'] ?? '') ?></span>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($start) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($end) ?></span>
          </td>
          <td>
            <?php if (!empty($o['driverName'])): ?>
              <span class="badge-tv badge-active"><?= htmlspecialchars($o['driverName']) ?></span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:12px">Unassigned</span>
            <?php endif; ?>
          </td>
          <td><?= !empty($o['totalPrice']) ? '<strong>₱'.number_format(floatval($o['totalPrice']),2).'</strong>' : '—' ?></td>
          <td><span class="badge-tv <?= $badgeCls ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
          <td>
            <div style="display:flex;gap:4px;align-items:center;flex-wrap:nowrap">
              <button class="btn-icon" title="Assign driver"
                onclick='openAssign(<?= json_encode(["booking_id"=>$bookingId,"driver_id"=>$o["driverId"]??"",'start'=>intval($o['startDateMs']??0),'end'=>intval($o['endDateMs']??0)]) ?>)'>
                <i class="bi bi-person-check"></i>
              </button>

              <?php if ($status === 'Upcoming'): ?>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($bookingId) ?>">
                <input type="hidden" name="new_status" value="Ongoing">
                <button type="submit" class="btn-icon" title="Accept order" style="color:#16A34A;border-color:#86EFAC">
                  <i class="bi bi-check-lg"></i>
                </button>
              </form>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($bookingId) ?>">
                <input type="hidden" name="new_status" value="Cancelled">
                <button type="submit" class="btn-icon danger" title="Decline order">
                  <i class="bi bi-x-lg"></i>
                </button>
              </form>

              <?php elseif ($status === 'Ongoing'): ?>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($bookingId) ?>">
                <input type="hidden" name="new_status" value="Completed">
                <button type="submit" class="btn-icon" title="Mark as completed" style="color:var(--tv-blue);border-color:#93C5FD">
                  <i class="bi bi-flag-fill"></i>
                </button>
              </form>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($bookingId) ?>">
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
        <input type="hidden" name="action"      value="assign_driver">
        <input type="hidden" name="booking_id"  id="fm_booking_id" value="">
        <input type="hidden" name="driver_name" id="fm_driver_name" value="">
        <label class="form-label-tv">Select driver</label>
        <select name="drive_id" id="fm_drive_id" class="tv-select"
                onchange="document.getElementById('fm_driver_name').value = this.options[this.selectedIndex].dataset.name || ''">
          <option value="" data-name="" data-base="— Self-drive (no driver) —">— Self-drive (no driver) —</option>
          <?php foreach ($drivers as $d):
            $dId   = $d['driverId'] ?? $d['id'];
            $dName = trim(($d['title'] ? $d['title'].' ' : '') . ($d['fullName'] ?? trim(($d['firstName']??'').' '.($d['lastName']??''))));
          ?>
          <option value="<?= htmlspecialchars($dId) ?>" data-name="<?= htmlspecialchars($dName) ?>" data-base="<?= htmlspecialchars($dName) ?>">
            <?= htmlspecialchars($dName) ?>
          </option>
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
const driverSchedules = <?= json_encode($driverSchedules) ?>;

function openAssign(data) {
  document.getElementById('assignTitle').textContent = 'Assign driver';
  document.getElementById('fm_booking_id').value = data.booking_id;

  // Mark drivers unavailable if they overlap with this booking's dates
  const sel = document.getElementById('fm_drive_id');
  Array.from(sel.options).forEach(opt => {
    const base = opt.dataset.base || opt.textContent;
    opt.dataset.base = base;
    if (!opt.value) { opt.disabled = false; opt.textContent = base; return; }
    const schedules = driverSchedules[opt.value] || [];
    const busy = schedules.some(s =>
      s.bookingId !== data.booking_id && s.start <= data.end && s.end >= data.start
    );
    opt.disabled = busy;
    opt.textContent = base + (busy ? ' — Unavailable' : '');
  });

  document.getElementById('fm_drive_id').value = data.driver_id || '';
  new bootstrap.Modal(document.getElementById('assignModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
