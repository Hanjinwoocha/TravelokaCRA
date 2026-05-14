</main><!-- end page-content -->

  <!-- Dashboard footer -->
  <footer style="background:#040E20;color:rgba(255,255,255,0.5);padding:48px 32px 0;margin-top:auto">
    <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1.4fr;gap:36px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,0.07)">
      <!-- Brand -->
      <div>
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:900;font-size:1.3rem;color:#fff;margin-bottom:10px">
          <span style="color:var(--tv-orange)">t</span>raveloka
          <span style="font-size:11px;font-weight:600;background:rgba(0,100,210,0.3);color:rgba(255,255,255,0.7);padding:2px 9px;border-radius:6px;margin-left:8px;vertical-align:middle">Admin</span>
        </div>
        <p style="font-size:12.5px;line-height:1.7;max-width:220px;margin-bottom:18px">Central control panel for managing the Traveloka car rental platform.</p>
        <div style="font-size:12px;display:flex;flex-direction:column;gap:6px">
          <span><i class="bi bi-person-fill" style="color:var(--tv-orange);margin-right:6px"></i><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator') ?></span>
          <span><i class="bi bi-shield-lock-fill" style="color:var(--tv-orange);margin-right:6px"></i>Administrator access</span>
        </div>
      </div>
      <!-- Manage -->
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px;margin-bottom:14px">Manage</div>
        <?php foreach([
          ['/Traveloka/AdminDashboard/index.php',            'bi-speedometer2',  'Dashboard'],
          ['/Traveloka/AdminDashboard/pages/providers.php',  'bi-building',      'Providers'],
          ['/Traveloka/AdminDashboard/pages/customers.php',  'bi-people',        'Customers'],
          ['/Traveloka/AdminDashboard/pages/bookings.php',   'bi-calendar-check','Bookings'],
          ['/Traveloka/AdminDashboard/pages/cars.php',       'bi-car-front',     'All cars'],
        ] as [$href,$icon,$label]): ?>
        <a href="<?= $href ?>" style="display:block;color:rgba(255,255,255,0.48);font-size:12.5px;text-decoration:none;margin-bottom:8px;transition:color .14s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.48)'">
          <i class="bi <?= $icon ?>" style="margin-right:6px;font-size:11px"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>
      <!-- Quick actions -->
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px;margin-bottom:14px">Quick actions</div>
        <?php foreach([
          ['/Traveloka/index.php',                            'bi-house',              'View landing page'],
          ['/Traveloka/AdminDashboard/pages/reports.php',     'bi-graph-up',           'Reports'],
          ['/Traveloka/AdminDashboard/pages/coupons.php',     'bi-ticket-perforated',  'Coupons'],
          ['javascript:void(0)',                               'bi-box-arrow-right',    'Log out'],
        ] as [$href,$icon,$label]):
          $isLogout = $label === 'Log out';
        ?>
        <a href="<?= $href ?>" <?= $isLogout ? 'data-bs-toggle="modal" data-bs-target="#logoutModal"' : '' ?> style="display:block;color:rgba(255,255,255,0.48);font-size:12.5px;text-decoration:none;margin-bottom:8px;transition:color .14s" onmouseover="this.style.color='<?= $isLogout ? '#EF4444' : '#fff' ?>'" onmouseout="this.style.color='rgba(255,255,255,0.48)'">
          <i class="bi <?= $icon ?>" style="margin-right:6px;font-size:11px"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>
      <!-- Contact -->
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px;margin-bottom:14px">Support</div>
        <p style="font-size:12.5px;margin-bottom:14px;line-height:1.6">Need help? Reach out to the development team or check system logs.</p>
        <a href="mailto:support@traveloka.ph" style="display:inline-flex;align-items:center;gap:8px;color:rgba(255,255,255,0.48);font-size:12.5px;text-decoration:none;transition:color .14s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.48)'">
          <i class="bi bi-envelope"></i> support@traveloka.ph
        </a>
      </div>
    </div>
    <!-- Bottom bar -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;padding:18px 0;font-size:11.5px">
      <span>&copy; <?= date('Y') ?> Traveloka Car Rental. All rights reserved.</span>
      <div style="display:flex;gap:20px">
        <a href="#" style="color:rgba(255,255,255,0.48);text-decoration:none;transition:color .14s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.48)'">Privacy</a>
        <a href="#" style="color:rgba(255,255,255,0.48);text-decoration:none;transition:color .14s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.48)'">Terms</a>
      </div>
    </div>
  </footer>
</div><!-- end main-wrapper -->

<!-- Global Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-body" style="padding:36px 28px 20px;text-align:center">
        <div class="logout-modal-icon"><i class="bi bi-box-arrow-right"></i></div>
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:17px;color:var(--text-primary);margin-bottom:10px">Log out?</h5>
        <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0">
          You're signed in as <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Administrator') ?></strong>.<br>
          You'll need to sign in again to access the dashboard.
        </p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 28px;justify-content:center;gap:10px">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal" style="min-width:110px">Stay</button>
        <a href="/Traveloka/auth/logout.php" class="btn-tv-orange" style="min-width:110px;justify-content:center">
          <i class="bi bi-box-arrow-right"></i> Log out
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Global Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-body" style="padding:36px 28px 20px;text-align:center">
        <div class="del-modal-icon"><i class="bi bi-trash3-fill"></i></div>
        <h5 id="delModalTitle" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:17px;color:var(--text-primary);margin-bottom:10px">Delete item?</h5>
        <p id="delModalMsg"   style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin:0"></p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 28px;justify-content:center;gap:10px">
        <button type="button" class="btn-tv-ghost" data-bs-dismiss="modal" style="min-width:110px">Cancel</button>
        <button type="button" id="delModalConfirm" class="btn-tv-primary" style="background:#DC2626;min-width:110px">
          <i class="bi bi-trash3"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="/Traveloka/AdminDashboard/assets/js/admin.js"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
<?php
// Auto-fire toast from $msg (format "type:text") set by any page
if (!empty($msg)) {
    [$_ftype, $_ftext] = explode(':', $msg, 2);
    $_ftype = in_array($_ftype, ['success','error','warning','info']) ? $_ftype : 'info';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){notify(' . json_encode(htmlspecialchars_decode($_ftext)) . ',' . json_encode($_ftype) . ');});</script>';
}
// Auto-fire toasts from $_flash session array (set via flash() helper in db.php)
if (!empty($_SESSION['_flash'])) {
    foreach ($_SESSION['_flash'] as $_fe) {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){notify(' . json_encode($_fe['text']) . ',' . json_encode($_fe['type']) . ');});</script>';
    }
    unset($_SESSION['_flash']);
}
?>
</body>
</html>