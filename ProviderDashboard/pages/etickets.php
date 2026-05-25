<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'E-tickets';
$activePage = 'etickets';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? '';

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

$fsStatusMap = ['Pending' => 'Upcoming', 'Active' => 'Ongoing', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
$filters     = [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]];
$allBookings = fb()->query('bookings', $filters);
usort($allBookings, fn($a, $b) => intval($b['createdAt'] ?? 0) <=> intval($a['createdAt'] ?? 0));
$tickets     = array_values(array_filter($allBookings, fn($b) => !empty($b['qrCode'])));

if ($status_filter && isset($fsStatusMap[$status_filter])) {
    $fsTarget = $fsStatusMap[$status_filter];
    $tickets  = array_values(array_filter($tickets, fn($b) => ($b['bookingStatus'] ?? '') === $fsTarget));
}
if ($search) {
    $sq      = strtolower($search);
    $tickets = array_values(array_filter($tickets, fn($b) =>
        str_contains(strtolower($b['renterName']  ?? ''), $sq) ||
        str_contains(strtolower($b['vehicleName'] ?? ''), $sq) ||
        str_contains(strtolower($b['qrCode']      ?? ''), $sq)
    ));
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">E-tickets</h1>
    <p class="page-subtitle"><?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?> for your fleet</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All e-tickets</h2>
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
  <?php if (empty($tickets)): ?>
    <div class="empty-state"><i class="bi bi-ticket-perforated d-block"></i><p>No e-tickets found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>QR / Booking</th><th>Customer</th><th>Vehicle</th><th>Rental period</th><th>Amount</th><th>Issued</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t):
          $status   = $t['bookingStatus'] ?? 'Upcoming';
          $badgeCls = Firebase::statusBadge($status);
          $start    = Firebase::msToDate($t['startDateMs'] ?? 0);
          $end      = Firebase::msToDate($t['endDateMs']   ?? 0);
          $issued   = date('d M Y', intdiv(intval($t['paidAt'] ?? $t['createdAt'] ?? 0), 1000));
          $shortId  = substr($t['id'], 0, 8);
        ?>
        <tr>
          <td>
            <strong style="color:var(--tv-blue);font-family:monospace;font-size:12px"><?= htmlspecialchars($shortId) ?>…</strong><br>
            <span style="font-size:11px;color:var(--text-muted);font-family:monospace"><?= htmlspecialchars(substr($t['qrCode'] ?? '', 0, 20)) ?>…</span>
          </td>
          <td>
            <strong><?= htmlspecialchars($t['renterName'] ?? '—') ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($t['renterEmail'] ?? '') ?></span>
          </td>
          <td>
            <?= htmlspecialchars($t['vehicleName'] ?? '—') ?><br>
            <span class="badge-tv badge-complete" style="margin-top:2px"><?= htmlspecialchars($t['vehicleCategory'] ?? '') ?></span>
          </td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($start) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($end) ?></span>
          </td>
          <td><strong>₱<?= number_format(floatval($t['totalPrice'] ?? 0), 2) ?></strong></td>
          <td><?= htmlspecialchars($issued) ?></td>
          <td><span class="badge-tv <?= $badgeCls ?>"><?= htmlspecialchars(Firebase::statusLabel($status)) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
