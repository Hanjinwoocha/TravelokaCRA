<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'Fleet management';
$activePage = 'fleet';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId       = $_SESSION['provider_id'] ?? '';
$provName     = $_SESSION['provider_name'] ?? '';
$msg          = '';
$provLocation = $_SESSION['provider_location'] ?? '';

// Fetch vendor doc early — needed for withDriver check and city checkboxes
$vendor       = fb()->getDoc('vendors', $provId);
$provCities   = $vendor['coverageAreas'] ?? ($provLocation ? [$provLocation] : []);
$provWithDriver = !empty($vendor['withDriver']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? '';
    $carName      = trim($_POST['car_model']        ?? '');
    $carType      = trim($_POST['car_type']         ?? '');
    $capacity     = intval($_POST['car_capacity']   ?? 0);
    $baggage      = floatval($_POST['car_baggageload'] ?? 0);
    $rate         = floatval($_POST['car_rentalrate']  ?? 0);
    $withDriver   = $provWithDriver && !empty($_POST['car_withdriver']);
    $transmission = trim($_POST['car_transmission'] ?? 'Automatic');
    // availableCities: comma-separated input → array
    $cities       = array_values(array_filter(array_map('trim', $_POST['car_cities'] ?? [])));
    $imageUrl     = trim($_POST['car_image_url'] ?? '');

    if ($action === 'create') {
        $vehicleId = fb()->newId();
        fb()->setDoc('vehicles', $vehicleId, [
            'vehicleId'          => $vehicleId,
            'name'               => $carName,
            'brand'              => '',
            'model'              => $carName,
            'year'               => 0,
            'category'           => $carType,
            'transmission'       => $transmission,
            'seatingCapacity'    => $capacity,
            'baggageLoad'        => $baggage,
            'pricePerDay'        => $rate,
            'withDriver'         => $withDriver,
            'vendorId'           => $provId,
            'vendorName'         => $provName,
            'availableCities'    => $cities,
            'imageUrls'          => $imageUrl ? [$imageUrl] : [],
            'features'           => [],
            'freeCancellation'   => false,
            'insuranceIncluded'  => false,
            'description'        => '',
            'isActive'           => true,
            'popularity'         => 0,
            'rating'             => 0.0,
            'totalReviews'       => 0,
            'createdAt'          => Firebase::nowMs(),
        ]);
        $msg = 'success:Car added to your fleet.';
    } elseif ($action === 'update') {
        $vehicleId = trim($_POST['car_id'] ?? '');
        $car = fb()->getDoc('vehicles', $vehicleId);
        if ($car && ($car['vendorId'] ?? '') === $provId) {
            $updateData = [
                'name'            => $carName,
                'model'           => $carName,
                'category'        => $carType,
                'transmission'    => $transmission,
                'seatingCapacity' => $capacity,
                'baggageLoad'     => $baggage,
                'pricePerDay'     => $rate,
                'withDriver'      => $withDriver,
                'availableCities' => $cities,
            ];
            // Re-activate car if cities are provided (may have been deactivated by profile city removal)
            if (!empty($cities)) $updateData['isActive'] = true;
            if ($imageUrl) $updateData['imageUrls'] = [$imageUrl];
            fb()->updateDoc('vehicles', $vehicleId, $updateData);
            $msg = 'success:Car updated.';
        }
    } elseif ($action === 'delete') {
        $vehicleId = trim($_POST['car_id'] ?? '');
        $car = fb()->getDoc('vehicles', $vehicleId);
        if ($car && ($car['vendorId'] ?? '') === $provId) {
            fb()->updateDoc('vehicles', $vehicleId, ['isActive' => false]);
            $msg = 'success:Car removed from fleet.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$cars   = fb()->query('vehicles', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $provId]]);
if ($search) {
    $sq   = strtolower($search);
    $cars = array_values(array_filter($cars, fn($c) =>
        str_contains(strtolower($c['name'] ?? ''), $sq) ||
        str_contains(strtolower($c['category'] ?? ''), $sq)
    ));
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Fleet management</h1>
    <p class="page-subtitle"><?= count($cars) ?> car<?= count($cars) !== 1 ? 's' : '' ?> in your fleet</p>
  </div>
  <button class="btn-tv-orange" data-bs-toggle="modal" data-bs-target="#carModal" onclick="prepareAdd()"><i class="bi bi-plus-lg"></i> Add car</button>
</div>

<?php if ($msg): [$type,$text] = explode(':',$msg,2); ?>
<div class="alert-tv <?= $type ?>"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Your cars</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search cars…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($cars)): ?>
    <div class="empty-state"><i class="bi bi-car-front d-block"></i><p>No cars yet. Add your first car.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Model</th><th>Type</th><th>Trans.</th><th>Seats</th><th>Baggage (kg)</th><th>Rate/day</th><th>Driver</th><th>Active</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cars as $c):
          $carId = $c['vehicleId'] ?? $c['id'];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php $thumb = ($c['imageUrls'] ?? [])[0] ?? ''; ?>
              <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                     style="width:44px;height:34px;object-fit:cover;border-radius:6px;flex-shrink:0;border:1px solid var(--border)">
              <?php else: ?>
                <div style="width:44px;height:34px;border-radius:6px;background:var(--tv-orange-light);color:var(--tv-orange);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <i class="bi bi-car-front"></i>
                </div>
              <?php endif; ?>
              <strong><?= htmlspecialchars($c['name'] ?? '') ?></strong>
            </div>
          </td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($c['category'] ?? '') ?></span></td>
          <td><?= htmlspecialchars($c['transmission'] ?? 'Auto') ?></td>
          <td><?= intval($c['seatingCapacity'] ?? 0) ?> seats</td>
          <td><?= number_format(floatval($c['baggageLoad'] ?? 0),1) ?> kg</td>
          <td><strong style="color:var(--tv-blue)">₱<?= number_format(floatval($c['pricePerDay'] ?? 0),2) ?></strong></td>
          <td><?= !empty($c['withDriver']) ? '<span class="badge-tv badge-active">Incl.</span>' : '<span style="color:var(--text-muted);font-size:12px">Self-drive</span>' ?></td>
          <td><?= !empty($c['isActive']) ? '<span class="badge-tv badge-active">Yes</span>' : '<span class="badge-tv badge-cancel">No</span>' ?></td>
          <td style="display:flex;gap:6px">
            <?php
              $citiesArr = $c['availableCities'] ?? [];
              $citiesStr = is_array($citiesArr) ? implode(', ', $citiesArr) : '';
            ?>
            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#carModal" onclick='prepareEdit(<?= json_encode(['car_id'=>$carId,'car_model'=>$c['name']??'','car_type'=>$c['category']??'','car_capacity'=>intval($c['seatingCapacity']??0),'car_baggageload'=>floatval($c['baggageLoad']??0),'car_rentalrate'=>floatval($c['pricePerDay']??0),'car_transmission'=>$c['transmission']??'Automatic','car_cities'=>$c['availableCities']??[],'car_withdriver'=>!empty($c['withDriver']),'car_image_url'=>($c['imageUrls']??[])[0]??'']) ?>)'><i class="bi bi-pencil"></i></button>
            <form id="del_car_<?= htmlspecialchars($carId) ?>" method="post" style="display:none">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="car_id" value="<?= htmlspecialchars($carId) ?>">
            </form>
            <button class="btn-icon danger" onclick="confirmDelete('car','del_car_<?= htmlspecialchars($carId) ?>',<?= json_encode($c['name']??'', JSON_HEX_APOS) ?>)"><i class="bi bi-trash3"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="carModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="carModalTitle">Add car</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="fm_action" value="create">
        <input type="hidden" name="car_id" id="fm_id" value="">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-tv">Model name *</label>
            <input type="text" name="car_model" id="fm_model" class="tv-input" required maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Car type *</label>
            <select name="car_type" id="fm_type" class="tv-select" required>
              <option value="">Select type…</option>
              <option>Sedan</option><option>SUV</option><option>Van</option>
              <option>MPV</option><option>Pickup</option><option>Hatchback</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Seat capacity *</label>
            <input type="number" name="car_capacity" id="fm_cap" class="tv-input" min="1" max="99" required>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Baggage load (kg) *</label>
            <input type="number" name="car_baggageload" id="fm_bag" class="tv-input" min="0" step="0.01" required>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Rate per day (₱) *</label>
            <input type="number" name="car_rentalrate" id="fm_rate" class="tv-input" min="0" step="0.01" required>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Transmission *</label>
            <select name="car_transmission" id="fm_transmission" class="tv-select" required>
              <option value="Automatic">Automatic</option>
              <option value="Manual">Manual</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label-tv">Available in</label>
            <?php if (empty($provCities)): ?>
              <div style="font-size:12.5px;color:var(--text-muted);padding:10px 12px;background:#F8FAFD;border:1px solid var(--border);border-radius:8px">
                <i class="bi bi-info-circle"></i> No operating cities set on your profile.
                <a href="/Traveloka/ProviderDashboard/pages/profile.php" style="color:var(--tv-blue);font-weight:600">Update profile first.</a>
              </div>
            <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:10px 20px" id="fm_cities_checks">
                <?php foreach ($provCities as $cityOpt):
                  $cid = 'city_' . preg_replace('/[^a-z0-9]/i', '_', $cityOpt);
                ?>
                <div class="form-check" style="margin:0">
                  <input class="form-check-input" type="checkbox" name="car_cities[]"
                         id="<?= htmlspecialchars($cid) ?>" value="<?= htmlspecialchars($cityOpt) ?>">
                  <label class="form-check-label" style="font-size:13.5px"
                         for="<?= htmlspecialchars($cid) ?>"><?= htmlspecialchars($cityOpt) ?></label>
                </div>
                <?php endforeach; ?>
              </div>
              <small style="color:var(--text-muted);font-size:11px;margin-top:5px;display:block">Tick the cities where this car will be available</small>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <?php if ($provWithDriver): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="car_withdriver" id="fm_withdriver" value="1">
              <label class="form-check-label" for="fm_withdriver" style="font-size:13.5px">Includes driver</label>
            </div>
            <?php else: ?>
            <div style="padding:8px 12px;background:#F8FAFD;border:1px solid var(--border);border-radius:8px;font-size:12.5px;color:var(--text-muted)">
              <i class="bi bi-info-circle"></i> With-driver service is not enabled for your account. All cars are self-drive only.
            </div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label-tv">Car photo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
            <input type="hidden" name="car_image_url" id="fm_image_url" value="">
            <!-- preview -->
            <div id="fm_img_preview" style="margin-bottom:8px"></div>
            <label id="fm_img_label" style="display:flex;align-items:center;gap:8px;cursor:pointer;background:#F8FAFD;border:2px dashed var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--text-secondary);transition:border-color .15s"
                   onmouseenter="this.style.borderColor='var(--tv-blue)'" onmouseleave="this.style.borderColor='var(--border)'">
              <i class="bi bi-cloud-upload" style="font-size:18px;color:var(--tv-blue)"></i>
              <span id="fm_img_label_text">Click to choose a photo</span>
              <input type="file" id="fm_img_file" accept="image/*" style="display:none" onchange="uploadCarImage(this)">
            </label>
            <div id="fm_img_status" style="font-size:12px;margin-top:5px;color:var(--text-muted)"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-orange"><i class="bi bi-check-lg"></i> Save car</button>
      </div>
    </form>
  </div>
</div>

<script>
const CLOUDINARY_URL    = 'https://api.cloudinary.com/v1_1/ddmves7uy/image/upload';
const CLOUDINARY_PRESET = 'traveloka_cars';

function resetImageField() {
  document.getElementById('fm_image_url').value    = '';
  document.getElementById('fm_img_preview').innerHTML = '';
  document.getElementById('fm_img_status').textContent = '';
  document.getElementById('fm_img_label_text').textContent = 'Click to choose a photo';
  document.getElementById('fm_img_file').value = '';
}

function setImagePreview(url) {
  document.getElementById('fm_image_url').value = url;
  document.getElementById('fm_img_preview').innerHTML =
    `<img src="${url}" alt="Car photo" style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid var(--border);margin-bottom:4px">`;
  document.getElementById('fm_img_label_text').textContent = 'Replace photo';
}

async function uploadCarImage(input) {
  const file = input.files[0];
  if (!file) return;
  const status = document.getElementById('fm_img_status');
  status.style.color = 'var(--text-muted)';
  status.textContent = 'Uploading…';
  try {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('upload_preset', CLOUDINARY_PRESET);
    const res  = await fetch(CLOUDINARY_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.secure_url) {
      setImagePreview(data.secure_url);
      status.style.color = '#16A34A';
      status.textContent = '✓ Photo uploaded successfully';
    } else {
      status.style.color = '#DC2626';
      status.textContent = 'Upload failed. Please try again.';
    }
  } catch (e) {
    status.style.color = '#DC2626';
    status.textContent = 'Upload error. Check your connection.';
  }
}

function setCityChecks(selectedCities) {
  const checks = document.querySelectorAll('#fm_cities_checks input[type=checkbox]');
  checks.forEach(cb => { cb.checked = selectedCities.includes(cb.value); });
}
function prepareAdd() {
  document.getElementById('carModalTitle').textContent = 'Add car';
  document.getElementById('fm_action').value = 'create';
  ['fm_id','fm_model','fm_cap','fm_bag','fm_rate'].forEach(function(id){ document.getElementById(id).value = ''; });
  document.getElementById('fm_type').value = '';
  document.getElementById('fm_transmission').value = 'Automatic';
  if (document.getElementById('fm_withdriver')) document.getElementById('fm_withdriver').checked = false;
  // Check all operating cities by default for a new car
  document.querySelectorAll('#fm_cities_checks input[type=checkbox]').forEach(cb => cb.checked = true);
  resetImageField();
}
function prepareEdit(c) {
  document.getElementById('carModalTitle').textContent = 'Edit car';
  document.getElementById('fm_action').value        = 'update';
  document.getElementById('fm_id').value            = c.car_id;
  document.getElementById('fm_model').value         = c.car_model;
  document.getElementById('fm_type').value          = c.car_type;
  document.getElementById('fm_cap').value           = c.car_capacity;
  document.getElementById('fm_bag').value           = c.car_baggageload;
  document.getElementById('fm_rate').value          = c.car_rentalrate;
  document.getElementById('fm_transmission').value  = c.car_transmission || 'Automatic';
  if (document.getElementById('fm_withdriver')) document.getElementById('fm_withdriver').checked = !!c.car_withdriver;
  setCityChecks(Array.isArray(c.car_cities) ? c.car_cities : []);
  resetImageField();
  if (c.car_image_url) setImagePreview(c.car_image_url);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
