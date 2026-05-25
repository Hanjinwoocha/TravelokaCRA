<?php
require_once '../includes/db.php';
$pageTitle  = 'Car providers';
$activePage = 'providers';
$msg = '';

$tab = $_GET['tab'] ?? 'providers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $id = trim($_POST['prov_id'] ?? '');
        fb()->updateDoc('vendors', $id, ['status' => 'approved', 'isActive' => true]);
        $msg = 'success:Provider approved. They can now log in.';
        $tab = 'applications';
    }

    if ($action === 'reject') {
        $id = trim($_POST['prov_id'] ?? '');
        fb()->updateDoc('vendors', $id, ['status' => 'rejected', 'isActive' => false]);
        $msg = 'success:Application rejected.';
        $tab = 'applications';
    }

    // Admin may not create, edit, or delete providers — only approve/reject applications and deactivate/reactivate.

    if ($action === 'deactivate') {
        $id     = trim($_POST['prov_id'] ?? '');
        $reason = trim($_POST['reason']  ?? '');
        $vendor = fb()->getDoc('vendors', $id);
        fb()->updateDoc('vendors', $id, [
            'isActive'           => false,
            'deactivationReason' => $reason ?: 'Your provider account has been deactivated by an administrator.',
        ]);
        $email = $vendor['email'] ?? '';
        $name  = $vendor['businessName'] ?? 'Provider';
        if ($email) {
            $body = "Hi {$name},\n\nYour Traveloka Car Rental provider account has been deactivated.\n"
                  . ($reason ? "Reason: {$reason}\n" : '')
                  . "\nIf you believe this is a mistake, please contact support.\n\nTraveloka Car Rental";
            @mail($email, 'Your provider account has been deactivated — Traveloka Car Rental', $body,
                  "From: noreply@traveloka-carrental.com\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $msg = 'success:Provider deactivated. A notification has been sent to ' . htmlspecialchars($email) . '.';
    }

    if ($action === 'toggle_driver') {
        $id      = trim($_POST['prov_id'] ?? '');
        $current = ($_POST['current_driver'] ?? '1') === '1';
        $newVal  = !$current;
        if (!$newVal) {
            // Disabling — check for active bookings with a driver assigned
            $vendorBookings = fb()->query('bookings', [['field' => 'vendorId', 'op' => 'EQUAL', 'value' => $id]]);
            $hasConflict = false;
            foreach ($vendorBookings as $b) {
                if (in_array($b['bookingStatus'] ?? '', ['Cancelled', 'Completed'])) continue;
                if (!empty($b['driverId'])) { $hasConflict = true; break; }
            }
            if ($hasConflict) {
                $msg = 'error:Cannot disable with-driver service — there are active or upcoming bookings with drivers assigned. Resolve those first.';
            } else {
                fb()->updateDoc('vendors', $id, ['withDriver' => false]);
                $msg = 'success:With-driver service disabled for this provider.';
            }
        } else {
            fb()->updateDoc('vendors', $id, ['withDriver' => true]);
            $msg = 'success:With-driver service enabled for this provider.';
        }
    }

    if ($action === 'reactivate') {
        $id     = trim($_POST['prov_id'] ?? '');
        $vendor = fb()->getDoc('vendors', $id);
        fb()->updateDoc('vendors', $id, [
            'isActive'           => true,
            'deactivationReason' => '',
        ]);
        $email = $vendor['email'] ?? '';
        $name  = $vendor['businessName'] ?? 'Provider';
        if ($email) {
            $body = "Hi {$name},\n\nGood news! Your Traveloka Car Rental provider account has been reactivated. You can now log in again.\n\nTraveloka Car Rental";
            @mail($email, 'Your provider account has been reactivated — Traveloka Car Rental', $body,
                  "From: noreply@traveloka-carrental.com\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $msg = 'success:Provider reactivated successfully.';
    }
}

$search       = trim($_GET['q'] ?? '');
$allVendors   = fb()->listDocs('vendors');
$pendingCount = count(array_filter($allVendors, fn($v) => ($v['status'] ?? '') === 'pending'));

if ($tab === 'applications') {
    $applications = array_values(array_filter($allVendors, fn($v) => in_array($v['status'] ?? '', ['pending','rejected'])));
    if ($search) {
        $sq = strtolower($search);
        $applications = array_values(array_filter($applications, fn($v) =>
            str_contains(strtolower($v['businessName'] ?? ''), $sq) ||
            str_contains(strtolower($v['email'] ?? ''), $sq)
        ));
    }
} else {
    $providers = array_values(array_filter($allVendors, fn($v) => ($v['status'] ?? '') === 'approved'));
    if ($search) {
        $sq = strtolower($search);
        $providers = array_values(array_filter($providers, fn($v) =>
            str_contains(strtolower($v['businessName'] ?? ''), $sq) ||
            str_contains(strtolower($v['country'] ?? ''), $sq)
        ));
    }
    // Attach vehicle counts
    $allVehicles = fb()->listDocs('vehicles');
    $vcounts = [];
    foreach ($allVehicles as $v) { $vid = $v['vendorId'] ?? ''; $vcounts[$vid] = ($vcounts[$vid] ?? 0) + 1; }
}

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Car providers</h1>
    <p class="page-subtitle">Manage approved providers and review applications.</p>
  </div>
</div>

<?php if ($msg): [$type,$text] = explode(':', $msg, 2); ?>
<div class="alert-tv <?= $type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px">
  <i class="bi bi-<?= $type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
  <?= htmlspecialchars($text) ?>
</div>
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
    <span style="background:#FF6000;color:#fff;border-radius:99px;padding:1px 8px;font-size:11px;margin-left:6px"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
  </a>
</div>

<?php if ($tab === 'applications'): ?>
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
      <thead><tr><th>Company</th><th>Email</th><th>Country</th><th>Driver?</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($applications as $p):
          $isPending  = ($p['status'] ?? '') === 'pending';
          $isRejected = ($p['status'] ?? '') === 'rejected';
          $vid = $p['vendorId'] ?? $p['id'];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:8px;background:<?= $isPending ? '#FEF3C7' : '#FEE2E2' ?>;color:<?= $isPending ? '#92400E' : '#991B1B' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($p['businessName'] ?? '?', 0, 2)) ?>
              </div>
              <strong><?= htmlspecialchars($p['businessName'] ?? '') ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['country'] ?? '—') ?></td>
          <td><span class="badge-tv <?= !empty($p['withDriver']) ? 'badge-driver' : 'badge-nodriver' ?>"><?= !empty($p['withDriver']) ? 'Yes' : 'No' ?></span></td>
          <td><?= $isPending ? '<span class="badge-tv badge-pending">Pending</span>' : '<span class="badge-tv badge-cancel">Rejected</span>' ?></td>
          <td style="display:flex;gap:6px">
            <?php if ($isPending || $isRejected): ?>
            <form method="post" style="display:contents">
              <input type="hidden" name="action"  value="approve">
              <input type="hidden" name="prov_id" value="<?= htmlspecialchars($vid) ?>">
              <button type="submit" class="btn-icon" title="Approve" style="color:#16A34A;border-color:#86EFAC"><i class="bi bi-check-lg"></i></button>
            </form>
            <?php endif; ?>
            <?php if ($isPending): ?>
            <form method="post" style="display:contents">
              <input type="hidden" name="action"  value="reject">
              <input type="hidden" name="prov_id" value="<?= htmlspecialchars($vid) ?>">
              <button type="submit" class="btn-icon danger" title="Reject"><i class="bi bi-x-lg"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
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
      <thead><tr><th>Name</th><th>Email</th><th>Country</th><th>Driver?</th><th>Cars</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($providers as $p):
          $vid      = $p['vendorId'] ?? $p['id'];
          $isActive = $p['isActive'] ?? true;
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--tv-orange-light);color:var(--tv-orange);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($p['businessName'] ?? '?', 0, 2)) ?>
              </div>
              <strong><?= htmlspecialchars($p['businessName'] ?? '') ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['country'] ?? '—') ?></td>
          <td><span class="badge-tv <?= !empty($p['withDriver']) ? 'badge-driver' : 'badge-nodriver' ?>"><?= !empty($p['withDriver']) ? 'Yes' : 'No' ?></span></td>
          <td><?= $vcounts[$vid] ?? 0 ?></td>
          <td>
            <?php if ($isActive): ?>
              <span class="badge-tv badge-active">Active</span>
            <?php else: ?>
              <span class="badge-tv badge-cancel">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px">
            <!-- Toggle withDriver -->
            <form method="post" style="display:contents">
              <input type="hidden" name="action"         value="toggle_driver">
              <input type="hidden" name="prov_id"        value="<?= htmlspecialchars($vid) ?>">
              <input type="hidden" name="current_driver" value="<?= !empty($p['withDriver']) ? '1' : '0' ?>">
              <?php if (!empty($p['withDriver'])): ?>
                <button type="submit" class="btn-icon" title="Disable with-driver service" style="color:#D97706;border-color:#FDE68A">
                  <i class="bi bi-person-dash"></i>
                </button>
              <?php else: ?>
                <button type="submit" class="btn-icon" title="Enable with-driver service" style="color:#16A34A;border-color:#86EFAC">
                  <i class="bi bi-person-check"></i>
                </button>
              <?php endif; ?>
            </form>
            <!-- Deactivate / Reactivate -->
            <?php if ($isActive): ?>
              <button class="btn-icon danger" title="Deactivate"
                onclick='openProvDeactivate(<?= json_encode(["id"=>$vid,"name"=>$p["businessName"]??"","action"=>"deactivate"]) ?>)'>
                <i class="bi bi-slash-circle"></i>
              </button>
            <?php else: ?>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"  value="reactivate">
                <input type="hidden" name="prov_id" value="<?= htmlspecialchars($vid) ?>">
                <button type="submit" class="btn-icon" title="Reactivate" style="color:#16A34A;border-color:#86EFAC">
                  <i class="bi bi-check-circle"></i>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>


<!-- Deactivate provider modal -->
<div class="modal fade" id="provDeactivateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-slash-circle" style="color:#DC2626;margin-right:6px"></i>Deactivate provider</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"  value="deactivate">
        <input type="hidden" name="prov_id" id="pdm_id" value="">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
          Deactivating <strong id="pdm_name"></strong> will prevent them from logging in. They will be notified by email.
        </p>
        <label class="form-label-tv">Reason <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
        <textarea name="reason" id="pdm_reason" class="tv-input" rows="3" maxlength="300" placeholder="e.g. Multiple policy violations…" style="resize:vertical;height:auto;padding:8px 12px"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary" style="background:#DC2626;border-color:#DC2626"><i class="bi bi-slash-circle"></i> Deactivate</button>
      </div>
    </form>
  </div>
</div>

<script>
function openProvDeactivate(data) {
  document.getElementById('pdm_id').value = data.id;
  document.getElementById('pdm_name').textContent = data.name;
  document.getElementById('pdm_reason').value = '';
  new bootstrap.Modal(document.getElementById('provDeactivateModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
