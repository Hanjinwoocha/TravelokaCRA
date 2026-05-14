<?php
require_once '../includes/db.php';
$pageTitle  = 'Car providers';
$activePage = 'providers';
$msg = '';

$tab = $_GET['tab'] ?? 'providers'; // 'providers' | 'applications'

// ── Locations for dropdown ────────────────────────────────────────────────────
try { $locations = $pdo->query("SELECT loctn_id, loctn_name FROM location ORDER BY loctn_name")->fetchAll(); }
catch (Exception $e) { $locations = []; }

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Approve application
  if ($action === 'approve') {
    $id = intval($_POST['prov_id']);
    $pdo->prepare("UPDATE car_provider SET prov_status='approved' WHERE prov_id=?")->execute([$id]);
    $msg = 'success:Provider approved. They can now log in.';
    $tab = 'applications';
  }

  // Reject application
  if ($action === 'reject') {
    $id = intval($_POST['prov_id']);
    $pdo->prepare("UPDATE car_provider SET prov_status='rejected' WHERE prov_id=?")->execute([$id]);
    $msg = 'success:Application rejected.';
    $tab = 'applications';
  }

  // Create provider (manual, auto-approved)
  if ($action === 'create') {
    $name       = trim($_POST['prov_name']       ?? '');
    $country    = trim($_POST['prov_country']    ?? '');
    $email      = trim($_POST['prov_email']      ?? '');
    $pass       = trim($_POST['prov_password']   ?? '');
    $withDriver = isset($_POST['prov_withdriver']) ? 1 : 0;
    $loctnId    = intval($_POST['prov_loctnid']  ?? 0);
    $pdo->prepare("INSERT INTO car_provider (prov_name, prov_country, prov_email, prov_password, prov_withdriver, prov_loctnid, prov_status) VALUES (?,?,?,?,?,?,'approved')")
      ->execute([$name, $country, $email, md5($pass), $withDriver, $loctnId]);
    $msg = 'success:Provider added and approved.';
  }

  // Update provider
  if ($action === 'update') {
    $id         = intval($_POST['prov_id']);
    $name       = trim($_POST['prov_name']       ?? '');
    $country    = trim($_POST['prov_country']    ?? '');
    $email      = trim($_POST['prov_email']      ?? '');
    $pass       = trim($_POST['prov_password']   ?? '');
    $withDriver = isset($_POST['prov_withdriver']) ? 1 : 0;
    $loctnId    = intval($_POST['prov_loctnid']  ?? 0);
    if ($pass !== '') {
      $pdo->prepare("UPDATE car_provider SET prov_name=?,prov_country=?,prov_email=?,prov_password=?,prov_withdriver=?,prov_loctnid=? WHERE prov_id=?")
        ->execute([$name, $country, $email, md5($pass), $withDriver, $loctnId, $id]);
    } else {
      $pdo->prepare("UPDATE car_provider SET prov_name=?,prov_country=?,prov_email=?,prov_withdriver=?,prov_loctnid=? WHERE prov_id=?")
        ->execute([$name, $country, $email, $withDriver, $loctnId, $id]);
    }
    $msg = 'success:Provider updated.';
  }

  // Delete provider
  if ($action === 'delete') {
    $id  = intval($_POST['prov_id']);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM car WHERE car_provid=?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
      $msg = 'error:Cannot delete — provider still has cars assigned. Remove those cars first.';
    } else {
      $pdo->prepare("DELETE FROM car_provider WHERE prov_id=?")->execute([$id]);
      $msg = 'success:Provider removed.';
    }
  }
}

// ── Pending applications count (for badge) ────────────────────────────────────
try { $pendingCount = $pdo->query("SELECT COUNT(*) FROM car_provider WHERE prov_status='pending'")->fetchColumn(); }
catch (Exception $e) { $pendingCount = 0; }

// ── Fetch data based on tab ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');

