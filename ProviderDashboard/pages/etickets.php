<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'E-tickets';
$activePage = 'etickets';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/index.php'); exit; }
$provId = $_SESSION['provider_id'] ?? 0;

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');

try {
  $where  = ['ca.car_provid = ?'];
  $params = [$provId];
  if ($status_filter) { $where[] = 'et.tick_status = ?'; $params[] = $status_filter; }
  if ($search) {
    $where[] = '(CONCAT(cu.cust_firstname," ",cu.cust_lastname) LIKE ? OR ca.car_model LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
  }
  $wc = 'WHERE ' . implode(' AND ', $where);
  $stmt = $pdo->prepare("
    SELECT et.*, p.pay_amount, p.pay_method,
           ro.rent_id, ro.rent_dateissued, ro.rent_datedue,
           ro.rent_pickuplocation, ro.rent_dropofflocation,
           CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
           cu.cust_email,
           ca.car_model, ca.car_type
    FROM eticket et
    JOIN payment p       ON p.pay_id    = et.tick_payid
    JOIN rental_order ro ON ro.rent_id  = p.pay_rentid
    JOIN customer cu     ON cu.cust_id  = ro.rent_custid
    JOIN car ca          ON ca.car_id   = ro.rent_carid
    $wc
    ORDER BY et.tick_id DESC
  ");
  $stmt->execute($params);
  $tickets = $stmt->fetchAll();
} catch (Exception $e) { $tickets = []; }

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">E-tickets</h1>
    <p class="page-subtitle"><?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?> for your fleet</p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv" style="flex-wrap:wrap;gap:10px">
    <h2 class="card-title-tv">All e-tickets</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <?php foreach ([''=>'All','Pending'=>'Pending','Active'=>'Active','Completed'=>'Completed','Cancelled'=>'Cancelled'] as $val=>$label): ?>
        <a href="?status=<?= urlencode($val) ?>&q=<?= urlencode($search) ?>"
           style="padding:4px 14px;border-radius:99px;font-size:12px;font-weight:600;text-decoration:none;
                  <?= $status_filter===$val ? 'background:var(--tv-blue);color:#fff' : 'background:#F0F3F8;color:var(--text-secondary)' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
      <form method="get" class="search-wrap" style="margin-left:8px">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <i class="bi bi-search"></i>
        <input type="text" name="q" class="tv-input" placeholder="Search…" value="<?= htmlspecialchars($search) ?>" style="width:180px">
      </form>
    </div>
  </div>
  <?php if (empty($tickets)): ?>
    <div class="empty-state"><i class="bi bi-ticket-perforated d-block"></i><p>No e-tickets found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead>
        <tr><th>Ticket ID</th><th>Customer</th><th>Vehicle</th><th>Order #</th><th>Rental period</th><th>Amount</th><th>Issued</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $t['tick_id'] ?></strong></td>
          <td>
            <strong><?= htmlspecialchars($t['customer_name']) ?></strong><br>
            <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($t['cust_email']) ?></span>
          </td>
          <td>
            <?= htmlspecialchars($t['car_model']) ?><br>
            <span class="badge-tv badge-complete" style="margin-top:2px"><?= htmlspecialchars($t['car_type']) ?></span>
          </td>
          <td><strong style="color:var(--tv-blue)">#<?= $t['rent_id'] ?></strong></td>
          <td>
            <span style="font-size:12.5px"><?= htmlspecialchars($t['rent_dateissued']) ?></span><br>
            <span style="font-size:12px;color:var(--text-secondary)">→ <?= htmlspecialchars($t['rent_datedue']) ?></span>
          </td>
          <td><strong>₱<?= number_format($t['pay_amount'],2) ?></strong></td>
          <td><?= htmlspecialchars($t['tick_dateissued']) ?></td>
          <td>
            <?php $st=$t['tick_status']; $cls=match($st){'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel',default=>'badge-pending'}; ?>
            <span class="badge-tv <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>