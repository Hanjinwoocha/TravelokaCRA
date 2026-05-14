</main>
</div><!-- end page-wrapper -->

<!-- Footer bar -->
<footer class="cust-footer">
  <div class="cust-footer-inner">
    <span class="cust-footer-brand"><span style="color:#FF6000">t</span>raveloka</span>
    <span class="cust-footer-copy">&copy; <?= date('Y') ?> Traveloka Car Rental. All rights reserved.</span>
  </div>
</footer>

<?php
$_custLabel = !empty($_SESSION['customer_logged_in'])
    ? ($_SESSION['customer_name'] ?? 'Customer')
    : 'Guest';
?>

<!-- Logout confirmation modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-body" style="padding:36px 28px 20px;text-align:center">
        <div class="logout-modal-icon"><i class="bi bi-box-arrow-right"></i></div>
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:17px;color:var(--text-primary);margin-bottom:10px">Log out?</h5>
        <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0">
          You're signed in as <strong><?= htmlspecialchars($_custLabel) ?></strong>.<br>
          You'll need to sign in again to view your bookings.
        </p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 28px;justify-content:center;gap:10px">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal" style="min-width:110px">Stay</button>
        <a href="/Traveloka/auth/customer_logout.php" class="btn-tv-orange" style="min-width:110px;justify-content:center">
          <i class="bi bi-box-arrow-right"></i> Log out
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Generic confirm modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-body" style="padding:36px 28px 20px;text-align:center">
        <div id="confirmIcon" style="width:64px;height:64px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
          <i class="bi bi-question-circle-fill" style="color:#DC2626;font-size:28px"></i>
        </div>
        <h5 id="confirmTitle" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:17px;color:var(--text-primary);margin-bottom:10px">Are you sure?</h5>
        <p id="confirmMsg" style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0"></p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 28px;justify-content:center;gap:10px">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal" style="min-width:110px">Cancel</button>
        <button type="button" id="confirmBtn" class="btn-tv-primary" style="background:#DC2626;min-width:110px;color:#fff">
          <i class="bi bi-check-lg"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Traveloka/CustomerDashboard/assets/js/customer.js"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
<?php
// Auto-fire toast from $msg (format "type:text"). Page-level $error / $success stay
// as inline alert-tv divs (used for form validation in book.php and payment.php).
if (!empty($msg)) {
    [$_ftype, $_ftext] = explode(':', $msg, 2);
    $_ftype = in_array($_ftype, ['success','error','warning','info']) ? $_ftype : 'info';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){notify(' . json_encode($_ftext) . ',' . json_encode($_ftype) . ');});</script>';
}
?>
</body>
</html>
