<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'My profile';
$activePage = 'profile';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? '';
$msg = '';

// Fetch current vendor doc
$provider = fb()->getDoc('vendors', $provId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['prov_name']      ?? '');
    $country    = trim($_POST['prov_country']   ?? '');
    $citiesJson = trim($_POST['prov_cities']    ?? '[]');
    $cities     = json_decode($citiesJson, true);
    if (!is_array($cities)) $cities = [];
    $cities     = array_values(array_filter(array_map('trim', $cities)));
    // withDriver is read-only on this page — do not update it from POST
    fb()->updateDoc('vendors', $provId, [
        'businessName'  => $name,
        'country'       => $country,
        'locationName'  => $cities[0] ?? '',
        'coverageAreas' => $cities,
    ]);

    $_SESSION['provider_name']     = $name;
    $_SESSION['provider_location'] = $cities[0] ?? '';

    // Sync car availability: strip cities no longer in provider's coverageAreas
    $provCars      = fb()->query('vehicles', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);
    $deactivated   = 0;
    $citiesUpdated = 0;
    foreach ($provCars as $car) {
        $carCities = $car['availableCities'] ?? [];
        $valid     = array_values(array_intersect($carCities, $cities));
        $carId     = $car['vehicleId'] ?? $car['id'] ?? '';
        if (!$carId) continue;
        if (empty($valid)) {
            // No valid cities left — deactivate the car
            fb()->updateDoc('vehicles', $carId, ['availableCities' => [], 'isActive' => false]);
            $deactivated++;
        } elseif ($valid !== $carCities) {
            // Some cities removed but car still has coverage
            fb()->updateDoc('vehicles', $carId, ['availableCities' => $valid]);
            $citiesUpdated++;
        }
    }

    $msg = 'success:Profile updated successfully.';
    if ($deactivated > 0 || $citiesUpdated > 0) {
        $note = [];
        if ($citiesUpdated > 0) $note[] = $citiesUpdated . ' car' . ($citiesUpdated !== 1 ? 's' : '') . ' had their city list trimmed';
        if ($deactivated  > 0) $note[] = $deactivated  . ' car' . ($deactivated  !== 1 ? 's' : '') . ' deactivated (no remaining valid cities)';
        $msg = 'success:Profile updated. Note: ' . implode('; ', $note) . '.';
    }

    // Re-fetch
    $provider = fb()->getDoc('vendors', $provId);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">My profile</h1>
    <p class="page-subtitle">Manage your provider account details</p>
  </div>
</div>

<?php if ($msg): [$type,$text] = explode(':',$msg,2); ?>
<div class="alert-tv <?= $type ?>"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<?php if (!$provider): ?>
  <div class="empty-state"><i class="bi bi-exclamation-circle d-block"></i><p>Provider not found.</p></div>
<?php else: ?>

