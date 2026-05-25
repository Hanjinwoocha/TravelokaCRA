<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Book a Car';
$activePage = 'search';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['customer_logged_in']) && empty($_SESSION['is_guest'])) {
    header('Location: /Traveloka/index.php'); exit;
}
$isGuest = !empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in']);
$custId  = $_SESSION['customer_id'] ?? '';

$carId = trim($_GET['car_id'] ?? '');
if (!$carId) { header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit; }

// Fetch vehicle
$car = fb()->getDoc('vehicles', $carId);
if (!$car) { header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit; }

// Fetch vendor for driver info
$vendor = fb()->getDoc('vendors', $car['vendorId'] ?? '');

// Fetch locations
$locations = fb()->listDocs('locations');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickupStreet  = trim($_POST['pickup_street']   ?? '');
    $pickupCity    = trim($_POST['pickup_city']     ?? '');
    $dropoffStreet = trim($_POST['dropoff_street']  ?? '');
    $dropoffCity   = trim($_POST['dropoff_city']    ?? '');
    $pickup  = trim($pickupStreet  . ($pickupCity  ? ', '.$pickupCity  : ''));
    $dropoff = trim($dropoffStreet . ($dropoffCity ? ', '.$dropoffCity : ''));
    $dateFrom = trim($_POST['rent_dateissued']     ?? '');
    $dateTo   = trim($_POST['rent_datedue']        ?? '');
    $flight   = trim($_POST['rent_flightnumber']   ?? '');
    $notes    = trim($_POST['rent_addnotes']       ?? '');
    $special  = trim($_POST['rent_specialrequest'] ?? '');
    $loctnId  = trim($_POST['rent_loctnid'] ?? '');
    $loctnName = $loctnId; // city name is used directly as both ID and label

    $gFirst  = trim($_POST['guest_firstname'] ?? '');
    $gLast   = trim($_POST['guest_lastname']  ?? '');
    $gEmail  = trim($_POST['guest_email']     ?? '');
    $gMobile = trim($_POST['guest_mobile']    ?? '');

    $today = date('Y-m-d');

    if (!$pickupStreet || !$pickupCity || !$dropoffStreet || !$dropoffCity || !$dateFrom || !$dateTo || !$loctnId) {
        $error = 'Please fill in all required fields.';
    } elseif ($dateFrom < $today) {
        $error = 'Pickup date cannot be in the past.';
    } elseif ($dateTo <= $dateFrom) {
        $error = 'Return date must be after the pickup date.';
    } elseif ($isGuest && !$custId && (!$gFirst || !$gLast || !$gEmail || !$gMobile)) {
        $error = 'Please complete your contact information so we can send your e-ticket.';
    }

    // Conflict check via Firestore
    if (!$error) {
        $startMs = Firebase::dateToMs($dateFrom);
        $endMs   = Firebase::dateToMs($dateTo);

        $existingBookings = fb()->query('bookings', [
            ['field' => 'vehicleId', 'op' => 'EQUAL', 'value' => $carId],
        ]);
        foreach ($existingBookings as $b) {
            $bStatus = $b['bookingStatus'] ?? '';
            if (in_array($bStatus, ['Cancelled', 'Completed'])) continue;
            $bStart = intval($b['startDateMs'] ?? 0);
            $bEnd   = intval($b['endDateMs']   ?? 0);
            // Overlap: existing.start <= newEnd && existing.end >= newStart
            if ($bStart <= $endMs && $bEnd >= $startMs) {
                $error = 'This car is already booked from '
                    . Firebase::msToDate($bStart) . ' to ' . Firebase::msToDate($bEnd)
                    . '. Please choose different dates or another car.';
                break;
            }
        }
    }

    if (!$error) {
        // Guest: create user doc if needed
        if ($isGuest && !$custId) {
            $existingUsers = fb()->query('users', [['field' => 'email', 'op' => 'EQUAL', 'value' => $gEmail]]);
            if (!empty($existingUsers)) {
                $custId = $existingUsers[0]['id'];
            } else {
                $custId = fb()->newId();
                fb()->setDoc('users', $custId, [
                    'uid'      => $custId,
                    'role'     => 'customer',
                    'fullName' => trim($gFirst.' '.$gLast),
                    'email'    => $gEmail,
                    'phone'    => $gMobile,
                    'createdAt'=> Firebase::nowMs(),
                ]);
            }
            $_SESSION['customer_id']   = $custId;
            $_SESSION['customer_name'] = $gFirst;
        }

        // loctnName is the city name directly (set above from POST)

        // Calculate days
        $startMs = Firebase::dateToMs($dateFrom);
        $endMs   = Firebase::dateToMs($dateTo);
        $days    = max(1, intval(($endMs - $startMs) / 86400000));

        // Resolve renter info
        $user = $custId ? fb()->getDoc('users', $custId) : null;
        $renterName  = $user['fullName'] ?? trim($gFirst.' '.$gLast);
        $renterEmail = $user['email']    ?? $gEmail;
        $renterPhone = $user['phone']    ?? $gMobile;

        // Create preliminary booking (no payment yet)
        $bookingId = fb()->newId();
        fb()->setDoc('bookings', $bookingId, [
            'bookingId'          => $bookingId,
            'userId'             => $custId ?: 'guest',
            'vehicleId'          => $carId,
            'vendorId'           => $car['vendorId'] ?? '',
            'vehicleName'        => $car['name'] ?? $car['model'] ?? '',
            'vehicleImageUrl'    => ($car['imageUrls'] ?? [])[0] ?? '',
            'vendorName'         => $car['vendorName'] ?? ($vendor['businessName'] ?? ''),
            'renterName'         => $renterName,
            'renterPhone'        => $renterPhone,
            'renterEmail'        => $renterEmail,
            'licensePhotoUrl'    => '',
            'pickupLocation'     => $pickup,
            'returnLocation'     => $dropoff,
            'locationId'         => $loctnId,
            'locationName'       => $loctnName,
            'flightNumber'       => $flight,
            'addNotes'           => $notes,
            'specialRequest'     => $special,
            'startDateMs'        => $startMs,
            'endDateMs'          => $endMs,
            'totalDays'          => $days,
            'pricePerDay'        => floatval($car['pricePerDay'] ?? 0),
            'addOns'             => [],
            'promoCode'          => '',
            'discountAmount'     => 0.0,
            'subtotal'           => floatval($car['pricePerDay'] ?? 0) * $days,
            'totalPrice'         => floatval($car['pricePerDay'] ?? 0) * $days,
            'paymentMethod'      => '',
            'paymentStatus'      => 'Pending',
            'bookingStatus'      => 'Upcoming',
            'refundStatus'       => '',
            'cancellationPolicy' => 'Standard',
            'driverName'         => '',
            'driverId'           => '',
            'qrCode'             => '',
            'createdAt'          => Firebase::nowMs(),
        ]);
        header("Location: /Traveloka/CustomerDashboard/pages/payment.php?booking_id=$bookingId");
        exit;
    }
}

