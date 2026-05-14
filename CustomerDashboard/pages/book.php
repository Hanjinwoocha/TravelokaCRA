<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Book a Car';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$isGuest = !empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in']);
$custId  = $_SESSION['customer_id'] ?? 0;

$carId = intval($_GET['car_id'] ?? 0);
if (!$carId) { header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit; }

// Fetch car details
try {
  $stmt = $pdo->prepare("SELECT c.*, cp.prov_name, cp.prov_withdriver, l.loctn_name FROM car c JOIN car_provider cp ON cp.prov_id=c.car_provid LEFT JOIN location l ON l.loctn_id=cp.prov_loctnid WHERE c.car_id=?");
  $stmt->execute([$carId]);
  $car = $stmt->fetch();
} catch (Exception $e) { $car = null; }

if (!$car) { header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit; }

// Fetch locations for dropdown
try { $locations = $pdo->query("SELECT * FROM location ORDER BY loctn_name")->fetchAll(); }
catch (Exception $e) { $locations = []; }

$error = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $pickup   = trim($_POST['rent_pickuplocation']  ?? '');
  $dropoff  = trim($_POST['rent_dropofflocation'] ?? '');
  $dateFrom = trim($_POST['rent_dateissued']      ?? '');
  $dateTo   = trim($_POST['rent_datedue']         ?? '');
  $flight   = trim($_POST['rent_flightnumber']    ?? '');
  $notes    = trim($_POST['rent_addnotes']        ?? '');
  $special  = trim($_POST['rent_specialrequest']  ?? '');
  $loctnId  = intval($_POST['rent_loctnid']       ?? 0);

  // Guest contact info (only when booking as guest with no cust_id yet)
  $gFirst  = trim($_POST['guest_firstname'] ?? '');
  $gLast   = trim($_POST['guest_lastname']  ?? '');
  $gEmail  = trim($_POST['guest_email']     ?? '');
  $gMobile = trim($_POST['guest_mobile']    ?? '');

  $today = date('Y-m-d');

  if (!$pickup || !$dropoff || !$dateFrom || !$dateTo || !$loctnId) {
    $error = 'Please fill in all required fields.';
  } elseif ($dateFrom < $today) {
    $error = 'Pickup date cannot be in the past.';
  } elseif ($dateTo <= $dateFrom) {
    $error = 'Return date must be after the pickup date.';
  } elseif ($isGuest && !$custId && (!$gFirst || !$gLast || !$gEmail || !$gMobile)) {
    $error = 'Please complete your contact information so we can send your e-ticket.';
  } else {
    // ── Conflict check: any non-cancelled/non-completed booking that overlaps these dates? ──
    $conflictStmt = $pdo->prepare("
      SELECT ro.rent_id, ro.rent_dateissued, ro.rent_datedue, et.tick_status
      FROM rental_order ro
      LEFT JOIN payment p   ON p.pay_rentid  = ro.rent_id
      LEFT JOIN eticket et  ON et.tick_payid = p.pay_id
      WHERE ro.rent_carid = ?
        AND ro.rent_dateissued <= ?
        AND ro.rent_datedue   >= ?
        AND (et.tick_status IS NULL OR et.tick_status NOT IN ('Cancelled', 'Completed'))
      ORDER BY ro.rent_dateissued ASC
      LIMIT 1
    ");
    $conflictStmt->execute([$carId, $dateTo, $dateFrom]);
    $conflict = $conflictStmt->fetch();
  }

  if (!$error && !empty($conflict)) {
    $error = 'This car is already booked from '
           . date('M j, Y', strtotime($conflict['rent_dateissued']))
           . ' to '
           . date('M j, Y', strtotime($conflict['rent_datedue']))
           . '. Please choose different dates or another car.';
  }

  if (!$error) {
    try {
      // Guests: create (or reuse) a customer row so the rental_order FK is satisfied.
      if ($isGuest && !$custId) {
        $chk = $pdo->prepare("SELECT cust_id FROM customer WHERE cust_email = ? LIMIT 1");
        $chk->execute([$gEmail]);
        if ($existing = $chk->fetch()) {
          $custId = $existing['cust_id'];
        } else {
          $pdo->prepare("INSERT INTO customer (cust_firstname, cust_lastname, cust_mobilenumber, cust_email, cust_password) VALUES (?,?,?,?,'')")
            ->execute([$gFirst, $gLast, $gMobile, $gEmail]);
          $custId = $pdo->lastInsertId();
        }
        $_SESSION['customer_id']   = $custId;
        $_SESSION['customer_name'] = $gFirst;
      }

      $stmt = $pdo->prepare("INSERT INTO rental_order (rent_pickuplocation, rent_dropofflocation, rent_flightnumber, rent_addnotes, rent_specialrequest, rent_dateissued, rent_datedue, rent_custid, rent_carid, rent_loctnid) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$pickup, $dropoff, $flight ?: null, $notes ?: null, $special ?: null, $dateFrom, $dateTo, $custId, $carId, $loctnId]);
      $rentId = $pdo->lastInsertId();
      header("Location: /Traveloka/CustomerDashboard/pages/payment.php?rent_id=$rentId");
      exit;
    } catch (Exception $e) {
      $error = 'Booking failed. Please try again.';
    }
  }
}

// Calculate days
$days = 1;
if (isset($_POST['rent_dateissued'], $_POST['rent_datedue'])) {
  $d1 = new DateTime($_POST['rent_dateissued']);
  $d2 = new DateTime($_POST['rent_datedue']);
  $days = max(1, $d2->diff($d1)->days);
}

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
    <div class="step-circle active">2</div>
    <span class="step-label active">Booking details</span>
  </div>
  <div class="step-line"></div>
  <div class="step-item">
    <div class="step-circle">3</div>
    <span class="step-label">Payment</span>
  </div>
  <div class="step-line"></div>
  <div class="step-item">
    <div class="step-circle">4</div>
    <span class="step-label">E-ticket</span>
  </div>
</div>

<?php if ($error): ?>
<div class="alert-tv error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Booking form -->
  <div class="col-lg-8">
    <div class="content-card">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Booking details</h2>
      </div>
      <div class="card-body-tv">
        <form method="post" id="bookingForm">
          <?php if ($isGuest && !$custId): ?>
          <div style="background:var(--tv-blue-light);border:1px solid #93C5FD;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px">
            <i class="bi bi-info-circle-fill" style="color:var(--tv-blue);font-size:18px;flex-shrink:0;margin-top:1px"></i>
            <div style="font-size:13px;color:var(--tv-blue-dark)">
              <strong>Booking as a guest.</strong> We'll send your e-ticket to the email below. <a href="/Traveloka/index.php" style="color:var(--tv-blue);font-weight:600;text-decoration:underline">Sign in instead</a> to save your bookings and unlock coupon codes.
            </div>
          </div>
          <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;margin-bottom:14px;color:var(--text-primary)">Your contact information</h3>
          <div class="row g-3" style="margin-bottom:24px">
            <div class="col-md-6">
              <label class="form-label-tv">First name *</label>
              <input type="text" name="guest_firstname" class="tv-input" required maxlength="50"
                     value="<?= htmlspecialchars($_POST['guest_firstname'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Last name *</label>
              <input type="text" name="guest_lastname" class="tv-input" required maxlength="50"
                     value="<?= htmlspecialchars($_POST['guest_lastname'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Email *</label>
              <input type="email" name="guest_email" class="tv-input" required maxlength="100"
                     value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Mobile number *</label>
              <input type="text" name="guest_mobile" class="tv-input" required maxlength="20"
                     value="<?= htmlspecialchars($_POST['guest_mobile'] ?? '') ?>">
            </div>
          </div>
          <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:700;margin-bottom:14px;color:var(--text-primary)">Booking details</h3>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-tv">Pickup date *</label>
              <input type="date" name="rent_dateissued" class="tv-input" required
                min="<?= date('Y-m-d') ?>"
                value="<?= htmlspecialchars($_POST['rent_dateissued'] ?? '') ?>"
                onchange="calcTotal()">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Return date *</label>
              <input type="date" name="rent_datedue" class="tv-input" required
                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                value="<?= htmlspecialchars($_POST['rent_datedue'] ?? '') ?>"
                onchange="calcTotal()">
            </div>
            <div class="col-12">
              <label class="form-label-tv">Pickup location (address) *</label>
              <input type="text" name="rent_pickuplocation" class="tv-input" required maxlength="200"
                placeholder="e.g. NAIA Terminal 3, Pasay City"
                value="<?= htmlspecialchars($_POST['rent_pickuplocation'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label-tv">Drop-off location (address) *</label>
              <input type="text" name="rent_dropofflocation" class="tv-input" required maxlength="200"
                placeholder="e.g. Mactan-Cebu International Airport"
                value="<?= htmlspecialchars($_POST['rent_dropofflocation'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label-tv">Area / Region *</label>
              <select name="rent_loctnid" class="tv-select" required>
                <option value="">Select region…</option>
                <?php foreach ($locations as $l): ?>
                <option value="<?= $l['loctn_id'] ?>" <?= ($_POST['rent_loctnid'] ?? '') == $l['loctn_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($l['loctn_name']) ?> (<?= htmlspecialchars($l['loctn_classification']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label-tv">Flight number <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional, for airport pickups)</span></label>
              <input type="text" name="rent_flightnumber" class="tv-input" maxlength="20"
                placeholder="e.g. PR100"
                value="<?= htmlspecialchars($_POST['rent_flightnumber'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label-tv">Special requests <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
              <textarea name="rent_specialrequest" class="tv-textarea" rows="2"
                placeholder="Child seat, wheelchair access, etc."><?= htmlspecialchars($_POST['rent_specialrequest'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label-tv">Additional notes <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
              <textarea name="rent_addnotes" class="tv-textarea" rows="2"
                placeholder="Any other notes for the provider"><?= htmlspecialchars($_POST['rent_addnotes'] ?? '') ?></textarea>
            </div>
          </div>

          <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap">
            <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-ghost">
              <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn-tv-primary">
              Continue to payment <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Car summary sidebar -->
  <div class="col-lg-4">
    <div class="content-card" style="position:sticky;top:80px">
      <div style="background:linear-gradient(135deg,var(--tv-blue-light),#F0F4FA);padding:28px;text-align:center;border-bottom:1px solid var(--border)">
        <i class="bi bi-car-front-fill" style="font-size:52px;color:var(--tv-blue)"></i>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:700;margin:12px 0 4px"><?= htmlspecialchars($car['car_model']) ?></h3>
        <span class="badge-tv badge-complete"><?= htmlspecialchars($car['car_type']) ?></span>
      </div>
      <div class="card-body-tv">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)"><i class="bi bi-building"></i> Provider</span>
            <strong><?= htmlspecialchars($car['prov_name']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)"><i class="bi bi-people"></i> Seats</span>
            <strong><?= $car['car_capacity'] ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)"><i class="bi bi-luggage"></i> Baggage</span>
            <strong><?= number_format($car['car_baggageload'],0) ?>kg</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)"><i class="bi bi-person-badge"></i> Driver</span>
            <strong><?= $car['prov_withdriver'] ? 'Available' : 'Self-drive' ?></strong>
          </div>
          <hr style="border:none;border-top:1px dashed var(--border);margin:4px 0">
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)">Rate/day</span>
            <strong style="color:var(--tv-blue)">₱<?= number_format($car['car_rentalrate'],2) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary)">Days</span>
            <strong id="days-count">—</strong>
          </div>
          <hr style="border:none;border-top:2px solid var(--border);margin:4px 0">
          <div style="display:flex;justify-content:space-between;font-size:15px">
            <span style="font-weight:700">Estimated total</span>
            <strong style="color:var(--tv-blue);font-size:18px" id="total-amount">₱<?= number_format($car['car_rentalrate'],2) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const rate = <?= floatval($car['car_rentalrate']) ?>;
function calcTotal() {
  const from = document.querySelector('[name="rent_dateissued"]').value;
  const to   = document.querySelector('[name="rent_datedue"]').value;
  if (from && to) {
    const d1 = new Date(from), d2 = new Date(to);
    const days = Math.max(1, Math.round((d2 - d1) / 86400000));
    document.getElementById('days-count').textContent   = days + ' day' + (days !== 1 ? 's' : '');
    document.getElementById('total-amount').textContent = '₱' + (rate * days).toLocaleString('en-PH', {minimumFractionDigits:2});
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>