<div class="row g-3">
  <!-- Profile card -->
  <div class="col-lg-4">
    <div class="content-card" style="margin-bottom:0">
      <div class="card-body-tv" style="text-align:center;padding:32px 22px">
        <div style="width:72px;height:72px;border-radius:16px;background:var(--tv-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:700;margin:0 auto 16px">
          <?= strtoupper(substr($provider['businessName'] ?? '?', 0, 2)) ?>
        </div>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($provider['businessName'] ?? '') ?></h3>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"><?= htmlspecialchars($provider['country'] ?? '') ?></p>
        <div style="display:flex;flex-direction:column;gap:8px;text-align:left">
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-geo-alt" style="color:var(--tv-blue)"></i>
            <span><?= htmlspecialchars($provider['locationName'] ?? '—') ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-person-badge" style="color:var(--tv-blue)"></i>
            <span><?= !empty($provider['withDriver']) ? 'Offers with-driver service' : 'Self-drive only' ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-circle" style="color:var(--tv-blue)"></i>
            <span>Status: <strong><?= htmlspecialchars($provider['status'] ?? 'pending') ?></strong></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit form -->
  <div class="col-lg-8">
    <div class="content-card" style="margin-bottom:0">
      <div class="card-header-tv">
        <h2 class="card-title-tv">Edit profile</h2>
      </div>
      <div class="card-body-tv">
        <form method="post">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label-tv">Company name *</label>
              <input type="text" name="prov_name" class="tv-input" required maxlength="100" value="<?= htmlspecialchars($provider['businessName'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Country *</label>
              <select name="prov_country" id="country_select" class="tv-select" required>
                <option value="">Loading countries…</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Operating cities * <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(up to 5)</span></label>
              <input type="hidden" name="prov_cities" id="prof_cities_json"
                     value="<?= htmlspecialchars(json_encode(array_values($provider['coverageAreas'] ?? ($provider['locationName'] ? [$provider['locationName']] : [])))) ?>">
              <select id="prof_city_select" class="tv-select" style="margin-bottom:8px" disabled onchange="profAddCity(this.value)">
                <option value="">Select country first…</option>
              </select>
              <div id="prof_city_chips" style="display:flex;flex-wrap:wrap;gap:6px;min-height:24px"></div>
              <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block">At least 1 city required. Changing cities here affects car availability.</small>
            </div>
            <div class="col-12">
              <label class="form-label-tv">With-driver service</label>
              <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#F8FAFD;border:1px solid var(--border);border-radius:8px;font-size:13.5px">
                <?php if (!empty($provider['withDriver'])): ?>
                  <span class="badge-tv badge-active"><i class="bi bi-person-check"></i> Enabled</span>
                  <span style="color:var(--text-secondary)">Your fleet offers with-driver rental service.</span>
                <?php else: ?>
                  <span class="badge-tv badge-cancel"><i class="bi bi-person-x"></i> Disabled</span>
                  <span style="color:var(--text-secondary)">Your fleet is self-drive only.</span>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:11.5px;color:var(--text-muted);white-space:nowrap"><i class="bi bi-lock"></i> Contact admin to change</span>
              </div>
            </div>
            <div class="col-12" style="padding-top:8px">
              <button type="submit" class="btn-tv-primary">
                <i class="bi bi-check-lg"></i> Save changes
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const savedCountry = <?= json_encode($provider['country'] ?? '') ?>;
const MAX_CITIES = 5;
let profCities = JSON.parse(document.getElementById('prof_cities_json').value || '[]');

function profRenderChips() {
  const container = document.getElementById('prof_city_chips');
  container.innerHTML = profCities.map((c, i) =>
    `<span style="display:inline-flex;align-items:center;gap:5px;background:var(--tv-orange-light);color:var(--tv-orange);border:1px solid rgba(255,96,0,.25);border-radius:20px;padding:4px 12px 4px 10px;font-size:12px;font-weight:600">
      <i class="bi bi-geo-alt-fill" style="font-size:10px"></i>${c}
      <button type="button" onclick="profRemoveCity(${i})" style="background:none;border:none;cursor:pointer;color:#637083;font-size:16px;line-height:1;padding:0;margin-left:2px">&times;</button>
    </span>`
  ).join('');
  document.getElementById('prof_cities_json').value = JSON.stringify(profCities);
}

function profAddCity(val) {
  if (!val) return;
  document.getElementById('prof_city_select').value = '';
  if (profCities.includes(val)) return;
  if (profCities.length >= MAX_CITIES) {
    alert('Maximum ' + MAX_CITIES + ' operating cities allowed.');
    return;
  }
  profCities.push(val);
  profRenderChips();
}

function profRemoveCity(idx) {
  profCities.splice(idx, 1);
  profRenderChips();
}

function loadCities(country, selectEl) {
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
    if (savedCountry) loadCities(savedCountry, document.getElementById('prof_city_select'));
  })
  .catch(() => {
    const sel = document.getElementById('country_select');
    const fallback = ['Australia','Canada','China','France','Germany','India','Indonesia','Japan','Malaysia','New Zealand','Philippines','Singapore','South Korea','Thailand','United Arab Emirates','United Kingdom','United States','Vietnam'];
    sel.innerHTML = '<option value="">Select country…</option>' +
      fallback.map(n => `<option value="${n}"${n === savedCountry ? ' selected' : ''}>${n}</option>`).join('');
    if (savedCountry) loadCities(savedCountry, document.getElementById('prof_city_select'));
  });

document.getElementById('country_select').addEventListener('change', function() {
  profCities = [];
  profRenderChips();
  const cityEl = document.getElementById('prof_city_select');
  if (!this.value) {
    cityEl.innerHTML = '<option value="">Select country first…</option>';
    cityEl.disabled = true;
    return;
  }
  loadCities(this.value, cityEl);
});

// Guard on save
document.querySelector('form').addEventListener('submit', function(e) {
  if (profCities.length === 0) {
    e.preventDefault();
    alert('Please select at least one operating city.');
    return;
  }
  if (!confirm('Save changes? Note: removing cities may deactivate cars that are no longer available in any of your operating cities.')) {
    e.preventDefault();
  }
});

profRenderChips();
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
