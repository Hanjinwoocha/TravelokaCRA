    </main><!-- end page-content -->

  </div><!-- end app-main -->

</div><!-- end app-wrapper -->

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
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:17px;color:var(--text-primary);margin-bottom:10px">Sign out?</h5>
        <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0">
          You're signed in as <strong><?= htmlspecialchars($_custLabel) ?></strong>.<br>
          You'll need to sign in again to view your bookings.
        </p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 28px;justify-content:center;gap:10px">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal" style="min-width:110px">Stay</button>
        <a href="/Traveloka/auth/customer_logout.php" class="btn-tv-orange" style="min-width:110px;justify-content:center">
          <i class="bi bi-box-arrow-right"></i> Sign out
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

<!-- Toast container -->
<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Traveloka/CustomerDashboard/assets/js/customer.js"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
<?php
if (!empty($msg)) {
    [$_ftype, $_ftext] = explode(':', $msg, 2);
    $_ftype = in_array($_ftype, ['success','error','warning','info']) ? $_ftype : 'info';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){notify(' . json_encode($_ftext) . ',' . json_encode($_ftype) . ');});</script>';
}
?>
<script>
function toggleSidebar() {
  document.getElementById('appSidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('appSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}
</script>
</body>
</html>
