<?php
require_once '../includes/db.php';
$pageTitle  = 'Customers';
$activePage = 'customers';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = trim($_POST['cust_id'] ?? '');
    $reason = trim($_POST['reason']  ?? '');

    if ($action === 'deactivate' && $uid) {
        $user = fb()->getDoc('users', $uid);
        fb()->updateDoc('users', $uid, [
            'isActive'            => false,
            'deactivationReason'  => $reason ?: 'Your account has been deactivated by an administrator.',
        ]);
        $email = $user['email'] ?? '';
        $name  = $user['fullName'] ?? 'Customer';
        if ($email) {
            $body = "Hi {$name},\n\nYour Traveloka Car Rental account has been deactivated.\n"
                  . ($reason ? "Reason: {$reason}\n" : '')
                  . "\nIf you believe this is a mistake, please contact support.\n\nTraveloka Car Rental";
            @mail($email, 'Your account has been deactivated — Traveloka Car Rental', $body,
                  "From: noreply@traveloka-carrental.com\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $msg = 'success:Customer deactivated. A notification has been sent to ' . htmlspecialchars($email) . '.';
    }

    if ($action === 'reactivate' && $uid) {
        $user = fb()->getDoc('users', $uid);
        fb()->updateDoc('users', $uid, [
            'isActive'           => true,
            'deactivationReason' => '',
        ]);
        $email = $user['email'] ?? '';
        $name  = $user['fullName'] ?? 'Customer';
        if ($email) {
            $body = "Hi {$name},\n\nGood news! Your Traveloka Car Rental account has been reactivated. You can now log in again.\n\nTraveloka Car Rental";
            @mail($email, 'Your account has been reactivated — Traveloka Car Rental', $body,
                  "From: noreply@traveloka-carrental.com\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $msg = 'success:Customer reactivated successfully.';
    }
}

$search    = trim($_GET['q'] ?? '');
$customers = fb()->query('users', [['field' => 'role', 'op' => 'EQUAL', 'value' => 'customer']]);
if ($search) {
    $sq = strtolower($search);
    $customers = array_filter($customers, fn($c) =>
        str_contains(strtolower($c['fullName'] ?? ''), $sq) ||
        str_contains(strtolower($c['email']    ?? ''), $sq)
    );
}
$customers = array_values($customers);

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Customers</h1>
    <p class="page-subtitle"><?= count($customers) ?> registered customer<?= count($customers) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<?php if ($msg): [$type,$text] = explode(':', $msg, 2); ?>
<div class="alert-tv <?= $type === 'success' ? 'success' : 'error' ?>" style="margin-bottom:20px">
  <i class="bi bi-<?= $type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
  <?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">All customers</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search customers…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($customers)): ?>
    <div class="empty-state"><i class="bi bi-people d-block"></i><p>No customers found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($customers as $c):
          $initials = strtoupper(substr($c['fullName'] ?? 'U', 0, 1));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--tv-blue-light);color:var(--tv-blue);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0">
                <?= $initials ?>
              </div>
              <strong><?= htmlspecialchars(($c['title'] ?? '') ? $c['title'].' '.($c['fullName'] ?? '') : ($c['fullName'] ?? '')) ?></strong>
            </div>
          </td>
          <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
          <?php $isActive = $c['isActive'] ?? true; ?>
          <td>
            <?php if ($isActive): ?>
              <span class="badge-tv badge-active">Active</span>
            <?php else: ?>
              <span class="badge-tv badge-cancel">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isActive): ?>
              <button class="btn-icon danger" title="Deactivate"
                onclick='openDeactivate(<?= json_encode(["id"=>$c["id"],"name"=>$c["fullName"]??"","action"=>"deactivate"]) ?>)'>
                <i class="bi bi-slash-circle"></i>
              </button>
            <?php else: ?>
              <form method="post" style="display:contents">
                <input type="hidden" name="action"  value="reactivate">
                <input type="hidden" name="cust_id" value="<?= htmlspecialchars($c['id']) ?>">
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


<!-- Deactivate modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-slash-circle" style="color:#DC2626;margin-right:6px"></i>Deactivate customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action"  value="deactivate">
        <input type="hidden" name="cust_id" id="dm_id" value="">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
          Deactivating <strong id="dm_name"></strong> will prevent them from logging in. They will be notified by email.
        </p>
        <label class="form-label-tv">Reason <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">(optional)</span></label>
        <textarea name="reason" id="dm_reason" class="tv-input" rows="3" maxlength="300" placeholder="e.g. Violation of terms of service…" style="resize:vertical;height:auto;padding:8px 12px"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-tv-primary" style="background:#DC2626;border-color:#DC2626"><i class="bi bi-slash-circle"></i> Deactivate</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeactivate(data) {
  document.getElementById('dm_id').value   = data.id;
  document.getElementById('dm_name').textContent = data.name;
  document.getElementById('dm_reason').value = '';
  new bootstrap.Modal(document.getElementById('deactivateModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
