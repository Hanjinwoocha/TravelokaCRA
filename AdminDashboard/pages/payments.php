<?php
require_once '../includes/db.php';
$pageTitle  = 'Payments';
$activePage = 'payments';

$search = trim($_GET['q'] ?? '');
try {
  if ($search) {
    $stmt = $pdo->prepare("
      SELECT p.*, ro.rent_id, ro.rent_dateissued,
             CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
             et.tick_status
      FROM payment p
      JOIN rental_order ro ON ro.rent_id = p.pay_rentid
      JOIN customer cu ON cu.cust_id = ro.rent_custid
      LEFT JOIN eticket et ON et.tick_payid = p.pay_id
      WHERE cu.cust_firstname LIKE ? OR cu.cust_lastname LIKE ? OR p.pay_method LIKE ?
      ORDER BY p.pay_id DESC
    ");
    $stmt->execute(["%$search%","%$search%","%$search%"]);
  } else {
    $stmt = $pdo->query("
      SELECT p.*, ro.rent_id, ro.rent_dateissued,
             CONCAT(cu.cust_firstname,' ',cu.cust_lastname) AS customer_name,
             et.tick_status
      FROM payment p
      JOIN rental_order ro ON ro.rent_id = p.pay_rentid
      JOIN customer cu ON cu.cust_id = ro.rent_custid
      LEFT JOIN eticket et ON et.tick_payid = p.pay_id
      ORDER BY p.pay_id DESC
    ");
  }
  $payments = $stmt->fetchAll();
} catch (Exception $e) { $payments = []; }

// Total revenue
try { $totalRevenue = $pdo->query("SELECT COALESCE(SUM(pay_amount),0) FROM payment")->fetchColumn(); }
catch (Exception $e) { $totalRevenue = 0; }

include '../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Payments</h1>
    <p class="page-subtitle">Total revenue: <strong style="color:var(--tv-blue)">₱<?= number_format($totalRevenue, 2) ?></strong></p>
  </div>
</div>

<div class="content-card">
  <div class="card-header-tv">
    <h2 class="card-title-tv">Payment records</h2>
    <form method="get" class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="tv-input" placeholder="Search payments…" value="<?= htmlspecialchars($search) ?>" style="width:220px">
    </form>
  </div>
  <?php if (empty($payments)): ?>
    <div class="empty-state"><i class="bi bi-credit-card d-block"></i><p>No payments found.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="tv-table">
      <thead><tr><th>Pay ID</th><th>Order</th><th>Customer</th><th>Amount</th><th>Method</th><th>Coupon</th><th>Discount</th><th>Date paid</th><th>Ticket status</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><strong style="color:var(--tv-blue)">#<?= $p['pay_id'] ?></strong></td>
          <td><a href="rentals.php" style="color:var(--tv-blue);text-decoration:none">#<?= $p['rent_id'] ?></a></td>
          <td><?= htmlspecialchars($p['customer_name']) ?></td>
          <td><strong>₱<?= number_format($p['pay_amount'], 2) ?></strong></td>
          <td><span class="badge-tv badge-complete"><?= htmlspecialchars($p['pay_method']) ?></span></td>
          <td><?= $p['pay_couponcode'] ? htmlspecialchars($p['pay_couponcode']) : '—' ?></td>
          <td><?= $p['pay_discountamt'] ? '₱'.number_format($p['pay_discountamt'],2) : '—' ?></td>
          <td><?= htmlspecialchars($p['pay_datepaid']) ?></td>
          <td>
            <?php $st = $p['tick_status'] ?? '—';
              $cls = match($st){ 'Active'=>'badge-active','Completed'=>'badge-complete','Cancelled'=>'badge-cancel','Pending'=>'badge-pending',default=>'' }; ?>
            <?= $cls ? "<span class='badge-tv $cls'>".htmlspecialchars($st).'</span>' : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>