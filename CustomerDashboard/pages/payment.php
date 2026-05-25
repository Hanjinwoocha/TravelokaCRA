<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Payment';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$isGuest = !empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in']);
$custId  = $_SESSION['customer_id'] ?? '';

$bookingId = trim($_GET['booking_id'] ?? '');
if (!$bookingId) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

// Fetch booking
$booking = fb()->getDoc('bookings', $bookingId);
if (!$booking) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

// If already paid (has qrCode), go straight to ticket
if (!empty($booking['qrCode'])) {
    header("Location: /Traveloka/CustomerDashboard/pages/ticket.php?id=$bookingId"); exit;
}

$pricePerDay = floatval($booking['pricePerDay'] ?? 0);
$days        = intval($booking['totalDays'] ?? 1);
$subtotal    = $pricePerDay * $days;

$error = '';

// ── AJAX: validate coupon (called by JS applyCoupon()) ──────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'coupon') {
    header('Content-Type: application/json');
    if ($isGuest) {
        echo json_encode(['ok' => false, 'msg' => 'Promo codes are for registered customers only. Please log in or create an account.']);
        exit;
    }
    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!$code) {
        echo json_encode(['ok' => false, 'msg' => 'Please enter a coupon code.']);
        exit;
    }
    $promos = fb()->query('promos', [['field' => 'code', 'op' => 'EQUAL', 'value' => $code]]);
    if (empty($promos)) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid coupon code.']);
        exit;
    }
    $p = $promos[0];
    if (!($p['isActive'] ?? true)) {
        echo json_encode(['ok' => false, 'msg' => 'This coupon code is no longer active.']);
        exit;
    }
    $expiresAt = intval($p['expiresAt'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < Firebase::nowMs()) {
        echo json_encode(['ok' => false, 'msg' => 'This coupon code has expired.']);
        exit;
    }
    $maxUses  = intval($p['maxUses']   ?? 0);
    $usedCount = intval($p['usedCount'] ?? 0);
    if ($maxUses > 0 && $usedCount >= $maxUses) {
        echo json_encode(['ok' => false, 'msg' => 'This coupon code has reached its usage limit.']);
        exit;
    }
    // Calculate discount
    $discountType  = $p['discountType']  ?? 'percent';
    $discountValue = floatval($p['discountValue'] ?? 0);
    $discountAmount = $discountType === 'percent'
        ? round($subtotal * ($discountValue / 100), 2)
        : min(round($discountValue, 2), $subtotal);
    echo json_encode([
        'ok'       => true,
        'discount' => $discountAmount,
        'label'    => $discountType === 'percent'
            ? number_format($discountValue, 0) . '% off'
            : '₱' . number_format($discountValue, 2) . ' off',
        'msg'      => '✓ Coupon applied! ' . ($discountType === 'percent' ? number_format($discountValue,0).'%' : '₱'.number_format($discountValue,2)) . ' discount',
    ]);
    exit;
}
// ── END AJAX ─────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = trim($_POST['pay_method']     ?? '');
    $coupon = strtoupper(trim($_POST['pay_couponcode'] ?? ''));

    // Server-side promo re-validation (never trust client-side discount value)
    $discount = 0.0;
    if ($coupon && !$isGuest) {
        $promos = fb()->query('promos', [['field' => 'code', 'op' => 'EQUAL', 'value' => $coupon]]);
        if (!empty($promos)) {
            $p = $promos[0];
            $expiresAt  = intval($p['expiresAt']  ?? 0);
            $maxUses    = intval($p['maxUses']    ?? 0);
            $usedCount  = intval($p['usedCount']  ?? 0);
            $stillValid = ($p['isActive'] ?? true)
                && ($expiresAt === 0 || $expiresAt > Firebase::nowMs())
                && ($maxUses === 0 || $usedCount < $maxUses);
            if ($stillValid) {
                $discountType  = $p['discountType']  ?? 'percent';
                $discountValue = floatval($p['discountValue'] ?? 0);
                $discount = $discountType === 'percent'
                    ? round($subtotal * ($discountValue / 100), 2)
                    : min(round($discountValue, 2), $subtotal);
                $validPromoDoc = $p; // reference for usedCount increment later
            }
        }
    }
    $total = max(0, $subtotal - $discount);

    if (!$method) {
        $error = 'Please select a payment method.';
    }

    // Race condition check: another booking for same vehicle/dates got paid first?
    if (!$error) {
        $startMs = intval($booking['startDateMs'] ?? 0);
        $endMs   = intval($booking['endDateMs']   ?? 0);
        $vehicleId = $booking['vehicleId'] ?? '';

        $others = fb()->query('bookings', [
            ['field' => 'vehicleId', 'op' => 'EQUAL', 'value' => $vehicleId],
        ]);
        foreach ($others as $o) {
            if ($o['id'] === $bookingId) continue;
            if (in_array($o['bookingStatus'] ?? '', ['Cancelled', 'Completed'])) continue;
            if (empty($o['qrCode'])) continue; // not yet paid
            $oStart = intval($o['startDateMs'] ?? 0);
            $oEnd   = intval($o['endDateMs']   ?? 0);
            if ($oStart <= $endMs && $oEnd >= $startMs) {
                $error = 'Sorry — this car was just booked by someone else for '
                    . Firebase::msToDate($oStart) . ' – ' . Firebase::msToDate($oEnd)
                    . '. Please go back and choose different dates or another car.';
                break;
            }
        }
    }

    if (!$error) {
        $qr = 'TRV-' . strtoupper(bin2hex(random_bytes(6))) . '-' . substr($bookingId, 0, 6);
        fb()->updateDoc('bookings', $bookingId, [
            'paymentMethod'  => $method,
            'paymentStatus'  => 'Paid',
            'promoCode'      => $coupon,
            'discountAmount' => $discount,
            'subtotal'       => $subtotal,
            'totalPrice'     => $total,
            'qrCode'         => $qr,
            'bookingStatus'  => 'Upcoming',
            'paidAt'         => Firebase::nowMs(),
        ]);
        // Increment promo usedCount
        if (!empty($validPromoDoc)) {
            $newCount = intval($validPromoDoc['usedCount'] ?? 0) + 1;
            fb()->updateDoc('promos', $validPromoDoc['promoId'] ?? $validPromoDoc['id'], [
                'usedCount' => $newCount,
            ]);
        }
        header("Location: /Traveloka/CustomerDashboard/pages/ticket.php?id=$bookingId&new=1"); exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Step bar -->
<div class="step-bar">
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Choose car</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Booking details</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle active">3</div><span class="step-label active">Payment</span></div>
  <div class="step-line"></div>
  <div class="step-item"><div class="step-circle">4</div><span class="step-label">E-ticket</span></div>
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
              <?php foreach (['GCash','Maya','Credit Card','Debit Card','Bank Transfer','Cash'] as $meth): ?>
              <label style="cursor:pointer">
                <input type="radio" name="pay_method" value="<?= $meth ?>" style="display:none" class="pay-radio">
                <div class="pay-option" style="border:1.5px solid var(--border);border-radius:10px;padding:14px 12px;text-align:center;transition:all .15s;font-size:13px;font-weight:600;color:var(--text-secondary)">
                  <?php $icons=['GCash'=>'bi-phone','Maya'=>'bi-phone-fill','Credit Card'=>'bi-credit-card','Debit Card'=>'bi-credit-card-2-front','Bank Transfer'=>'bi-bank','Cash'=>'bi-cash']; ?>
                  <i class="bi <?= $icons[$meth] ?>" style="font-size:22px;display:block;margin-bottom:6px;color:var(--tv-blue)"></i>
                  <?= $meth ?>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Coupon -->
          <?php if ($isGuest): ?>
          <div class="mb-4" style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:13px 16px;display:flex;align-items:flex-start;gap:10px">
            <i class="bi bi-ticket-detailed" style="color:#92400E;font-size:18px;flex-shrink:0;margin-top:1px"></i>
            <div style="font-size:12.5px;color:#78350F;line-height:1.5">
              <strong>Coupon codes are for registered customers only.</strong><br>
              <a href="/Traveloka/auth/signin.php" style="color:#78350F;text-decoration:underline;font-weight:600">Sign in</a> or
              <a href="/Traveloka/auth/customer_login.php" style="color:#78350F;text-decoration:underline;font-weight:600">create a free account</a> to unlock discounts.
            </div>
            <input type="hidden" name="pay_couponcode" value="">
          </div>
          <?php else: ?>
          <div class="mb-4">
            <label class="form-label-tv">Coupon code <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
            <div style="display:flex;gap:10px">
              <input type="text" name="pay_couponcode" id="couponInput" class="tv-input" placeholder="Enter coupon code" maxlength="50"
                style="flex:1;text-transform:uppercase;font-family:monospace;letter-spacing:.5px"
                oninput="this.value=this.value.toUpperCase()">
              <button type="button" class="btn-tv-ghost" id="applyBtn" onclick="applyCoupon()" style="white-space:nowrap">Apply</button>
            </div>
            <div id="couponMsg" style="font-size:12.5px;margin-top:6px;display:none"></div>
          </div>
          <?php endif; ?>

          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="javascript:history.back()" class="btn-tv-ghost"><i class="bi bi-arrow-left"></i> Back</a>
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
            <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($booking['vehicleName'] ?? '') ?></div>
            <div style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($booking['vendorName'] ?? '') ?></div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;font-size:13.5px">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Pickup date</span>
            <strong><?= htmlspecialchars(Firebase::msToDate($booking['startDateMs'] ?? 0)) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Return date</span>
            <strong><?= htmlspecialchars(Firebase::msToDate($booking['endDateMs'] ?? 0)) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Duration</span>
            <strong><?= $days ?> day<?= $days!==1?'s':'' ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-secondary)">Rate/day</span>
            <strong>₱<?= number_format($pricePerDay,2) ?></strong>
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

