<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'E-Ticket';
$activePage = 'home';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$custId = $_SESSION['customer_id'] ?? 0;

$tickId = intval($_GET['id'] ?? 0);
$isNew  = isset($_GET['new']);
if (!$tickId) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT et.tick_id, et.tick_dateissued, et.tick_qrcode, et.tick_status,
               p.pay_amount, p.pay_method, p.pay_datepaid, p.pay_couponcode, p.pay_discountamt,
               ro.rent_id, ro.rent_pickuplocation, ro.rent_dropofflocation,
               ro.rent_flightnumber, ro.rent_addnotes, ro.rent_specialrequest,
               ro.rent_dateissued, ro.rent_datedue,
               ca.car_model, ca.car_type, ca.car_capacity, ca.car_rentalrate,
               cp.prov_name, cp.prov_withdriver,
               l.loctn_name,
               dd.drive_firstname, dd.drive_lastname
        FROM eticket et
        JOIN payment p          ON p.pay_id    = et.tick_payid
        JOIN rental_order ro    ON ro.rent_id  = p.pay_rentid
        JOIN car ca             ON ca.car_id   = ro.rent_carid
        JOIN car_provider cp    ON cp.prov_id  = ca.car_provid
        LEFT JOIN location l    ON l.loctn_id  = ro.rent_loctnid
        LEFT JOIN driver_details dd ON dd.drive_id = ro.rent_driveid
        WHERE et.tick_id = ? AND ro.rent_custid = ?
    ");
    $stmt->execute([$tickId, $custId]);
    $t = $stmt->fetch();
} catch (Exception $e) { $t = null; }

if (!$t) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

$d1      = new DateTime($t['rent_dateissued']);
$d2      = new DateTime($t['rent_datedue']);
$days    = max(1, $d2->diff($d1)->days);

$statusCls = match($t['tick_status']) {
    'Active'    => 'badge-active',
    'Completed' => 'badge-complete',
    'Cancelled' => 'badge-cancel',
    default     => 'badge-pending'
};

$driver = ($t['drive_firstname'] ?? '') ? trim($t['drive_firstname'].' '.$t['drive_lastname']) : 'Self-drive';
$qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($t['tick_qrcode']);

include __DIR__ . '/../includes/header.php';
?>

<!-- Step bar -->
<div class="step-bar">
  <div class="step-item">
    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
    <span class="step-label done">Choose car</span>
  </div>
  <div class="step-line done"></div>
  <div class="step-item">
    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
    <span class="step-label done">Booking details</span>
  </div>
  <div class="step-line done"></div>
  <div class="step-item">
    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
    <span class="step-label done">Payment</span>
  </div>
  <div class="step-line done"></div>
  <div class="step-item">
    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
    <span class="step-label done">E-ticket</span>
  </div>
</div>

<?php if ($isNew): ?>
<div class="alert-tv success" style="margin-bottom:24px">
  <i class="bi bi-check-circle-fill"></i>
  <div>
    <strong>Booking confirmed!</strong> Your e-ticket has been issued. Show the QR code at pickup.
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
          <span class="badge-tv <?= $statusCls ?>"><?= htmlspecialchars($t['tick_status']) ?></span>
        </div>
        <div class="ticket-title">Car Rental E-Ticket</div>
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;margin-top:6px">
          <?= htmlspecialchars($t['car_model']) ?>
        </div>
        <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:4px">
          <?= htmlspecialchars($t['car_type']) ?> &middot; <?= htmlspecialchars($t['prov_name']) ?>
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
            <span><?= htmlspecialchars($t['tick_qrcode']) ?></span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);font-family:monospace;letter-spacing:.5px">
            <?= htmlspecialchars($t['tick_qrcode']) ?>
          </div>
        </div>

        <hr class="ticket-divider">

        <!-- Trip details -->
        <div class="ticket-row">
          <span class="ticket-row-label">Pickup date</span>
          <span class="ticket-row-value"><?= date('D, d M Y', strtotime($t['rent_dateissued'])) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Return date</span>
          <span class="ticket-row-value"><?= date('D, d M Y', strtotime($t['rent_datedue'])) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Duration</span>
          <span class="ticket-row-value"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?></span>
        </div>
        <?php if ($t['loctn_name']): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Region</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['loctn_name']) ?></span>
        </div>
        <?php endif; ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Pickup location</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['rent_pickuplocation']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Drop-off location</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['rent_dropofflocation']) ?></span>
        </div>
        <?php if ($t['rent_flightnumber']): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Flight number</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['rent_flightnumber']) ?></span>
        </div>
        <?php endif; ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Driver</span>
          <span class="ticket-row-value"><?= htmlspecialchars($driver) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Seats</span>
          <span class="ticket-row-value"><?= $t['car_capacity'] ?> passengers</span>
        </div>

        <hr class="ticket-divider">

        <!-- Payment -->
        <div class="ticket-row">
          <span class="ticket-row-label">Payment method</span>
          <span class="ticket-row-value"><?= htmlspecialchars($t['pay_method']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Rate / day</span>
          <span class="ticket-row-value">₱<?= number_format($t['car_rentalrate'], 2) ?></span>
        </div>
        <?php if ($t['pay_couponcode']): ?>
        <div class="ticket-row">
          <span class="ticket-row-label">Coupon</span>
          <span class="ticket-row-value" style="color:var(--tv-green)"><?= htmlspecialchars($t['pay_couponcode']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-row-label">Discount</span>
          <span class="ticket-row-value" style="color:var(--tv-green)">- ₱<?= number_format($t['pay_discountamt'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="ticket-row" style="border-bottom:none;padding-top:14px">
          <span class="ticket-row-label" style="font-size:14px;font-weight:700;color:var(--text-primary)">Total paid</span>
          <span class="ticket-row-value" style="font-size:18px;color:var(--tv-blue)">₱<?= number_format($t['pay_amount'], 2) ?></span>
        </div>

        <hr class="ticket-divider">

        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted)">
          <span>Booking #<?= $t['rent_id'] ?> &middot; Ticket #<?= $t['tick_id'] ?></span>
          <span>Issued <?= date('d M Y', strtotime($t['tick_dateissued'])) ?></span>
        </div>

        <?php if ($t['rent_specialrequest'] || $t['rent_addnotes']): ?>
        <div style="margin-top:14px;padding:12px;background:#F8FAFD;border-radius:8px;font-size:12.5px;color:var(--text-secondary)">
          <?php if ($t['rent_specialrequest']): ?>
          <div><strong>Special request:</strong> <?= htmlspecialchars($t['rent_specialrequest']) ?></div>
          <?php endif; ?>
          <?php if ($t['rent_addnotes']): ?>
          <div style="margin-top:4px"><strong>Notes:</strong> <?= htmlspecialchars($t['rent_addnotes']) ?></div>
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
        <a href="/Traveloka/CustomerDashboard/index.php" class="btn-tv-ghost" style="justify-content:center">
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
        <p style="margin:0 0 6px"><i class="bi bi-clock" style="color:var(--tv-blue)"></i> Ticket is <strong>Pending</strong> until the provider confirms.</p>
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
