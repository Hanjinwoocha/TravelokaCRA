<?php
require_once '../includes/db.php';
$pageTitle  = 'Payments';
$activePage = 'payments';

$search   = trim($_GET['q'] ?? '');
$bookings = fb()->query('bookings', [], ['field' => 'createdAt', 'dir' => 'DESCENDING']);

// Only bookings that have been paid (have paymentMethod set)
$payments = array_filter($bookings, fn($b) => !empty($b['paymentMethod']) && !empty($b['totalPrice']));

if ($search) {
    $sq = strtolower($search);
    $payments = array_filter($payments, fn($b) =>
        str_contains(strtolower($b['renterName']    ?? ''), $sq) ||
        str_contains(strtolower($b['paymentMethod'] ?? ''), $sq) ||
        str_contains(strtolower($b['promoCode']     ?? ''), $sq)
    );
}
$payments = array_values($payments);

$totalRevenue = array_sum(array_column($payments, 'totalPrice'));

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Payments</h1>
    <p class="page-subtitle">Total revenue: <strong style="color:var(--tv-blue)">₱<?= number_format($totalRevenue, 2) ?></strong></p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Payment records</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search payments…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($payments)): ?>
    <div class="empty-state"><i class="bi bi-credit-card d-block"></i><p>No payments found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Amount</th><th>Method</th><th>Promo</th><th>Discount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $b):
          $status  = $b['bookingStatus'] ?? 'Upcoming';
          $badgeCls = Firebase::statusBadge($status);
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue);font-family:monospace;font-size:11px"><?= htmlspecialchars(substr($b['id'],0,8)) ?>…</strong></td>
          <td>
            <strong><?= htmlspecialchars($b['renterName'] ?? '—') ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($b['renterEmail'] ?? '') ?></span>
          </td>
          <td><?= htmlspecialchars($b['vehicleName'] ?? '—') ?></td>
          <td><strong>₱<?= number_format(floatval($b['totalPrice'] ?? 0), 2) ?></strong></td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($b['paymentMethod'] ?? '—') ?></span></td>
          <td><?= !empty($b['promoCode']) ? htmlspecialchars($b['promoCode']) : '—' ?></td>
          <td><?= !empty($b['discountAmount']) && $b['discountAmount'] > 0 ? '₱'.number_format(floatval($b['discountAmount']),2) : '—' ?></td>
          <td><span class="badge-tv <?= $badgeCls ?>"><?= htmlspecialchars(Firebase::statusLabel($status)) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
