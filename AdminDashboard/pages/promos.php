<?php
require_once '../includes/db.php';
$pageTitle  = 'Promo codes';
$activePage = 'promos';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code      = strtoupper(trim($_POST['promo_code']     ?? ''));
        $type      = trim($_POST['promo_type']     ?? 'percent');
        $value     = floatval($_POST['promo_value']    ?? 0);
        $maxUses   = intval($_POST['promo_maxuses']  ?? 0);
        $expiresAt = trim($_POST['promo_expires']   ?? '');
        $expiresMs = $expiresAt ? (strtotime($expiresAt) * 1000) : 0;

        if (!$code) {
            $msg = 'error:Promo code cannot be empty.';
        } elseif ($value <= 0) {
            $msg = 'error:Discount value must be greater than zero.';
        } elseif ($type === 'percent' && $value > 100) {
            $msg = 'error:Percentage discount cannot exceed 100%.';
        } else {
            // Check for duplicate
            $existing = fb()->query('promos', [['field' => 'code', 'op' => 'EQUAL', 'value' => $code]]);
            if (!empty($existing)) {
                $msg = 'error:A promo code "' . htmlspecialchars($code) . '" already exists.';
            } else {
                $id = fb()->newId();
                fb()->setDoc('promos', $id, [
                    'promoId'      => $id,
                    'code'         => $code,
                    'discountType' => $type,
                    'discountValue'=> $value,
                    'maxUses'      => $maxUses,
                    'usedCount'    => 0,
                    'isActive'     => true,
                    'expiresAt'    => $expiresMs,
                    'createdAt'    => Firebase::nowMs(),
                ]);
                $msg = 'success:Promo code "' . htmlspecialchars($code) . '" created.';
            }
        }
    }

    if ($action === 'toggle') {
        $id        = trim($_POST['promo_id'] ?? '');
        $current   = trim($_POST['current_active'] ?? '1') === '1';
        $newActive = !$current;
        fb()->updateDoc('promos', $id, ['isActive' => $newActive]);
        $msg = 'success:Promo code ' . ($newActive ? 'activated' : 'deactivated') . '.';
    }

    if ($action === 'delete') {
        $id = trim($_POST['promo_id'] ?? '');
        fb()->deleteDoc('promos', $id);
        $msg = 'success:Promo code deleted.';
    }
}

$promos = fb()->listDocs('promos');
usort($promos, fn($a, $b) => intval($b['createdAt'] ?? 0) <=> intval($a['createdAt'] ?? 0));

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Promo codes</h1>
    <p class="page-subtitle"><?= count($promos) ?> promo code<?= count($promos) !== 1 ? 's' : '' ?> &mdash; registered customers only</p>
  </div>
  <button class="btn-tv-primary" onclick="openAddModal()"><i class="bi bi-plus-lg"></i> Add promo</button>
</div>

