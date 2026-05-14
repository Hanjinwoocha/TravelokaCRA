<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Payment';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$isGuest = !empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in']);
$custId  = $_SESSION['customer_id'] ?? 0;

$rentId = intval($_GET['rent_id'] ?? 0);
if (!$rentId) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

// Fetch rental order — must belong to this customer
try {
  $stmt = $pdo->prepare("
    SELECT ro.*, ca.car_model, ca.car_type, ca.car_rentalrate,
           cp.prov_name, p.pay_id
    FROM rental_order ro
    JOIN car ca ON ca.car_id = ro.rent_carid
    JOIN car_provider cp ON cp.prov_id = ca.car_provid
    LEFT JOIN payment p ON p.pay_rentid = ro.rent_id
    WHERE ro.rent_id = ? AND ro.rent_custid = ?
  ");
  $stmt->execute([$rentId, $custId]);
  $order = $stmt->fetch();
} catch (Exception $e) { $order = null; }

if (!$order) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }
if ($order['pay_id']) {
  // Already paid — go straight to ticket
  try {
    $tick = $pdo->prepare("SELECT tick_id FROM eticket WHERE tick_payid=?");
    $tick->execute([$order['pay_id']]);
    $tickRow = $tick->fetch();
    if ($tickRow) { header("Location: /Traveloka/CustomerDashboard/pages/ticket.php?id={$tickRow['tick_id']}"); exit; }
  } catch (Exception $e) {}
}

// Calculate total
$d1   = new DateTime($order['rent_dateissued']);
$d2   = new DateTime($order['rent_datedue']);
$days = max(1, $d2->diff($d1)->days);
$subtotal = $order['car_rentalrate'] * $days;

