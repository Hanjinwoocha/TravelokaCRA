<?php
require_once __DIR__ . '/../includes/db.php';
$pageTitle  = 'My Profile';
$activePage = 'profile';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/auth/customer_login.php'); exit; }
$custId  = $_SESSION['customer_id'] ?? '';
$isGuest = !empty($_SESSION['is_guest']) && empty($_SESSION['customer_logged_in']);

$success = '';
$errors  = [];

$cust = fb()->getDoc('users', $custId);
if (!$cust) { header('Location: /Traveloka/auth/customer_login.php'); exit; }

$nameRx   = '/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/u';
$mobileRx = '/^(\+?63|0)9\d{9}$/';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $title  = trim($_POST['cust_title']        ?? '');
    $first  = trim($_POST['cust_firstname']    ?? '');
    $mid    = trim($_POST['cust_middlename']   ?? '');
    $last   = trim($_POST['cust_lastname']     ?? '');
    $mobile = trim($_POST['cust_mobilenumber'] ?? '');

    if ($first === '')                                      $errors['first']  = 'First name is required.';
    elseif (!preg_match($nameRx, $first))                  $errors['first']  = 'Letters, spaces, and hyphens only.';
    elseif (strlen($first) < 2 || strlen($first) > 50)    $errors['first']  = 'First name must be 2–50 characters.';

    if ($mid !== '' && !preg_match($nameRx, $mid))         $errors['mid']    = 'Letters, spaces, and hyphens only.';
    elseif ($mid !== '' && strlen($mid) > 50)              $errors['mid']    = 'Middle name must be 50 characters or fewer.';

    if ($last === '')                                       $errors['last']   = 'Last name is required.';
    elseif (!preg_match($nameRx, $last))                   $errors['last']   = 'Letters, spaces, and hyphens only.';
    elseif (strlen($last) < 2 || strlen($last) > 50)      $errors['last']   = 'Last name must be 2–50 characters.';

    $cleanMobile = preg_replace('/\s+/', '', $mobile);
    if ($mobile === '')                                     $errors['mobile'] = 'Mobile number is required.';
    elseif (!preg_match($mobileRx, $cleanMobile))          $errors['mobile'] = 'Enter a valid PH number (e.g. 09171234567).';

    if (empty($errors)) {
        $fullName = trim("$first $last");
        fb()->updateDoc('users', $custId, [
            'title'      => $title,
            'firstName'  => $first,
            'middleName' => $mid,
            'lastName'   => $last,
            'fullName'   => $fullName,
            'phone'      => $mobile,
        ]);
        $_SESSION['customer_name'] = $fullName;
        $success = 'success:Profile updated successfully.';
        $cust['title']      = $title;
        $cust['firstName']  = $first;
        $cust['middleName'] = $mid;
        $cust['lastName']   = $last;
        $cust['fullName']   = $fullName;
        $cust['phone']      = $mobile;
    }
    $activeForm = 'profile';
}

// Change password via Firebase Auth update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $new     = trim($_POST['new_password']     ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($new === '')                              $errors['new']     = 'New password is required.';
    elseif (strlen($new) < 8)                    $errors['new']     = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new))
                                                  $errors['new']     = 'Must contain at least one letter and one number.';

    if ($confirm === '')                          $errors['confirm'] = 'Please confirm your new password.';
    elseif (!isset($errors['new']) && $new !== $confirm) $errors['confirm'] = 'Passwords do not match.';

    if (empty($errors)) {
        $result = fb()->updatePassword($custId, $new);
        if ($result === true) {
            $success = 'success:Password changed successfully.';
        } else {
            $errors['general'] = 'Password change failed. Please try again.';
        }
    }
    $activeForm = 'password';
}

$nameParts = explode(' ', $cust['fullName'] ?? '');
$firstName = $cust['firstName'] ?? ($nameParts[0] ?? '');
$lastName  = $cust['lastName']  ?? (count($nameParts) > 1 ? end($nameParts) : '');
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

include __DIR__ . '/../includes/header.php';
?>

<!-- Profile hero -->
<div class="search-hero" style="margin-bottom:28px">
  <div style="display:flex;align-items:center;gap:20px;position:relative;z-index:1;flex-wrap:wrap">
    <div class="profile-avatar" style="width:72px;height:72px;font-size:26px;flex-shrink:0">
      <?= htmlspecialchars($initials ?: '?') ?>
    </div>
    <div>
      <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px">
        <i class="bi bi-person-circle" style="color:var(--tv-orange)"></i> My Profile
      </div>
      <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:3px">
        <?= htmlspecialchars(trim(($cust['title'] ? $cust['title'].' ' : '') . ($cust['fullName'] ?? ''))) ?>
      </h1>
      <div style="font-size:13px;color:rgba(255,255,255,.55)"><?= htmlspecialchars($cust['email'] ?? '') ?></div>
    </div>
  </div>