// Calculate preview days
$days = 1;
if (isset($_POST['rent_dateissued'], $_POST['rent_datedue'])) {
    $d1   = new DateTime($_POST['rent_dateissued']);
    $d2   = new DateTime($_POST['rent_datedue']);
    $days = max(1, $d2->diff($d1)->days);
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Step bar -->
<div class="step-bar">
  <div class="step-item"><div class="step-circle done"><i class="bi bi-check-lg"></i></div><span class="step-label done">Choose car</span></div>
  <div class="step-line done"></div>
  <div class="step-item"><div class="step-circle active">2</div><span class="step-label active">Booking details</span></div>
  <div class="step-line"></div>
  <div class="step-item"><div class="step-circle">3</div><span class="step-label">Payment</span></div>
  <div class="step-line"></div>
  <div class="step-item"><div class="step-circle">4</div><span class="step-label">E-ticket</span></div>
</div>

<?php if ($error): ?>
<div class="alert-tv error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <form method="post" id="bookingForm">

      <?php if ($isGuest && !$custId): ?>
      <div class="content-card">
        <div class="card-header-tv">
          <h2 class="card-title-tv"><i class="bi bi-person" style="color:var(--tv-blue)"></i> Your contact information</h2>
        </div>
        <div class="card-body-tv">
          <div class="alert-tv info" style="margin-bottom:20px">
            <i class="bi bi-info-circle-fill"></i>
            <span>Booking as a guest. We'll send your e-ticket to the email below.
              <a href="/Traveloka/auth/signin.php" style="color:var(--tv-blue);font-weight:600">Sign in instead</a>
              to save your bookings and unlock coupon codes.
            </span>
          </div>
          <div class="row g-3">
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
              <label class="form-label-tv">Email address *</label>
              <input type="email" name="guest_email" class="tv-input" required maxlength="100"
                     value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Mobile number *</label>
              <input type="text" name="guest_mobile" class="tv-input" required maxlength="20"
                     placeholder="09XXXXXXXXX"
                     value="<?= htmlspecialchars($_POST['guest_mobile'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Trip dates -->
      <div class="content-card">
        <div class="card-header-tv">
          <h2 class="card-title-tv"><i class="bi bi-calendar3" style="color:var(--tv-blue)"></i> Trip dates</h2>
        </div>
        <div class="card-body-tv">
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
            <div class="col-md-6">
              <label class="form-label-tv">Area / City *</label>
              <select name="rent_loctnid" class="tv-select" required>
                <option value="">Select city…</option>
                <?php foreach ($car['availableCities'] ?? [] as $city): ?>
                <option value="<?= htmlspecialchars($city) ?>"
                        <?= ($_POST['rent_loctnid'] ?? '') === $city ? 'selected' : '' ?>>
                  <?= htmlspecialchars($city) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Flight number <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
              <input type="text" name="rent_flightnumber" class="tv-input" maxlength="20"
                placeholder="e.g. PR100"
                value="<?= htmlspecialchars($_POST['rent_flightnumber'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Pickup location -->
      <div class="content-card">
        <div class="card-header-tv">
          <h2 class="card-title-tv"><i class="bi bi-geo-alt" style="color:var(--tv-blue)"></i> Pickup location</h2>
        </div>
        <div class="card-body-tv">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label-tv">Street address / Landmark *</label>
              <input type="text" name="pickup_street" class="tv-input" required maxlength="150"
                placeholder="e.g. NAIA Terminal 3, Andrews Ave."
                value="<?= htmlspecialchars($_POST['pickup_street'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label-tv">Barangay / District *</label>
              <input type="text" name="pickup_city" class="tv-input" required maxlength="100"
                placeholder="e.g. Barangay 183, Pasay"
                value="<?= htmlspecialchars($_POST['pickup_city'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Drop-off location -->
      <div class="content-card">
        <div class="card-header-tv">
          <h2 class="card-title-tv"><i class="bi bi-geo-alt-fill" style="color:var(--tv-orange)"></i> Drop-off location</h2>
        </div>
        <div class="card-body-tv">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label-tv">Street address / Landmark *</label>
              <input type="text" name="dropoff_street" class="tv-input" required maxlength="150"
                placeholder="e.g. Mactan-Cebu International Airport"
                value="<?= htmlspecialchars($_POST['dropoff_street'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label-tv">Barangay / District *</label>
              <input type="text" name="dropoff_city" class="tv-input" required maxlength="100"
                placeholder="e.g. Lahug, Cebu City"
                value="<?= htmlspecialchars($_POST['dropoff_city'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="content-card">
        <div class="card-header-tv">
          <h2 class="card-title-tv"><i class="bi bi-chat-text" style="color:var(--tv-blue)"></i> Additional notes <span style="font-family:'DM Sans',sans-serif;font-weight:400;font-size:13px;color:var(--text-muted)">(optional)</span></h2>
        </div>
        <div class="card-body-tv">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label-tv">Special requests</label>
              <textarea name="rent_specialrequest" class="tv-textarea" rows="2"
                placeholder="Child seat, wheelchair access, extra luggage space…"><?= htmlspecialchars($_POST['rent_specialrequest'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label-tv">Notes for the provider</label>
              <textarea name="rent_addnotes" class="tv-textarea" rows="2"
                placeholder="Any other instructions or information for the provider…"><?= htmlspecialchars($_POST['rent_addnotes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="/Traveloka/CustomerDashboard/pages/search.php" class="btn-tv-ghost">
          <i class="bi bi-arrow-left"></i> Back to search
        </a>
        <button type="submit" class="btn-tv-primary">
          Continue to payment <i class="bi bi-arrow-right"></i>
        </button>
      </div>
    </form>
  </div>

  <!-- Car summary sidebar -->
  <div class="col-lg-4">
    <div class="content-card" style="position:sticky;top:80px">
      <?php $carImg = ($car['imageUrls'] ?? [])[0] ?? ''; ?>
      <?php if ($carImg): ?>
      <div style="position:relative;overflow:hidden;border-bottom:1px solid var(--border)">
        <img src="<?= htmlspecialchars($carImg) ?>" alt="<?= htmlspecialchars($car['name'] ?? '') ?>"
             style="width:100%;height:180px;object-fit:cover;display:block">
        <div style="position:absolute;bottom:0;left:0;right:0;padding:16px 20px;background:linear-gradient(to top,rgba(0,0,0,.75),transparent)">
          <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:#fff;margin:0 0 6px"><?= htmlspecialchars($car['name'] ?? $car['model'] ?? '') ?></h3>
          <span class="badge-tv badge-complete"><?= htmlspecialchars($car['category'] ?? '') ?></span>
        </div>
      </div>
      <?php else: ?>
      <div style="background:linear-gradient(135deg,#001232 0%,#002B70 100%);padding:32px;text-align:center;border-bottom:1px solid var(--border);position:relative;overflow:hidden">
        <div style="position:absolute;top:-40px;right:-40px;width:150px;height:150px;border-radius:50%;border:30px solid rgba(255,96,0,.1)"></div>
        <i class="bi bi-car-front-fill" style="font-size:56px;color:rgba(255,255,255,.9);position:relative;z-index:1"></i>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:#fff;margin:14px 0 6px;position:relative;z-index:1"><?= htmlspecialchars($car['name'] ?? $car['model'] ?? '') ?></h3>
        <span class="badge-tv badge-complete" style="position:relative;z-index:1"><?= htmlspecialchars($car['category'] ?? '') ?></span>
      </div>
      <?php endif; ?>
      <div class="card-body-tv">
        <div style="display:flex;flex-direction:column;gap:11px;margin-bottom:20px">
          <?php foreach ([
            ['bi-building',          'Provider',     htmlspecialchars($car['vendorName'] ?? '')],
            ['bi-people',            'Seats',        intval($car['seatingCapacity'] ?? 0).' seats'],
            ['bi-luggage',           'Baggage',      number_format(floatval($car['baggageLoad'] ?? 0),0).' kg'],
            ['bi-gear',              'Transmission', htmlspecialchars($car['transmission'] ?? 'Automatic')],
            ['bi-person-badge',      'Driver',       !empty($car['withDriver']) ? 'Available' : 'Self-drive'],
          ] as [$icon,$label,$val]): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:13.5px">
            <span style="color:var(--text-secondary);display:flex;align-items:center;gap:6px">
              <i class="bi <?= $icon ?>" style="width:16px;text-align:center;color:var(--tv-blue)"></i> <?= $label ?>
            </span>
            <strong><?= $val ?></strong>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="background:#F8FAFD;border:1px solid var(--border);border-radius:10px;padding:16px">
          <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:10px">
            <span style="color:var(--text-secondary)">Rate per day</span>
            <strong style="color:var(--tv-blue)">₱<?= number_format(floatval($car['pricePerDay'] ?? 0),2) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:12px">
            <span style="color:var(--text-secondary)">Duration</span>
            <strong id="days-count" style="color:var(--text-primary)">— days</strong>
          </div>
          <div style="border-top:2px dashed var(--border);padding-top:12px;display:flex;justify-content:space-between;align-items:baseline">
            <span style="font-size:13px;font-weight:700;color:var(--text-primary)">Estimated total</span>
            <strong id="total-amount" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--tv-blue)">₱<?= number_format(floatval($car['pricePerDay'] ?? 0),2) ?></strong>
          </div>
        </div>
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:12px;text-align:center;line-height:1.5">
          <i class="bi bi-shield-check" style="color:var(--tv-green)"></i>
          Final amount confirmed at payment step
        </p>
      </div>
    </div>
  </div>
</div>

<script>
const rate = <?= floatval($car['pricePerDay'] ?? 0) ?>;
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
