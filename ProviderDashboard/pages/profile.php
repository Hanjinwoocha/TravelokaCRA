<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'My profile';
$activePage = 'profile';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? 0;
$msg = '';

// Fetch locations for dropdown
try { $locations = $pdo->query("SELECT loctn_id, loctn_name FROM location ORDER BY loctn_name")->fetchAll(); }
catch (Exception $e) { $locations = []; }

// Fetch current provider
try {
  $stmt = $pdo->prepare("SELECT cp.*, l.loctn_name FROM car_provider cp LEFT JOIN location l ON l.loctn_id = cp.prov_loctnid WHERE cp.prov_id = ?");
  $stmt->execute([$provId]);
  $provider = $stmt->fetch();
} catch (Exception $e) { $provider = null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name       = trim($_POST['prov_name']       ?? '');
  $country    = trim($_POST['prov_country']    ?? '');
  $withDriver = isset($_POST['prov_withdriver']) ? 1 : 0;
  $loctnId    = intval($_POST['prov_loctnid']  ?? 0);

  $pdo->prepare("UPDATE car_provider SET prov_name=?, prov_country=?, prov_withdriver=?, prov_loctnid=? WHERE prov_id=?")
    ->execute([$name, $country, $withDriver, $loctnId, $provId]);

  // Update session name
  $_SESSION['provider_name'] = $name;
  $msg = 'success:Profile updated successfully.';

  // Re-fetch
  $refetch = $pdo->prepare("SELECT cp.*, l.loctn_name FROM car_provider cp LEFT JOIN location l ON l.loctn_id = cp.prov_loctnid WHERE cp.prov_id = ?");
  $refetch->execute([$provId]);
  $provider = $refetch->fetch();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">My profile</h1>
    <p class="page-subtitle">Manage your provider account details</p>
  </div>
</div>

<?php if (!$provider): ?>
  <div class="empty-state"><i class="bi bi-exclamation-circle d-block"></i><p>Provider not found.</p></div>
<?php else: ?>

<div class="row g-3">
  <!-- Profile card -->
  <div class="col-lg-4">
    <div class="content-card" style="margin-bottom:0">
      <div class="card-body-tv" style="text-align:center;padding:32px 22px">
        <div style="width:72px;height:72px;border-radius:16px;background:var(--tv-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:700;margin:0 auto 16px">
          <?= strtoupper(substr($provider['prov_name'],0,2)) ?>
        </div>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($provider['prov_name']) ?></h3>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px"><?= htmlspecialchars($provider['prov_country']) ?></p>
        <div style="display:flex;flex-direction:column;gap:8px;text-align:left">
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-geo-alt" style="color:var(--tv-blue)"></i>
            <span><?= htmlspecialchars($provider['loctn_name'] ?? '—') ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-person-badge" style="color:var(--tv-blue)"></i>
            <span><?= $provider['prov_withdriver'] ? 'Offers with-driver service' : 'Self-drive only' ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;font-size:13px">
            <i class="bi bi-hash" style="color:var(--tv-blue)"></i>
            <span>Provider ID: <strong>#<?= $provider['prov_id'] ?></strong></span>
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
            <div class="col-md-6">
              <label class="form-label-tv">Company name *</label>
              <input type="text" name="prov_name" class="tv-input" required maxlength="100" value="<?= htmlspecialchars($provider['prov_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Country *</label>
              <input type="text" name="prov_country" class="tv-input" required maxlength="100" value="<?= htmlspecialchars($provider['prov_country']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label-tv">Location *</label>
              <select name="prov_loctnid" class="tv-select" required>
                <option value="">Select location…</option>
                <?php foreach ($locations as $l): ?>
                <option value="<?= $l['loctn_id'] ?>" <?= $l['loctn_id'] == $provider['prov_loctnid'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($l['loctn_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="prov_withdriver" id="prov_withdriver" value="1" <?= $provider['prov_withdriver'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="prov_withdriver" style="font-size:13.5px">
                  We offer with-driver rental service
                </label>
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

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>