</div>

<?php if (isset($errors['general'])): ?>
<div class="alert-tv error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>
<?php if ($success): [$sType, $sText] = explode(':', $success, 2); ?>
<div class="alert-tv success" style="margin-bottom:20px"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($sText) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Left sidebar -->
  <div class="col-lg-3">
    <div class="content-card">
      <div class="card-body-tv" style="padding:20px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px">Quick links</div>
        <a href="/Traveloka/CustomerDashboard/pages/bookings.php" class="profile-quick-link">
          <i class="bi bi-house"></i> My Bookings
        </a>
        <a href="/Traveloka/CustomerDashboard/pages/search.php" class="profile-quick-link">
          <i class="bi bi-search"></i> Find a Car
        </a>
        <div style="border-top:1px solid var(--border);margin:12px 0"></div>
        <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal"
           class="profile-quick-link" style="color:rgba(220,38,38,.7)">
          <i class="bi bi-box-arrow-right"></i> Log out
        </a>
      </div>
    </div>
  </div>

  <!-- Right: forms -->
  <div class="col-lg-9">

    <!-- Personal info -->
    <div class="content-card">
      <div class="card-header-tv">
        <h2 class="card-title-tv"><i class="bi bi-person" style="color:var(--tv-blue);margin-right:6px"></i>Personal information</h2>
      </div>
      <div class="card-body-tv">
        <?php $pf = isset($activeForm) && $activeForm === 'profile'; ?>
        <form method="post" id="profileForm" novalidate>
          <input type="hidden" name="form" value="profile">
          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label-tv">Title</label>
              <select name="cust_title" class="tv-select">
                <option value="">—</option>
                <?php foreach (['Mr.','Ms.','Mrs.'] as $t): ?>
                <option value="<?= $t ?>" <?= ($cust['title'] ?? '')===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label-tv">First name *</label>
              <input type="text" name="cust_firstname" id="p_first" maxlength="50"
                     class="tv-input <?= ($pf && isset($errors['first'])) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($cust['firstName'] ?? $firstName) ?>">
              <?php if ($pf && isset($errors['first'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['first']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-5">
              <label class="form-label-tv">Middle name <span style="font-weight:400;text-transform:none;font-size:11px">(optional)</span></label>
              <input type="text" name="cust_middlename" id="p_mid" maxlength="50"
                     class="tv-input <?= ($pf && isset($errors['mid'])) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($cust['middleName'] ?? '') ?>">
              <?php if ($pf && isset($errors['mid'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['mid']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Last name *</label>
              <input type="text" name="cust_lastname" id="p_last" maxlength="50"
                     class="tv-input <?= ($pf && isset($errors['last'])) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($cust['lastName'] ?? $lastName) ?>">
              <?php if ($pf && isset($errors['last'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['last']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Mobile number *</label>
              <input type="text" name="cust_mobilenumber" id="p_mobile" maxlength="20"
                     placeholder="09171234567"
                     class="tv-input <?= ($pf && isset($errors['mobile'])) ? 'is-invalid' : '' ?>"
                     value="<?= htmlspecialchars($cust['phone'] ?? '') ?>">
              <?php if ($pf && isset($errors['mobile'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['mobile']) ?></div>
              <?php else: ?>
              <div class="pass-hint-tv">Format: 09XXXXXXXXX or +639XXXXXXXXX</div>
              <?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label-tv">Email address</label>
              <input type="email" class="tv-input" value="<?= htmlspecialchars($cust['email'] ?? '') ?>" disabled
                     style="background:#F4F6FA;color:var(--text-muted);cursor:not-allowed">
              <div class="pass-hint-tv">Email address cannot be changed.</div>
            </div>
          </div>
          <div style="margin-top:22px;display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn-tv-primary">
              <i class="bi bi-check-lg"></i> Save changes
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="content-card">
      <div class="card-header-tv">
        <h2 class="card-title-tv"><i class="bi bi-lock" style="color:var(--tv-blue);margin-right:6px"></i>Change password</h2>
      </div>
      <div class="card-body-tv">
        <?php $pw = isset($activeForm) && $activeForm === 'password'; ?>
        <form method="post" id="passForm" novalidate>
          <input type="hidden" name="form" value="password">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-tv">New password *</label>
              <input type="password" name="new_password" id="p_new"
                     class="tv-input <?= ($pw && isset($errors['new'])) ? 'is-invalid' : '' ?>"
                     oninput="profileStrength(this.value)">
              <div class="pass-strength-tv"><div class="pass-strength-tv-bar" id="profileStrBar"></div></div>
              <?php if ($pw && isset($errors['new'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['new']) ?></div>
              <?php else: ?>
              <div class="pass-hint-tv" id="profileStrHint">Min 8 characters with a letter and a number</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label-tv">Confirm new password *</label>
              <input type="password" name="confirm_password" id="p_confirm"
                     class="tv-input <?= ($pw && isset($errors['confirm'])) ? 'is-invalid' : '' ?>">
              <?php if ($pw && isset($errors['confirm'])): ?>
              <div class="field-err-tv"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['confirm']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div style="margin-top:22px">
            <button type="submit" class="btn-tv-ghost">
              <i class="bi bi-lock"></i> Change password
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
const nameRxP   = /^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/;
const mobileRxP = /^(\+?63|0)9\d{9}$/;

function pfErr(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('is-invalid'); el.classList.remove('is-valid');
  let d = el.parentElement.querySelector('.field-err-tv');
  if (!d) { d = document.createElement('div'); d.className = 'field-err-tv'; d.innerHTML = '<i class="bi bi-exclamation-circle"></i><span></span>'; el.insertAdjacentElement('afterend', d); }
  const sp = d.querySelector('span'); if (sp) sp.textContent = msg;
  d.style.display = 'flex';
}
function pfOk(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('is-invalid');
  if (el.value.trim()) el.classList.add('is-valid');
  const d = el.parentElement.querySelector('.field-err-tv');
  if (d) d.style.display = 'none';
}
function pfValidate(id) {
  const el = document.getElementById(id);
  if (!el) return true;
  const v = el.value.trim();
  if (id === 'p_first') {
    if (!v)               { pfErr(id,'First name is required.');                     return false; }
    if (!nameRxP.test(v)) { pfErr(id,'Letters, spaces, and hyphens only.');          return false; }
    if (v.length < 2)     { pfErr(id,'At least 2 characters required.');             return false; }
  }
  if (id === 'p_last') {
    if (!v)               { pfErr(id,'Last name is required.');                      return false; }
    if (!nameRxP.test(v)) { pfErr(id,'Letters, spaces, and hyphens only.');          return false; }
    if (v.length < 2)     { pfErr(id,'At least 2 characters required.');             return false; }
  }
  if (id === 'p_mid' && v && !nameRxP.test(v)) { pfErr(id,'Letters, spaces, and hyphens only.'); return false; }
  if (id === 'p_mobile') {
    if (!v)                                    { pfErr(id,'Mobile number is required.');           return false; }
    if (!mobileRxP.test(v.replace(/\s+/g,''))) { pfErr(id,'Enter a valid PH number (e.g. 09171234567).'); return false; }
  }
  if (id === 'p_new') {
    if (!v)               { pfErr(id,'New password is required.');                   return false; }
    if (v.length < 8)     { pfErr(id,'At least 8 characters required.');             return false; }
    if (!/[A-Za-z]/.test(v)||!/[0-9]/.test(v)) { pfErr(id,'Must contain a letter and a number.'); return false; }
  }
  if (id === 'p_confirm') {
    const nv = document.getElementById('p_new')?.value ?? '';
    if (!v)          { pfErr(id,'Please confirm your new password.'); return false; }
    if (v !== nv)    { pfErr(id,'Passwords do not match.');           return false; }
  }
  pfOk(id); return true;
}

['p_first','p_mid','p_last','p_mobile'].forEach(id => {
  document.getElementById(id)?.addEventListener('blur', () => pfValidate(id));
});
['p_new','p_confirm'].forEach(id => {
  document.getElementById(id)?.addEventListener('blur', () => pfValidate(id));
});

document.getElementById('profileForm')?.addEventListener('submit', e => {
  const ok = ['p_first','p_mid','p_last','p_mobile'].map(pfValidate).every(Boolean);
  if (!ok) e.preventDefault();
});
document.getElementById('passForm')?.addEventListener('submit', e => {
  const ok = ['p_new','p_confirm'].map(pfValidate).every(Boolean);
  if (!ok) e.preventDefault();
});

function profileStrength(val) {
  const bar = document.getElementById('profileStrBar');
  const hint = document.getElementById('profileStrHint');
  if (!bar) return;
  let s = 0;
  if (val.length >= 8) s++;
  if (/[A-Za-z]/.test(val) && /[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const lvls = [{w:'25%',bg:'#EF4444',l:'Too weak'},{w:'55%',bg:'#F59E0B',l:'Fair'},{w:'80%',bg:'#3B82F6',l:'Good'},{w:'100%',bg:'#16A34A',l:'Strong'}];
  const lv = val.length === 0 ? null : (lvls[s] ?? lvls[0]);
  bar.style.width = lv ? lv.w : '0'; bar.style.background = lv ? lv.bg : '';
  if (hint) { hint.textContent = lv ? lv.l : 'Min 8 characters with a letter and a number'; hint.style.color = lv ? lv.bg : ''; }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