if ($tab === 'applications') {
  try {
    $where  = ["cp.prov_status IN ('pending','rejected')"];
    $params = [];
    if ($search) { $where[] = '(cp.prov_name LIKE ? OR cp.prov_email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    $wc = 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT cp.*, l.loctn_name FROM car_provider cp LEFT JOIN location l ON l.loctn_id=cp.prov_loctnid $wc ORDER BY cp.prov_id DESC");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
  } catch (Exception $e) { $applications = []; }
} else {
  try {
    $where  = ["cp.prov_status = 'approved'"];
    $params = [];
    if ($search) { $where[] = '(cp.prov_name LIKE ? OR cp.prov_country LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    $wc = 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT cp.*, l.loctn_name, (SELECT COUNT(*) FROM car WHERE car_provid=cp.prov_id) AS car_count FROM car_provider cp LEFT JOIN location l ON l.loctn_id=cp.prov_loctnid $wc ORDER BY cp.prov_id DESC");
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
  } catch (Exception $e) { $providers = []; }
}

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Car providers</h1>
    <p class="page-subtitle">Manage approved providers and review applications.</p>
  </div>
  <?php if ($tab === 'providers'): ?>
  <button class="btn-tv-primary" onclick="openAddModal()">
    <i class="bi bi-plus-lg"></i> Add provider
  </button>
  <?php endif; ?>
</div>

<?php if ($msg): [$type,$text] = explode(':',$msg,2); ?>
<div class="alert-tv <?= $type ?>"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($text) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0">
  <a href="?tab=providers"
     style="padding:9px 20px;font-size:13.5px;font-weight:600;text-decoration:none;border-radius:8px 8px 0 0;margin-bottom:-2px;border:2px solid transparent;
            <?= $tab==='providers' ? 'border-color:var(--border);border-bottom-color:var(--surface);background:var(--surface);color:var(--tv-blue)' : 'color:var(--text-secondary)' ?>">
    <i class="bi bi-building" style="margin-right:6px"></i>Providers
    <span style="background:var(--tv-blue-light);color:var(--tv-blue);border-radius:99px;padding:1px 8px;font-size:11px;margin-left:6px">
      <?= $tab === 'providers' ? count($providers ?? []) : '…' ?>
    </span>
  </a>
  <a href="?tab=applications"
     style="padding:9px 20px;font-size:13.5px;font-weight:600;text-decoration:none;border-radius:8px 8px 0 0;margin-bottom:-2px;border:2px solid transparent;
            <?= $tab==='applications' ? 'border-color:var(--border);border-bottom-color:var(--surface);background:var(--surface);color:var(--tv-blue)' : 'color:var(--text-secondary)' ?>">
    <i class="bi bi-inbox" style="margin-right:6px"></i>Applications
    <?php if ($pendingCount > 0): ?>
    <span style="background:#FF6000;color:#fff;border-radius:99px;padding:1px 8px;font-size:11px;margin-left:6px">
      <?= $pendingCount ?> pending
    </span>
    <?php endif; ?>
  </a>
</div>

<?php if ($tab === 'applications'): ?>
<!-- ── Applications tab ─────────────────────────────────────────────── -->
<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">Provider applications</h2>
    <form method="get" class="search-wrap">
      <input type="hidden" name="tab" value="applications">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search applications…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>

  <?php if (empty($applications)): ?>
    <div class="empty-state"><i class="bi bi-inbox d-block"></i><p>No applications found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>ID</th><th>Company</th><th>Email</th><th>Country</th><th>Location</th><th>Driver?</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($applications as $p):
          $isPending  = $p['prov_status'] === 'pending';
          $isRejected = $p['prov_status'] === 'rejected';
        ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $p['prov_id'] ?></strong></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:8px;background:<?= $isPending ? '#FEF3C7' : '#FEE2E2' ?>;color:<?= $isPending ? '#92400E' : '#991B1B' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($p['prov_name'],0,2)) ?>
              </div>
              <strong><?= htmlspecialchars($p['prov_name']) ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($p['prov_email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['prov_country']) ?></td>
          <td><?= htmlspecialchars($p['loctn_name'] ?? '—') ?></td>
          <td>
            <span class="badge-tv <?= $p['prov_withdriver'] ? 'badge-driver' : 'badge-nodriver' ?>">
              <?= $p['prov_withdriver'] ? 'Yes' : 'No' ?>
            </span>
          </td>
          <td>
            <?php if ($isPending): ?>
              <span class="badge-tv badge-pending">Pending</span>
            <?php else: ?>
              <span class="badge-tv badge-cancel">Rejected</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px">
            <?php if ($isPending): ?>
            <form method="post" style="display:contents"
                  data-confirm-title="Approve provider"
                  data-confirm-message="Approve <?= htmlspecialchars($p['prov_name'], ENT_QUOTES) ?>? They'll be able to log in immediately."
                  data-confirm-variant="success"
                  data-confirm-action="Approve">
              <input type="hidden" name="action"  value="approve">
              <input type="hidden" name="prov_id" value="<?= $p['prov_id'] ?>">
              <button type="submit" class="btn-icon" title="Approve"
                      style="color:#16A34A;border-color:#86EFAC">
                <i class="bi bi-check-lg"></i>
              </button>
            </form>
            <form method="post" style="display:contents"
                  data-confirm-title="Reject application"
                  data-confirm-message="Reject <?= htmlspecialchars($p['prov_name'], ENT_QUOTES) ?>'s application? They will not be able to log in."
                  data-confirm-variant="danger"
                  data-confirm-action="Reject"
                  data-confirm-action-icon="bi-x-lg">
              <input type="hidden" name="action"  value="reject">
              <input type="hidden" name="prov_id" value="<?= $p['prov_id'] ?>">
              <button type="submit" class="btn-icon danger" title="Reject">
                <i class="bi bi-x-lg"></i>
              </button>
            </form>
            <?php elseif ($isRejected): ?>
            <form method="post" style="display:contents"
                  data-confirm-title="Reinstate application"
                  data-confirm-message="Approve <?= htmlspecialchars($p['prov_name'], ENT_QUOTES) ?>'s previously rejected application?"
                  data-confirm-variant="success"
                  data-confirm-action="Approve">
              <input type="hidden" name="action"  value="approve">
              <input type="hidden" name="prov_id" value="<?= $p['prov_id'] ?>">
              <button type="submit" class="btn-icon" title="Approve anyway"
                      style="color:#16A34A;border-color:#86EFAC">
                <i class="bi bi-check-lg"></i>
              </button>
            </form>
            <?php endif; ?>
            <form id="del_app_<?= $p['prov_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="prov_id" value="<?= $p['prov_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete"
                    onclick='confirmDelete("application","del_app_<?= $p['prov_id'] ?>",<?= json_encode($p['prov_name'], JSON_HEX_APOS) ?>)'>
              <i class="bi bi-trash3"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ── Providers tab ────────────────────────────────────────────────── -->
<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Approved providers</h2>
    <form method="get" class="search-wrap">
      <input type="hidden" name="tab" value="providers">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search providers…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($providers)): ?>
    <div class="empty-state"><i class="bi bi-building d-block"></i><p>No approved providers yet.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Country</th><th>Location</th><th>Driver?</th><th>Cars</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($providers as $p): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $p['prov_id'] ?></strong></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--tv-orange-light);color:var(--tv-orange);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($p['prov_name'],0,2)) ?>
              </div>
              <strong><?= htmlspecialchars($p['prov_name']) ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($p['prov_email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['prov_country']) ?></td>
          <td><?= htmlspecialchars($p['loctn_name'] ?? '—') ?></td>
          <td>
            <span class="badge-tv <?= $p['prov_withdriver'] ? 'badge-driver' : 'badge-nodriver' ?>">
              <?= $p['prov_withdriver'] ? 'Yes' : 'No' ?>
            </span>
          </td>
          <td><?= $p['car_count'] ?></td>
          <td style="display:flex;gap:6px">
            <button class="btn-icon" title="Edit" onclick='openEditModal(<?= json_encode($p) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <form id="del_prov_<?= $p['prov_id'] ?>" method="post" style="display:none">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="prov_id" value="<?= $p['prov_id'] ?>">
            </form>
            <button class="btn-icon danger" title="Delete"
                    onclick='confirmDelete("provider","del_prov_<?= $p['prov_id'] ?>",<?= json_encode($p['prov_name'], JSON_HEX_APOS) ?>)'>
              <i class="bi bi-trash3"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Add / Edit Provider Modal -->
<div class="modal fade" id="providerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="providerModalTitle">Add provider</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"  id="fm_action" value="create">
        <input type="hidden" name="prov_id" id="fm_id"     value="">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label-tv">Provider name *</label>
            <input type="text" name="prov_name" id="fm_name" class="tv-input" required maxlength="100">
          </div>
          <div class="col-md-5">
            <label class="form-label-tv">Country *</label>
            <input type="text" name="prov_country" id="fm_country" class="tv-input" required maxlength="100">
          </div>
          <div class="col-12">
            <label class="form-label-tv">Login email *</label>
            <input type="email" name="prov_email" id="fm_email" class="tv-input" required maxlength="100">
          </div>
          <div class="col-12">
            <label class="form-label-tv">
              Password <span id="fm_pass_hint" style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(leave blank to keep existing)</span>
            </label>
            <input type="password" name="prov_password" id="fm_pass" class="tv-input" maxlength="100">
          </div>
          <div class="col-12">
            <label class="form-label-tv">Location *</label>
            <select name="prov_loctnid" id="fm_loctn" class="tv-select" required>
              <option value="">Select location…</option>
              <?php foreach ($locations as $l): ?>
              <option value="<?= $l['loctn_id'] ?>"><?= htmlspecialchars($l['loctn_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="prov_withdriver" id="fm_driver" value="1">
              <label class="form-check-label" for="fm_driver" style="font-size:13.5px">Offers with-driver rentals</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('providerModalTitle').textContent = 'Add provider';
  document.getElementById('fm_action').value  = 'create';
  document.getElementById('fm_id').value      = '';
  document.getElementById('fm_name').value    = '';
  document.getElementById('fm_country').value = '';
  document.getElementById('fm_email').value   = '';
  document.getElementById('fm_pass').value    = '';
  document.getElementById('fm_pass').required = true;
  document.getElementById('fm_pass_hint').style.display = 'none';
  document.getElementById('fm_loctn').value   = '';
  document.getElementById('fm_driver').checked = false;
  new bootstrap.Modal(document.getElementById('providerModal')).show();
}
function openEditModal(p) {
  document.getElementById('providerModalTitle').textContent = 'Edit provider';
  document.getElementById('fm_action').value  = 'update';
  document.getElementById('fm_id').value      = p.prov_id;
  document.getElementById('fm_name').value    = p.prov_name;
  document.getElementById('fm_country').value = p.prov_country;
  document.getElementById('fm_email').value   = p.prov_email || '';
  document.getElementById('fm_pass').value    = '';
  document.getElementById('fm_pass').required = false;
  document.getElementById('fm_pass_hint').style.display = '';
  document.getElementById('fm_loctn').value   = p.prov_loctnid;
  document.getElementById('fm_driver').checked = p.prov_withdriver == 1;
  new bootstrap.Modal(document.getElementById('providerModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