$error = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $method   = trim($_POST['pay_method']      ?? '');
  $coupon   = trim($_POST['pay_couponcode']  ?? '');
  $discount = floatval($_POST['pay_discount'] ?? 0);
  $total    = $subtotal - $discount;

  if (!$method) {
    $error = 'Please select a payment method.';
  } else {
    // ── Re-check conflict: did another customer pay for this car/date range first? ──
    $raceStmt = $pdo->prepare("
      SELECT ro2.rent_id, ro2.rent_dateissued, ro2.rent_datedue
      FROM rental_order ro2
      JOIN payment p2  ON p2.pay_rentid  = ro2.rent_id
      JOIN eticket et2 ON et2.tick_payid = p2.pay_id
      WHERE ro2.rent_carid = ?
        AND ro2.rent_id   != ?
        AND ro2.rent_dateissued <= ?
        AND ro2.rent_datedue   >= ?
        AND et2.tick_status IN ('Pending', 'Active')
      LIMIT 1
    ");
    $raceStmt->execute([$order['rent_carid'], $rentId, $order['rent_datedue'], $order['rent_dateissued']]);
    $race = $raceStmt->fetch();
  }

  if (!$error && !empty($race)) {
    $error = 'Sorry — this car was just booked by someone else for '
           . date('M j, Y', strtotime($race['rent_dateissued'])) . ' – '
           . date('M j, Y', strtotime($race['rent_datedue']))
           . '. Please go back and choose different dates or another car.';
  }

  if (!$error) {
    try {
      $pdo->beginTransaction();

      // Insert payment
      $pdo->prepare("INSERT INTO payment (pay_amount, pay_method, pay_datepaid, pay_couponcode, pay_discountamt, pay_rentid) VALUES (?,?,CURDATE(),?,?,?)")
        ->execute([$total, $method, $coupon ?: null, $discount ?: null, $rentId]);
      $payId = $pdo->lastInsertId();

      // Generate QR code string
      $qr = 'TRV-' . strtoupper(bin2hex(random_bytes(6))) . '-' . $rentId;

      // Insert e-ticket
      $pdo->prepare("INSERT INTO eticket (tick_dateissued, tick_qrcode, tick_status, tick_payid) VALUES (CURDATE(),?,'Pending',?)")
        ->execute([$qr, $payId]);
      $tickId = $pdo->lastInsertId();

      $pdo->commit();
      header("Location: /Traveloka/CustomerDashboard/pages/ticket.php?id=$tickId&new=1");
      exit;
    } catch (Exception $e) {
      $pdo->rollBack();
      $error = 'Payment failed. Please try again.';
    }
  }
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
    <div class="step-circle done"><i class="bi bi-check-lg"></i></div>
    <span class="step-label done">Booking details</span>
  </div>
  <div class="step-line done"></div>
  <div class="step-item">
    <div class="step-circle active">3</div>
    <span class="step-label active">Payment</span>
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
  <!-- Payment form -->
  <div class="col-lg-8">
    <div class="content-card">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Payment details</h2>
      </div>
      <div class="card-body-tv">
        <form method="post">
          <!-- Payment methods -->
          <div class="mb-4">
            <label class="form-label-tv">Payment method *</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:8px">
              <?php foreach (['GCash','Maya','Credit Card','Debit Card','Bank Transfer','Cash'] as $method): ?>
              <label style="cursor:pointer">
                <input type="radio" name="pay_method" value="<?= $method ?>" style="display:none" class="pay-radio">
                <div class="pay-option" style="border:1.5px solid var(--border);border-radius:10px;padding:14px 12px;text-align:center;transition:all .15s;font-size:13px;font-weight:600;color:var(--text-secondary)">
                  <?php $icons=['GCash'=>'bi-phone','Maya'=>'bi-phone-fill','Credit Card'=>'bi-credit-card','Debit Card'=>'bi-credit-card-2-front','Bank Transfer'=>'bi-bank','Cash'=>'bi-cash']; ?>
                  <i class="bi <?= $icons[$method] ?>" style="font-size:22px;display:block;margin-bottom:6px;color:var(--tv-blue)"></i>
                  <?= $method ?>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Coupon (registered customers only) -->
          <?php if ($isGuest): ?>
          <div class="mb-4" style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:13px 16px;display:flex;align-items:flex-start;gap:10px">
            <i class="bi bi-ticket-detailed" style="color:#92400E;font-size:18px;flex-shrink:0;margin-top:1px"></i>
            <div style="font-size:12.5px;color:#78350F;line-height:1.5">
              <strong>Coupon codes are for registered customers.</strong>
              <a href="/Traveloka/auth/customer_login.php?tab=register" style="color:#78350F;text-decoration:underline;font-weight:600">Create a free account</a> to unlock discounts on future bookings.
            </div>
            <input type="hidden" name="pay_discount" value="0">
          </div>
          <?php else: ?>
          <div class="mb-4">
            <label class="form-label-tv">Coupon code <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
            <div style="display:flex;gap:10px">
              <input type="text" name="pay_couponcode" id="couponInput" class="tv-input" placeholder="Enter coupon code" maxlength="50" style="flex:1">
              <button type="button" class="btn-tv-ghost" onclick="applyCoupon()" style="white-space:nowrap">Apply</button>
            </div>
            <input type="hidden" name="pay_discount" id="discountValue" value="0">
            <div id="couponMsg" style="font-size:12.5px;margin-top:6px;display:none"></div>
          </div>
          <?php endif; ?>

          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="javascript:history.back()" class="btn-tv-ghost">
              <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn-tv-primary" style="flex:1;justify-content:center">
              <i class="bi bi-lock-fill"></i> Confirm & pay ₱<span id="payBtn"><?= number_format($subtotal,2) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Order summary -->
  <div class="col-lg-4">
    <div class="content-card" style="position:sticky;top:80px">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Order summary</h2>
      </div>
      <div class="card-body-tv">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
          <div style="width:44px;height:44px;border-radius:10px;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
            <i class="bi bi-car-front"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($order['car_model']) ?></div>
            <div style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($order['car_type']) ?> &middot; <?= htmlspecialchars($order['prov_name']) ?></div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;font-size:13.5px">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Pickup date</span>
            <strong><?= htmlspecialchars($order['rent_dateissued']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Return date</span>
            <strong><?= htmlspecialchars($order['rent_datedue']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Duration</span>
            <strong><?= $days ?> day<?= $days!==1?'s':'' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Rate/day</span>
            <strong>₱<?= number_format($order['car_rentalrate'],2) ?></strong>
          </div>
          <hr style="border:none;border-top:1px dashed var(--border);margin:4px 0">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Subtotal</span>
            <strong>₱<?= number_format($subtotal,2) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between" id="discountRow" style="display:none">
            <span style="color:#15803D">Discount</span>
            <strong style="color:#15803D">- ₱<span id="discountDisplay">0.00</span></strong>
          </div>
          <hr style="border:none;border-top:2px solid var(--border);margin:4px 0">
          <div style="display:flex;justify-content:space-between;font-size:16px">
            <span style="font-weight:700">Total</span>
            <strong style="color:var(--tv-blue)">₱<span id="totalDisplay"><?= number_format($subtotal,2) ?></span></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const subtotal = <?= floatval($subtotal) ?>;

// Payment method selection styling
document.querySelectorAll('.pay-radio').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.pay-option').forEach(opt => {
      opt.style.borderColor = 'var(--border)';
      opt.style.background  = '';
      opt.style.color       = 'var(--text-secondary)';
    });
    const chosen = this.closest('label').querySelector('.pay-option');
    chosen.style.borderColor = 'var(--tv-blue)';
    chosen.style.background  = 'var(--tv-blue-light)';
    chosen.style.color       = 'var(--tv-blue)';
  });
});

// Demo coupon: TRAVEL10 = 10% off
function applyCoupon() {
  const code  = document.getElementById('couponInput').value.trim().toUpperCase();
  const msg   = document.getElementById('couponMsg');
  const coupons = { 'TRAVEL10': 0.10, 'SAVE20': 0.20, 'FIRST50': 0.50 };
  msg.style.display = 'block';
  if (coupons[code]) {
    const disc = parseFloat((subtotal * coupons[code]).toFixed(2));
    const total = parseFloat((subtotal - disc).toFixed(2));
    document.getElementById('discountValue').value        = disc;
    document.getElementById('discountDisplay').textContent = disc.toLocaleString('en-PH',{minimumFractionDigits:2});
    document.getElementById('totalDisplay').textContent   = total.toLocaleString('en-PH',{minimumFractionDigits:2});
    document.getElementById('payBtn').textContent         = total.toLocaleString('en-PH',{minimumFractionDigits:2});
    document.getElementById('discountRow').style.display  = 'flex';
    msg.style.color = '#15803D';
    msg.textContent = '✓ Coupon applied! ' + (coupons[code]*100) + '% discount';
  } else if (code) {
    msg.style.color = '#991B1B';
    msg.textContent = '✗ Invalid coupon code.';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>