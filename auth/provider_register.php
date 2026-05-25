<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/ProviderDashboard/index.php'); exit; }

require_once __DIR__ . '/../includes/firebase.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['prov_name']        ?? '');
    $country    = trim($_POST['prov_country']     ?? '');
    $citiesJson = trim($_POST['prov_cities']      ?? '[]');
    $cities     = json_decode($citiesJson, true);
    if (!is_array($cities)) $cities = [];
    $cities     = array_values(array_filter(array_map('trim', $cities)));
    $email      = trim($_POST['prov_email']       ?? '');
    $pass       = trim($_POST['password']         ?? '');
    $confirm    = trim($_POST['confirm_password'] ?? '');
    $withDriver = isset($_POST['prov_withdriver']);

    if (!$name || !$country || empty($cities) || !$email || !$pass) {
        $error = 'Please fill in all required fields (including at least one operating city).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $res = fb()->adminCreateUser($email, $pass, $name);

        if (isset($res['error'])) {
            $msg = $res['error']['message'] ?? '';
            $error = str_contains($msg, 'EMAIL_EXISTS')
                ? 'An account with this email already exists.'
                : 'Submission failed: ' . $msg;
        } else {
            $uid      = $res['localId'];
            $vendorId = fb()->newId();
            fb()->setDoc('vendors', $vendorId, [
                'vendorId'      => $vendorId,
                'ownerId'       => $uid,
                'businessName'  => $name,
                'country'       => $country,
                'locationName'  => $cities[0] ?? '',
                'coverageAreas' => $cities,
                'description'   => '',
                'logoUrl'       => '',
                'phone'         => '',
                'email'         => $email,
                'withDriver'    => $withDriver,
                'rating'        => 0.0,
                'totalReviews'  => 0,
                'isActive'      => false,
                'status'        => 'pending',
                'createdAt'     => Firebase::nowMs(),
            ]);
            header('Location: /Traveloka/index.php?applied=1'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Become a Provider — Traveloka Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    :root{--blue:#0064D2;--blue-dk:#004FAA;--orange:#FF6000;--navy:#001A3C;--border:#E4E8EF;--muted:#637083}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:url('https://images.unsplash.com/photo-1485291571150-772bcfc10da5?w=1800&q=80') center/cover no-repeat fixed;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    body::before{content:'';position:fixed;inset:0;background:linear-gradient(150deg,rgba(0,15,45,0.78) 0%,rgba(0,30,80,0.6) 55%,rgba(0,10,30,0.5) 100%)}
    .card-wrap{position:relative;z-index:1;background:#fff;border-radius:22px;box-shadow:0 24px 64px rgba(0,0,0,0.28);width:100%;max-width:1040px;display:flex;overflow:hidden;min-height:560px}
    .hero-panel{background:linear-gradient(145deg,var(--navy) 50%,#002B70);flex:0 0 320px;padding:48px 38px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
    .hero-panel::before{content:'';position:absolute;width:260px;height:260px;border-radius:50%;border:44px solid rgba(255,96,0,0.1);top:-80px;right:-80px}
    .hero-panel::after{content:'';position:absolute;width:180px;height:180px;border-radius:50%;background:rgba(0,100,210,0.1);bottom:-55px;left:-55px}
    .hp-brand{font-family:'Plus Jakarta Sans',sans-serif;font-weight:900;font-size:1.5rem;color:#fff;text-decoration:none;position:relative;z-index:1;letter-spacing:-.4px}
    .hp-brand .t{color:var(--orange)}
    .hp-body{position:relative;z-index:1}
    .hp-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.7rem;color:#fff;line-height:1.2;letter-spacing:-.4px;margin-bottom:11px}
    .hp-title span{color:var(--orange)}
    .hp-sub{font-size:13px;color:rgba(255,255,255,.48);line-height:1.7;margin-bottom:28px}
    .step{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
    .step-num{width:24px;height:24px;border-radius:50%;background:var(--orange);color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
    .step-text{font-size:13px;color:rgba(255,255,255,.7);line-height:1.5}
    .hp-foot{font-size:11px;color:rgba(255,255,255,.18);position:relative;z-index:1}
    .form-panel{flex:1;padding:42px 46px;display:flex;flex-direction:column;justify-content:center}
    .fp-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--orange);margin-bottom:8px}
    .fp-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.5rem;color:var(--navy);letter-spacing:-.3px;margin-bottom:4px}
    .fp-sub{font-size:13px;color:var(--muted);margin-bottom:20px}
    .err-box{background:#FEE2E2;border:1px solid #FCA5A5;color:#991B1B;border-radius:10px;padding:11px 14px;font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:16px}
    .f-label{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px}
    .f-input,.f-select{width:100%;height:40px;border:1.5px solid var(--border);border-radius:9px;padding:0 12px;font-size:13.5px;font-family:'Inter',sans-serif;color:#0D1B30;background:#FAFBFD;outline:none;transition:border-color .14s,box-shadow .14s}
    .f-input:focus,.f-select:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,100,210,.09)}
    .f-grid{display:grid;gap:12px}
    .g-3{grid-template-columns:1.4fr 1fr 1fr}
    .g-3col{grid-template-columns:1fr 1fr 1fr}
    .f-mb{margin-bottom:12px}
    .divider{border:none;border-top:1px solid var(--border);margin:16px 0}
    .section-label{font-size:10.5px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
    .check-row{display:flex;align-items:center;gap:10px;padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;cursor:pointer;transition:border-color .14s,background .14s}
    .check-row:hover{border-color:var(--blue);background:#F8FBFF}
    .check-row input{width:16px;height:16px;accent-color:var(--blue);cursor:pointer;flex-shrink:0}
    .check-row label{font-size:13px;font-weight:500;cursor:pointer;margin:0;line-height:1.4;color:#0D1B30}
    .check-row label small{color:var(--muted);font-size:11.5px;display:block;margin-top:1px}
    .btn-submit{width:100%;height:44px;background:var(--orange);color:#fff;border:none;border-radius:9px;font-size:14.5px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(255,96,0,.28);margin-top:18px;transition:background .14s,transform .1s}
    .btn-submit:hover{background:#e05500;transform:translateY(-1px)}
    .fp-foot{font-size:12.5px;color:var(--muted);text-align:center;margin-top:13px}
    .fp-foot a{color:var(--blue);font-weight:600;text-decoration:none}
    @media(max-width:860px){.hero-panel{display:none}.form-panel{padding:36px 28px}.g-3,.g-3col{grid-template-columns:1fr 1fr}}
    @media(max-width:480px){.g-3,.g-3col{grid-template-columns:1fr}.form-panel{padding:28px 20px}}
  </style>
</head>
<body>
<div class="card-wrap">
  <div class="hero-panel">
    <a href="/Traveloka/" class="hp-brand"><span class="t">t</span>raveloka</a>
    <div class="hp-body">
      <h2 class="hp-title">Become a<br><span>Provider.</span></h2>
      <p class="hp-sub">List your fleet and start earning. Applications are reviewed by our admin team within 24–48 hours.</p>
      <div class="step"><div class="step-num">1</div><div class="step-text">Fill in your company details and submit your application</div></div>
      <div class="step"><div class="step-num">2</div><div class="step-text">Our team reviews your application and verifies your fleet</div></div>
      <div class="step"><div class="step-num">3</div><div class="step-text">Get approved and start managing bookings from your dashboard</div></div>
    </div>
    <div class="hp-foot">&copy; <?= date('Y') ?> Traveloka Car Rental</div>
  </div>

  <div class="form-panel">
    <div class="fp-eyebrow">Provider Application</div>
    <h1 class="fp-title">Apply to join</h1>
    <p class="fp-sub">Tell us about your company. We'll review your application shortly.</p>

    <?php if ($error): ?>
    <div class="err-box"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="f-grid" style="grid-template-columns:1.4fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label class="f-label">Company / provider name *</label>
          <input type="text" name="prov_name" class="f-input" required maxlength="100"
                 value="<?= htmlspecialchars($_POST['prov_name'] ?? '') ?>">
        </div>
        <div>
          <label class="f-label">Country *</label>
          <select name="prov_country" id="country_select" class="f-select" required>
            <option value="">Loading countries…</option>
          </select>
        </div>
      </div>
      <div class="f-mb">
        <label class="f-label">Operating cities * <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(up to 5)</span></label>
        <input type="hidden" name="prov_cities" id="reg_cities_json" value="<?= htmlspecialchars(json_encode(array_values(array_filter(json_decode($_POST['prov_cities'] ?? '[]', true) ?? [])))) ?>">
        <select id="reg_city_select" class="f-select" style="margin-bottom:8px" disabled onchange="regAddCity(this.value)">
          <option value="">Select country first…</option>
        </select>
        <div id="reg_city_chips" style="display:flex;flex-wrap:wrap;gap:6px;min-height:24px"></div>
        <div style="font-size:11px;color:var(--muted);margin-top:5px">Choose cities where you operate. At least 1 required, up to 5.</div>
      </div>

      <div class="f-mb">
        <div class="check-row">
          <input type="checkbox" name="prov_withdriver" id="prov_withdriver" value="1"
                 <?= isset($_POST['prov_withdriver']) ? 'checked' : '' ?>>
          <label for="prov_withdriver">
            We offer with-driver rentals
            <small>Uncheck if you offer self-drive only</small>
          </label>
        </div>
      </div>

      <hr class="divider">
      <div class="section-label">Login credentials</div>

      <div class="f-grid g-3col f-mb">
        <div>
          <label class="f-label">Email address *</label>
          <input type="email" name="prov_email" class="f-input" required maxlength="100"
                 value="<?= htmlspecialchars($_POST['prov_email'] ?? '') ?>">
        </div>
        <div>
          <label class="f-label">Password *</label>
          <input type="password" name="password" class="f-input" required minlength="6">
        </div>
        <div>
          <label class="f-label">Confirm password *</label>
          <input type="password" name="confirm_password" class="f-input" required minlength="6">
        </div>
      </div>

      <button type="submit" class="btn-submit"><i class="bi bi-send"></i> Submit application</button>
    </form>

    <p class="fp-foot">Already have an account? <a href="/Traveloka/auth/signin.php">Sign in</a></p>
  </div>
</div>

<script>
const savedCountry = <?= json_encode($_POST['prov_country'] ?? '') ?>;
const MAX_CITIES = 5;
let regCities = JSON.parse(document.getElementById('reg_cities_json').value || '[]');

function regRenderChips() {
  const container = document.getElementById('reg_city_chips');
  container.innerHTML = regCities.map((c, i) =>
    `<span style="display:inline-flex;align-items:center;gap:5px;background:#EEF4FF;color:#0064D2;border:1px solid #BDD4F8;border-radius:20px;padding:4px 12px 4px 10px;font-size:12px;font-weight:600">
      <i class="bi bi-geo-alt-fill" style="font-size:10px"></i>${c}
      <button type="button" onclick="regRemoveCity(${i})" style="background:none;border:none;cursor:pointer;color:#637083;font-size:16px;line-height:1;padding:0;margin-left:2px">&times;</button>
    </span>`
  ).join('');
  document.getElementById('reg_cities_json').value = JSON.stringify(regCities);
}

function regAddCity(val) {
  if (!val) return;
  document.getElementById('reg_city_select').value = '';
  if (regCities.includes(val)) return;
  if (regCities.length >= MAX_CITIES) {
    alert('Maximum ' + MAX_CITIES + ' operating cities allowed.');
    return;
  }
  regCities.push(val);
  regRenderChips();
}

function regRemoveCity(idx) {
  regCities.splice(idx, 1);
  regRenderChips();
}

function loadCities(country, selectEl, onDone) {
  selectEl.innerHTML = '<option value="">Loading cities…</option>';
  selectEl.disabled = true;
  fetch('https://countriesnow.space/api/v0.1/countries/cities', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ country: country })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.error && Array.isArray(data.data) && data.data.length) {
      const sorted = data.data.slice().sort();
      selectEl.innerHTML = '<option value="">Select a city…</option>' +
        sorted.map(c => `<option value="${c}">${c}</option>`).join('');
      selectEl.disabled = false;
    } else {
      selectEl.innerHTML = '<option value="">No cities found for this country</option>';
    }
    if (onDone) onDone();
  })
  .catch(() => {
    selectEl.innerHTML = '<option value="">Failed to load cities</option>';
  });
}

// Load countries
fetch('https://restcountries.com/v3.1/all?fields=name')
  .then(r => r.json())
  .then(data => {
    const sel = document.getElementById('country_select');
    const names = data.map(c => c.name.common).sort();
    sel.innerHTML = '<option value="">Select country…</option>' +
      names.map(n => `<option value="${n}"${n === savedCountry ? ' selected' : ''}>${n}</option>`).join('');
    if (savedCountry) loadCities(savedCountry, document.getElementById('reg_city_select'));
  })
  .catch(() => {
    const sel = document.getElementById('country_select');
    const fallback = ['Australia','Canada','China','France','Germany','India','Indonesia','Japan','Malaysia','New Zealand','Philippines','Singapore','South Korea','Thailand','United Arab Emirates','United Kingdom','United States','Vietnam'];
    sel.innerHTML = '<option value="">Select country…</option>' +
      fallback.map(n => `<option value="${n}"${n === savedCountry ? ' selected' : ''}>${n}</option>`).join('');
    if (savedCountry) loadCities(savedCountry, document.getElementById('reg_city_select'));
  });

document.getElementById('country_select').addEventListener('change', function() {
  regCities = [];
  regRenderChips();
  const cityEl = document.getElementById('reg_city_select');
  if (!this.value) {
    cityEl.innerHTML = '<option value="">Select country first…</option>';
    cityEl.disabled = true;
    return;
  }
  loadCities(this.value, cityEl);
});

// Client-side guard before submit
document.querySelector('form').addEventListener('submit', function(e) {
  if (regCities.length === 0) {
    e.preventDefault();
    alert('Please select at least one operating city.');
  }
});

regRenderChips();
</script>
</body>
</html>