async function applyCoupon() {
  const code    = document.getElementById('couponInput').value.trim().toUpperCase();
  const msg     = document.getElementById('couponMsg');
  const btn     = document.getElementById('applyBtn');
  if (!code) return;

  btn.disabled     = true;
  btn.textContent  = 'Checking…';
  msg.style.display = 'block';
  msg.style.color   = 'var(--text-muted)';
  msg.textContent   = 'Validating coupon…';

  try {
    const res  = await fetch(`?booking_id=<?= urlencode($bookingId) ?>&ajax=coupon&code=${encodeURIComponent(code)}`);
    const data = await res.json();

    if (data.ok) {
      const disc  = parseFloat(data.discount.toFixed(2));
      const total = parseFloat((subtotal - disc).toFixed(2));
      document.getElementById('discountDisplay').textContent  = disc.toLocaleString('en-PH',{minimumFractionDigits:2});
      document.getElementById('totalDisplay').textContent     = total.toLocaleString('en-PH',{minimumFractionDigits:2});
      document.getElementById('payBtn').textContent           = total.toLocaleString('en-PH',{minimumFractionDigits:2});
      document.getElementById('discountRow').style.display    = 'flex';
      msg.style.color   = '#15803D';
      msg.textContent   = data.msg;
      btn.textContent   = 'Applied ✓';
      btn.disabled      = true;
    } else {
      msg.style.color  = '#991B1B';
      msg.textContent  = '✗ ' + data.msg;
      btn.disabled     = false;
      btn.textContent  = 'Apply';
    }
  } catch(e) {
    msg.style.color  = '#991B1B';
    msg.textContent  = '✗ Could not validate coupon. Please try again.';
    btn.disabled     = false;
    btn.textContent  = 'Apply';
  }
}

// Allow Enter key in coupon input
const ci = document.getElementById('couponInput');
if (ci) ci.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); } });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
