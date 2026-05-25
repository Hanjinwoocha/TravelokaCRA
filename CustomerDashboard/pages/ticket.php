<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'E-Ticket';
$activePage = 'home';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$custId = $_SESSION['customer_id'] ?? '';

$bookingId = trim($_GET['id'] ?? '');
$isNew     = isset($_GET['new']);
if (!$bookingId) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

$t = fb()->getDoc('bookings', $bookingId);
if (!$t) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

$status    = $t['bookingStatus'] ?? 'Upcoming';
$statusCls = Firebase::statusBadge($status);
$qrUrl     = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($t['qrCode'] ?? $bookingId);

$start = Firebase::msToDate($t['startDateMs'] ?? 0);
$end   = Firebase::msToDate($t['endDateMs']   ?? 0);
$days  = intval($t['totalDays'] ?? 1);

include __DIR__ . '/../includes/header.php';
?>

<!-- Step bar -->
<div class="step-bar">
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Choose car</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Booking details</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Payment</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">E-ticket</span></div>
</div>

<?php if (!empty($t['isFlagged'])): ?>
<div class="alert-tv" style="background:#FEF3C7;border-color:#FDE68A;color:#78350F;margin-bottom:24px">
  <i class="bi bi-flag-fill" style="color:#D97706"></i>
  <div>
    <strong>This booking has been flagged for review by an administrator.</strong>
    <?php if (!empty($t['flagReason'])): ?>
      <div style="margin-top:4px;font-size:13px"><?= htmlspecialchars($t['flagReason']) ?></div>
    <?php endif; ?>
    <div style="margin-top:6px;font-size:12px;opacity:.75">If you believe this is a mistake, please contact support.</div>
  </div>
</div>
<?php endif; ?>

