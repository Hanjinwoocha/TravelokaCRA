<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

require_once __DIR__ . '/../includes/firebase.php';
$error  = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    $title  = trim($_POST['cust_title']        ?? '');
    $first  = trim($_POST['cust_firstname']    ?? '');
    $mid    = trim($_POST['cust_middlename']   ?? '');
    $last   = trim($_POST['cust_lastname']     ?? '');
    $mobile = trim($_POST['cust_mobilenumber'] ?? '');
    $email  = trim($_POST['cust_email']        ?? '');
    $pass   = trim($_POST['password']          ?? '');

    if ($first === '') {
        $errors['first'] = 'First name is required.';
    } elseif (!preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/', $first)) {
        $errors['first'] = 'First name may only contain letters, spaces, and hyphens.';
    } elseif (strlen($first) < 2 || strlen($first) > 50) {
        $errors['first'] = 'First name must be 2–50 characters.';
    }

    if ($mid !== '' && !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/', $mid)) {
        $errors['mid'] = 'Middle name may only contain letters, spaces, and hyphens.';
    } elseif ($mid !== '' && strlen($mid) > 50) {
        $errors['mid'] = 'Middle name must be 50 characters or fewer.';
    }

    if ($last === '') {
        $errors['last'] = 'Last name is required.';
    } elseif (!preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/', $last)) {
        $errors['last'] = 'Last name may only contain letters, spaces, and hyphens.';
    } elseif (strlen($last) < 2 || strlen($last) > 50) {
        $errors['last'] = 'Last name must be 2–50 characters.';
    }

    if ($mobile === '') {
        $errors['mobile'] = 'Mobile number is required.';
    } elseif (!preg_match('/^(\+?63|0)9\d{9}$/', preg_replace('/\s+/', '', $mobile))) {
        $errors['mobile'] = 'Enter a valid PH mobile number (e.g. 09171234567).';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($pass === '') {
        $errors['pass'] = 'Password is required.';
    } elseif (strlen($pass) < 8) {
        $errors['pass'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        $errors['pass'] = 'Password must contain at least one letter and one number.';
    }

    if (empty($errors)) {
        $fullName = trim($first . ($mid ? ' ' . $mid : '') . ' ' . $last);
        $res = fb()->signUp($email, $pass, $fullName);

        if (isset($res['error'])) {
            $msg = $res['error']['message'] ?? '';
            $errors['email'] = str_contains($msg, 'EMAIL_EXISTS')
                ? 'An account with this email already exists.'
                : 'Registration failed: ' . $msg;
        } else {
            $uid = $res['localId'];
            fb()->setDoc('users', $uid, [
                'uid'        => $uid,
                'role'       => 'customer',
                'title'      => $title,
                'fullName'   => $fullName,
                'email'      => $email,
                'phone'      => $mobile,
                'photoUrl'   => '',
                'fcmToken'   => '',
                'promoNotificationsEnabled' => true,
                'createdAt'  => Firebase::nowMs(),
            ]);
            unset($_SESSION['is_guest']);
            $_SESSION['customer_logged_in'] = true;
            $_SESSION['customer_id']        = $uid;
            $_SESSION['customer_name']      = $first;
            header('Location: /Traveloka/CustomerDashboard/index.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — Traveloka Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    :root { --blue:#0064D2; --blue-dk:#004FAA; --orange:#FF6000; --navy:#001A3C; --border:#E4E8EF; --muted:#637083; }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:url('https://images.unsplash.com/photo-1485291571150-772bcfc10da5?w=1800&q=80') center/cover no-repeat fixed;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    body::before{content:'';position:fixed;inset:0;background:linear-gradient(150deg,rgba(0,15,45,0.78) 0%,rgba(0,30,80,0.6) 55%,rgba(0,10,30,0.5) 100%)}
    .card-wrap{position:relative;z-index:1;background:#fff;border-radius:22px;box-shadow:0 24px 64px rgba(0,0,0,0.28);width:100%;max-width:980px;display:flex;overflow:hidden;min-height:540px}
    .hero-panel{background:linear-gradient(145deg,var(--navy) 50%,#002B70);flex:0 0 340px;padding:48px 40px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
    .hero-panel::before{content:'';position:absolute;width:260px;height:260px;border-radius:50%;border:44px solid rgba(255,96,0,0.1);top:-80px;right:-80px}
    .hero-panel::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(0,100,210,0.1);bottom:-60px;left:-60px}
    .hp-brand{font-family:'Plus Jakarta Sans',sans-serif;font-weight:900;font-size:1.5rem;color:#fff;text-decoration:none;position:relative;z-index:1;letter-spacing:-.4px}
    .hp-brand .t{color:var(--orange)}
    .hp-body{position:relative;z-index:1}
    .hp-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.65rem;color:#fff;line-height:1.2;letter-spacing:-.4px;margin-bottom:12px}
    .hp-title span{color:var(--orange)}
    .hp-sub{font-size:13.5px;color:rgba(255,255,255,.5);line-height:1.7;margin-bottom:28px}
    .hp-perk{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
    .hp-perk-icon{width:32px;height:32px;border-radius:8px;background:rgba(0,100,210,.25);color:rgba(255,255,255,.8);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;margin-top:1px}
    .hp-perk-title{font-size:13px;font-weight:600;color:#fff;line-height:1.3}
    .hp-perk-sub{font-size:11.5px;color:rgba(255,255,255,.45);margin-top:2px}
    .hp-foot{font-size:11px;color:rgba(255,255,255,.18);position:relative;z-index:1}
    .form-panel{flex:1;padding:44px 48px;display:flex;flex-direction:column;justify-content:center;overflow-y:auto}
    .fp-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--orange);margin-bottom:8px}
    .fp-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.5rem;color:var(--navy);letter-spacing:-.3px;margin-bottom:4px}
    .fp-sub{font-size:13px;color:var(--muted);margin-bottom:22px}
    .err-box{background:#FEE2E2;border:1px solid #FCA5A5;color:#991B1B;border-radius:10px;padding:11px 14px;font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:18px}
    .field-err{font-size:11.5px;color:#DC2626;margin-top:4px;display:flex;align-items:center;gap:4px}
    .field-err i{font-size:11px;flex-shrink:0}
    .f-label{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px}
    .f-wrap{position:relative}
    .f-wrap i.icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#B0BAC8;font-size:13px;pointer-events:none}
    .f-input,.f-select{width:100%;height:40px;border:1.5px solid var(--border);border-radius:9px;padding:0 12px;font-size:13.5px;font-family:'Inter',sans-serif;color:#0D1B30;background:#FAFBFD;outline:none;transition:border-color .14s,box-shadow .14s}
    .f-input.has-icon{padding-left:36px}
    .f-input:focus,.f-select:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(0,100,210,.09)}
    .f-input.is-invalid,.f-select.is-invalid{border-color:#DC2626;background:#FFF5F5}
    .f-input.is-valid{border-color:#16A34A}
    .f-grid{display:grid;gap:12px}
    .f-grid-3{grid-template-columns:0.7fr 1fr 1fr}
    .f-grid-2{grid-template-columns:1fr 1fr}
    .f-mb{margin-bottom:12px}
    .pass-strength{margin-top:5px;height:3px;border-radius:99px;background:var(--border);overflow:hidden}
    .pass-strength-bar{height:100%;border-radius:99px;width:0;transition:width .3s,background .3s}
    .pass-hint{font-size:11px;color:var(--muted);margin-top:4px}
    .btn-submit{width:100%;height:44px;background:var(--blue);color:#fff;border:none;border-radius:9px;font-size:14.5px;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(0,100,210,.28);margin-top:18px;transition:background .14s,transform .1s}
    .btn-submit:hover{background:var(--blue-dk);transform:translateY(-1px)}
    .fp-foot{font-size:12.5px;color:var(--muted);text-align:center;margin-top:14px}
    .fp-foot a{color:var(--blue);font-weight:600;text-decoration:none}
    @media(max-width:767px){.hero-panel{display:none}.form-panel{padding:36px 28px}.f-grid-3{grid-template-columns:1fr 1fr}}
    @media(max-width:480px){.f-grid-3,.f-grid-2{grid-template-columns:1fr}.form-panel{padding:28px 20px}}
  </style>
</head>
<body>
<div class="card-wrap">
  <div class="hero-panel">
    <a href="/Traveloka/" class="hp-brand"><span class="t">t</span>raveloka</a>
    <div class="hp-body">
      <h2 class="hp-title">Join <span>thousands</span><br>of satisfied customers.</h2>
      <p class="hp-sub">Create a free account and unlock the full Traveloka experience.</p>
      <div class="hp-perk"><div class="hp-perk-icon"><i class="bi bi-ticket-perforated"></i></div><div><div class="hp-perk-title">Instant e-tickets</div><div class="hp-perk-sub">QR-code booking confirmation sent immediately</div></div></div>
      <div class="hp-perk"><div class="hp-perk-icon"><i class="bi bi-tag"></i></div><div><div class="hp-perk-title">Exclusive coupon codes</div><div class="hp-perk-sub">Up to 50% off for registered members</div></div></div>
      <div class="hp-perk"><div class="hp-perk-icon"><i class="bi bi-clock-history"></i></div><div><div class="hp-perk-title">Booking history</div><div class="hp-perk-sub">Track all your past and upcoming rentals</div></div></div>
    </div>
    <div class="hp-foot">&copy; <?= date('Y') ?> Traveloka Car Rental</div>
  </div>

  <div class="form-panel">
    <div class="fp-eyebrow">New account</div>
    <h1 class="fp-title">Create your account</h1>
    <p class="fp-sub">Fill in your details below. Takes less than a minute.</p>

    <?php if (!empty($errors)): ?>
    <div class="err-box"><i class="bi bi-exclamation-circle-fill"></i>Please fix the errors below before continuing.</div>
    <?php endif; ?>

    <form method="post" id="regForm" novalidate>
      <input type="hidden" name="form" value="register">

      <div class="f-grid f-grid-3 f-mb">
        <div>
          <label class="f-label">Title</label>
          <select name="cust_title" class="f-select">
            <option value="">—</option>
            <?php foreach (['Mr.','Ms.','Mrs.'] as $t): ?>
            <option <?= ($_POST['cust_title'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f-label">First name *</label>
          <input type="text" name="cust_firstname" id="f_first"
                 class="f-input <?= isset($errors['first']) ? 'is-invalid' : '' ?>"
                 maxlength="50" value="<?= htmlspecialchars($_POST['cust_firstname'] ?? '') ?>">
          <?php if (isset($errors['first'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['first']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="f-label">Last name *</label>
          <input type="text" name="cust_lastname" id="f_last"
                 class="f-input <?= isset($errors['last']) ? 'is-invalid' : '' ?>"
                 maxlength="50" value="<?= htmlspecialchars($_POST['cust_lastname'] ?? '') ?>">
          <?php if (isset($errors['last'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['last']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="f-grid f-grid-2 f-mb">
        <div>
          <label class="f-label">Middle name <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
          <input type="text" name="cust_middlename" id="f_mid"
                 class="f-input <?= isset($errors['mid']) ? 'is-invalid' : '' ?>"
                 maxlength="50" value="<?= htmlspecialchars($_POST['cust_middlename'] ?? '') ?>">
          <?php if (isset($errors['mid'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['mid']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="f-label">Mobile number *</label>
          <div class="f-wrap"><i class="bi bi-phone icon"></i>
            <input type="text" name="cust_mobilenumber" id="f_mobile"
                   class="f-input has-icon <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>"
                   maxlength="20" placeholder="09171234567"
                   value="<?= htmlspecialchars($_POST['cust_mobilenumber'] ?? '') ?>">
          </div>
          <?php if (isset($errors['mobile'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['mobile']) ?></div>
          <?php else: ?><div class="pass-hint">Format: 09XXXXXXXXX or +639XXXXXXXXX</div><?php endif; ?>
        </div>
      </div>

      <div class="f-grid f-grid-2 f-mb">
        <div>
          <label class="f-label">Email *</label>
          <div class="f-wrap"><i class="bi bi-envelope icon"></i>
            <input type="email" name="cust_email" id="f_email"
                   class="f-input has-icon <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   maxlength="100" value="<?= htmlspecialchars($_POST['cust_email'] ?? '') ?>">
          </div>
          <?php if (isset($errors['email'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="f-label">Password *</label>
          <div class="f-wrap"><i class="bi bi-lock icon"></i>
            <input type="password" name="password" id="f_pass"
                   class="f-input has-icon <?= isset($errors['pass']) ? 'is-invalid' : '' ?>"
                   oninput="updateStrength(this.value)">
          </div>
          <div class="pass-strength"><div class="pass-strength-bar" id="strengthBar"></div></div>
          <?php if (isset($errors['pass'])): ?><div class="field-err"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['pass']) ?></div>
          <?php else: ?><div class="pass-hint" id="strengthHint">Min 8 characters with a letter and a number</div><?php endif; ?>
        </div>
      </div>

      <button type="submit" class="btn-submit"><i class="bi bi-person-plus"></i> Create account</button>
    </form>

    <p class="fp-foot">Already have an account? <a href="/Traveloka/auth/signin.php">Sign in</a></p>
  </div>
</div>

<script>
function updateStrength(val) {
  const bar=document.getElementById('strengthBar'),hint=document.getElementById('strengthHint');
  if(!bar)return;
  let s=0;
  if(val.length>=8)s++;
  if(/[A-Za-z]/.test(val)&&/[0-9]/.test(val))s++;
  if(/[^A-Za-z0-9]/.test(val))s++;
  const lvls=[{w:'25%',bg:'#EF4444',l:'Too weak'},{w:'55%',bg:'#F59E0B',l:'Fair'},{w:'80%',bg:'#3B82F6',l:'Good'},{w:'100%',bg:'#16A34A',l:'Strong'}];
  const lvl=val.length===0?null:(lvls[s]??lvls[0]);
  bar.style.width=lvl?lvl.w:'0';bar.style.background=lvl?lvl.bg:'';
  if(hint){hint.textContent=lvl?lvl.l:'Min 8 characters with a letter and a number';hint.style.color=lvl?lvl.bg:'';}
}
</script>
</body>
</html>