<?php if ($msg): [$type,$text] = explode(':', $msg, 2); ?>
<div class="alert-tv <?= $type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px">
  <i class="bi bi-<?= $type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
  <?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All promo codes</h2>
    <div style="font-size:12.5px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
      <i class="bi bi-info-circle"></i> Only logged-in registered customers can apply these codes at checkout.
    </div>
  </div>

  <?php if (empty($promos)): ?>
    <div class="empty-state"><i class="bi bi-ticket-detailed d-block"></i><p>No promo codes yet. Create one to get started.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Code</th><th>Discount</th><th>Uses</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($promos as $p):
          $isActive   = $p['isActive'] ?? true;
          $maxUses    = intval($p['maxUses']    ?? 0);
          $usedCount  = intval($p['usedCount']  ?? 0);
          $expiresAt  = intval($p['expiresAt']  ?? 0);
          $isExpired  = $expiresAt > 0 && $expiresAt < Firebase::nowMs();
          $isExhausted = $maxUses > 0 && $usedCount >= $maxUses;
          $pid = $p['promoId'] ?? $p['id'];
        ?>
        <tr>
          <td>
            <strong style="font-family:monospace;font-size:14px;letter-spacing:.5px;color:var(--tv-blue)">
              <?= htmlspecialchars($p['code'] ?? '') ?>
            </strong>
          </td>
          <td>
            <?php if (($p['discountType'] ?? 'percent') === 'percent'): ?>
              <span class="badge-tv badge-active"><?= number_format(floatval($p['discountValue'] ?? 0), 0) ?>% off</span>
            <?php else: ?>
              <span class="badge-tv badge-complete">₱<?= number_format(floatval($p['discountValue'] ?? 0), 2) ?> off</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($maxUses > 0): ?>
              <span><?= $usedCount ?> / <?= $maxUses ?></span>
              <?php if ($isExhausted): ?>
                <span class="badge-tv badge-cancel" style="margin-left:4px">Exhausted</span>
              <?php endif; ?>
            <?php else: ?>
              <span><?= $usedCount ?> / <em style="color:var(--text-muted)">unlimited</em></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($expiresAt > 0): ?>
              <span style="font-size:13px;<?= $isExpired ? 'color:#DC2626' : '' ?>">
                <?= date('M d, Y', intdiv($expiresAt, 1000)) ?>
                <?php if ($isExpired): ?><span class="badge-tv badge-cancel" style="margin-left:4px">Expired</span><?php endif; ?>
              </span>
            <?php else: ?>
              <em style="color:var(--text-muted);font-size:12.5px">No expiry</em>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isActive && !$isExpired && !$isExhausted): ?>
              <span class="badge-tv badge-active">Active</span>
            <?php elseif (!$isActive): ?>
              <span class="badge-tv badge-cancel">Inactive</span>
            <?php else: ?>
              <span class="badge-tv badge-cancel">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px">
            <!-- Toggle active/inactive -->
            <form method="post" style="display:contents">
              <input type="hidden" name="action"         value="toggle">
              <input type="hidden" name="promo_id"       value="<?= htmlspecialchars($pid) ?>">
              <input type="hidden" name="current_active" value="<?= $isActive ? '1' : '0' ?>">
              <?php if ($isActive): ?>
                <button type="submit" class="btn-icon danger" title="Deactivate"><i class="bi bi-pause-circle"></i></button>
              <?php else: ?>
                <button type="submit" class="btn-icon" title="Activate" style="color:#16A34A;border-color:#86EFAC"><i class="bi bi-play-circle"></i></button>
              <?php endif; ?>
            </form>
            <!-- Delete -->
            <form id="del_promo_<?= htmlspecialchars($pid) ?>" method="post" style="display:none">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="promo_id" value="<?= htmlspecialchars($pid) ?>">
            </form>
            <button class="btn-icon danger" title="Delete"
              onclick='confirmDelete("promo code","del_promo_<?= htmlspecialchars($pid) ?>",<?= json_encode($p["code"] ?? "", JSON_HEX_APOS) ?>)'>
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

<!-- Add promo modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-ticket-detailed" style="color:var(--tv-blue);margin-right:6px"></i>New promo code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-tv">Code *</label>
            <input type="text" name="promo_code" id="pm_code" class="tv-input" required maxlength="30"
              placeholder="e.g. SUMMER20" style="font-family:monospace;letter-spacing:1px;text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase().replace(/\s/g,'')">
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px">Uppercase letters and numbers only. No spaces.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Discount type *</label>
            <select name="promo_type" id="pm_type" class="tv-select" required onchange="updateValueHint()">
              <option value="percent">Percentage (% off)</option>
              <option value="fixed">Fixed amount (₱ off)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Discount value *</label>
            <div style="position:relative">
              <span id="pm_prefix" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none">%</span>
              <input type="number" name="promo_value" id="pm_value" class="tv-input" required min="0.01" step="0.01"
                style="padding-left:28px" placeholder="10">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Max uses <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(0 = unlimited)</span></label>
            <input type="number" name="promo_maxuses" class="tv-input" min="0" value="0" placeholder="0">
          </div>
          <div class="col-md-6">
            <label class="form-label-tv">Expiry date <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
            <input type="date" name="promo_expires" class="tv-input" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary"><i class="bi bi-check-lg"></i> Create promo</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('pm_code').value  = '';
  document.getElementById('pm_type').value  = 'percent';
  document.getElementById('pm_value').value = '';
  updateValueHint();
  new bootstrap.Modal(document.getElementById('promoModal')).show();
}
function updateValueHint() {
  const type = document.getElementById('pm_type').value;
  const pfx  = document.getElementById('pm_prefix');
  const inp  = document.getElementById('pm_value');
  if (type === 'percent') {
    pfx.textContent = '%';
    inp.max  = 100;
    inp.step = 1;
    inp.placeholder = '10';
  } else {
    pfx.textContent = '₱';
    inp.removeAttribute('max');
    inp.step = '0.01';
    inp.placeholder = '100.00';
  }
}
</script>

<?php include '../includes/footer.php'; ?>