<?php if ($isNew): ?>
<div class="alert-tv" style="background:#FEF3C7;border-color:#FDE68A;color:#78350F;margin-bottom:24px">
  <i class="bi bi-clock-fill" style="color:#D97706"></i>
  <div>
    <strong>Booking submitted!</strong> Your e-ticket has been issued and is <strong>Upcoming</strong> — awaiting provider confirmation.
    <div style="margin-top:4px;font-size:12.5px;opacity:.85">Show the QR code to the driver at pickup. You'll be notified once the provider accepts.</div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4 justify-content-center">
  <!-- Ticket card -->
  <div class="col-lg-6">
    <div class="ticket-card">
      <div class="ticket-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div class="ticket-brand"><span style="color:var(--tv-orange)">t</span>raveloka</div>
          <span class="badge-tv <?= $statusCls ?>"><?= htmlspecialchars(Firebase::statusLabel($status)) ?></span>
        </div>
        <div class="ticket-title">Car Rental E-Ticket</div>
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;margin-top:6px">
          <?= htmlspecialchars($t['vehicleName'] ?? '') ?>
        </div>
        <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:4px">
          <?= htmlspecialchars($t['vendorName'] ?? '') ?>
        </div>
      </div>

      <div class="ticket-body">
        <!-- QR code -->
        <div style="text-align:center;margin-bottom:24px">
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" width="140" height="140"
               style="border:1px solid var(--border);border-radius:10px;display:block;margin:0 auto 8px"
               onerror="this.style.display='none';document.getElementById('qr-fallback').style.display='flex'">
          <div id="qr-fallback" class="ticket-qr" style="display:none">
            <i class="bi bi-qr-code"></i>
            <span><?= htmlspecialchars($t['qrCode'] ?? '') ?></span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);font-family:monospace;letter-spacing:.5px">
            <?= htmlspecialchars($t['qrCode'] ?? '') ?>
          </div>
        </div>

        <hr class="ticket-divider">

        <!-- Trip details -->
        <div class="ticket-row">
          <span class="ticket-row-label">Pickup date</span>
          <span class="ticket-row-value"><?= date('D, d M Y', strtotime($start)) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Return date</span>
          <span class="ticket-row-value"><?= date('D, d M Y', strtotime($end)) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Duration</span>
          <span class="ticket-row-value"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (!empty($t['locationName'])): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Region</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['locationName']) ?></span>
        </div>
        <?php endif; ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Pickup location</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['pickupLocation'] ?? '') ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Drop-off location</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['returnLocation'] ?? '') ?></span>
        </div>
        <?php if (!empty($t['flightNumber'])): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Flight number</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['flightNumber']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($t['driverName'])): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Driver</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['driverName']) ?></span>
        </div>
        <?php else: ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Driver</span>
          <span class="ticket-row-value">Self-drive</span>
        </div>
        <?php endif; ?>

        <hr class="ticket-divider">

        <!-- Payment -->
        <div class="ticket-row">
          <span class="ticket-row-label">Payment method</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['paymentMethod'] ?? '—') ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Rate / day</span>
          <span class="ticket-row-value">₱<?= number_format(floatval($t['pricePerDay'] ?? 0), 2) ?></span>
        </div>
        <?php if (!empty($t['promoCode'])): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Coupon</span>
          <span class="ticket-row-value" style="color:var(--tv-green)"><?= htmlspecialchars($t['promoCode']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Discount</span>
          <span class="ticket-row-value" style="color:var(--tv-green)">- ₱<?= number_format(floatval($t['discountAmount'] ?? 0), 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="ticket-row" style="border-bottom:none;padding-top:14px">
          <span class="ticket-row-label" style="font-size:14px;font-weight:700;color:var(--text-primary)">Total paid</span>
          <span class="ticket-row-value" style="font-size:18px;color:var(--tv-blue)">₱<?= number_format(floatval($t['totalPrice'] ?? 0), 2) ?></span>
        </div>

        <hr class="ticket-divider">

        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted)">
          <span>Booking <?= htmlspecialchars(substr($bookingId,0,12)) ?>…</span>
          <span>Issued <?= date('d M Y', intdiv(intval($t['paidAt'] ?? $t['createdAt'] ?? 0), 1000)) ?></span>
        </div>

        <?php if (!empty($t['specialRequest']) || !empty($t['addNotes'])): ?>
        <div style="margin-top:14px;padding:12px;background:#F8FAFD;border-radius:8px;font-size:12.5px;color:var(--text-secondary)">
          <?php if (!empty($t['specialRequest'])): ?>
          <div><strong>Special request:</strong> <?= htmlspecialchars($t['specialRequest']) ?></div>
          <?php endif; ?>
          <?php if (!empty($t['addNotes'])): ?>
          <div style="margin-top:4px"><strong>Notes:</strong> <?= htmlspecialchars($t['addNotes']) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Actions sidebar -->
  <div class="col-lg-3">
    <div class="content-card" style="position:sticky;top:80px">
      <div class="card-header-tv">
        <h2 class="card-title-tv" style="font-size:14px">Actions</h2>
      </div>
      <div class="card-body-tv" style="display:flex;flex-direction:column;gap:10px;padding:16px">
        <a href="/Traveloka/CustomerDashboard/pages/bookings.php" class="btn-tv-ghost" style="justify-content:center">
          <i class="bi bi-house"></i> My Bookings
        </a>
        <button onclick="window.print()" class="btn-tv-primary" style="justify-content:center;width:100%">
          <i class="bi bi-printer"></i> Print ticket
        </button>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-orange" style="justify-content:center">
          <i class="bi bi-plus-lg"></i> Book another
        </a>
      </div>
    </div>

    <div class="content-card" style="margin-top:0">
      <div class="card-body-tv" style="padding:16px;font-size:12.5px;color:var(--text-secondary);line-height:1.7">
        <p style="margin:0 0 8px"><strong style="color:var(--text-primary)">What happens next?</strong></p>
        <p style="margin:0 0 6px"><i class="bi bi-clock" style="color:var(--tv-blue)"></i> Ticket is <strong>Upcoming</strong> until the provider confirms.</p>
        <p style="margin:0 0 6px"><i class="bi bi-qr-code" style="color:var(--tv-blue)"></i> Show the QR code to the driver at pickup.</p>
        <p style="margin:0"><i class="bi bi-bookmark" style="color:var(--tv-blue)"></i> Bookmark this page for easy access.</p>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
  .cust-navbar,.cust-footer,.step-bar,.alert-tv,.col-lg-3 { display:none !important; }
  .col-lg-6 { width:100% !important; }
  .ticket-card { box-shadow:none; border:1px solid #ccc; }
  body { background:#fff; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
