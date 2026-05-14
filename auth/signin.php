<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Already authenticated — redirect to appropriate dashboard
if (!empty($_SESSION['admin_logged_in']))    { header('Location: /Traveloka/AdminDashboard/index.php'); exit; }
if (!empty($_SESSION['provider_logged_in'])) { header('Location: /Traveloka/ProviderDashboard/index.php'); exit; }
if (!empty($_SESSION['customer_logged_in'])) { header('Location: /Traveloka/CustomerDashboard/index.php'); exit; }

require_once __DIR__ . '/../AdminDashboard/includes/db.php';
$error = '';

// Continue as guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guest') {
    $_SESSION['is_guest']           = true;
    $_SESSION['customer_logged_in'] = false;
    $_SESSION['customer_name']      = 'Guest';
    header('Location: /Traveloka/CustomerDashboard/pages/search.php'); exit;
}

// Unified login: try admin → provider → customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (!$email || !$pass) {
        $error = 'Please enter both email and password.';
    } else {
        // 1. Admin (hardcoded)
        if (($email === 'admin' || $email === 'admin@traveloka.com') && $pass === 'admin123') {
            unset($_SESSION['is_guest']);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = 'admin';
            header('Location: /Traveloka/AdminDashboard/index.php'); exit;
        }

        // 2. Provider
        $matchedAs = null;
        try {
            $stmt = $pdo->prepare("SELECT prov_id, prov_name, prov_status FROM car_provider WHERE prov_email = ? AND prov_password = ? LIMIT 1");
            $stmt->execute([$email, md5($pass)]);
            $prov = $stmt->fetch();
            if ($prov) {
                $matchedAs = 'provider';
                if ($prov['prov_status'] === 'approved') {
                    unset($_SESSION['is_guest']);
                    $_SESSION['provider_logged_in'] = true;
                    $_SESSION['provider_id']        = $prov['prov_id'];
                    $_SESSION['provider_name']      = $prov['prov_name'];
                    header('Location: /Traveloka/ProviderDashboard/index.php'); exit;
                } elseif ($prov['prov_status'] === 'pending') {
                    $error = 'pending';
                } else {
                    $error = 'rejected';
                }
            }
        } catch (Exception $e) {}

        // 3. Customer
        if (!$matchedAs) {
            try {
                $stmt = $pdo->prepare("SELECT cust_id, cust_firstname FROM customer WHERE cust_email = ? AND cust_password = ? LIMIT 1");
                $stmt->execute([$email, md5($pass)]);
                $cust = $stmt->fetch();
                if ($cust) {
                    unset($_SESSION['is_guest']);
                    $_SESSION['customer_logged_in'] = true;
                    $_SESSION['customer_id']        = $cust['cust_id'];
                    $_SESSION['customer_name']      = $cust['cust_firstname'];
                    header('Location: /Traveloka/CustomerDashboard/index.php'); exit;
                }
            } catch (Exception $e) {}
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Traveloka Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #0064D2; --blue-dk: #004FAA;
      --orange: #FF6000; --navy: #001A3C;
      --border: #E4E8EF; --muted: #637083;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: url('https://images.unsplash.com/photo-1485291571150-772bcfc10da5?w=1800&q=80') center/cover no-repeat fixed;
      min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
    }
    body::before {
      content: ''; position: fixed; inset: 0;
      background: linear-gradient(150deg, rgba(0,15,45,0.78) 0%, rgba(0,30,80,0.6) 55%, rgba(0,10,30,0.5) 100%);
    }

    .card-wrap {
      position: relative; z-index: 1;
      background: #fff; border-radius: 22px;
      box-shadow: 0 24px 64px rgba(0,0,0,0.28);
      width: 100%; max-width: 980px;
      display: flex; overflow: hidden;
      min-height: 560px;
    }

    /* ── Hero panel ── */
    .hero-panel {
      background: linear-gradient(145deg, var(--navy) 50%, #002B70);
      flex: 0 0 400px; padding: 52px 44px;
      display: flex; flex-direction: column; justify-content: space-between;
      position: relative; overflow: hidden;
    }
    .hero-panel::before { content:''; position:absolute; width:300px; height:300px; border-radius:50%; border:50px solid rgba(255,96,0,0.1); top:-90px; right:-90px; }
    .hero-panel::after  { content:''; position:absolute; width:200px; height:200px; border-radius:50%; background:rgba(0,100,210,0.1); bottom:-60px; left:-60px; }
    .hp-brand { font-family:'Plus Jakarta Sans',sans-serif; font-weight:900; font-size:1.6rem; color:#fff; text-decoration:none; position:relative; z-index:1; letter-spacing:-.4px; }
    .hp-brand .t { color:var(--orange); }
    .hp-body { position:relative; z-index:1; }
    .hp-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:2rem; color:#fff; line-height:1.15; letter-spacing:-.5px; margin-bottom:12px; }
    .hp-title span { color:var(--orange); }
    .hp-sub { font-size:13.5px; color:rgba(255,255,255,.5); line-height:1.7; margin-bottom:32px; max-width:300px; }
    .hp-role { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .hp-role-dot { width:30px; height:30px; border-radius:9px; background:rgba(0,100,210,.25); color:rgba(255,255,255,.8); display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .hp-role-title { font-size:13.5px; font-weight:600; color:#fff; }
    .hp-role-sub { font-size:11.5px; color:rgba(255,255,255,.42); }
    .hp-foot { font-size:11px; color:rgba(255,255,255,.18); position:relative; z-index:1; }

    /* ── Form panel ── */
    .form-panel { flex:1; padding:52px 48px; display:flex; flex-direction:column; justify-content:center; }
    .fp-eyebrow { font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--orange); margin-bottom:9px; }
    .fp-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:1.6rem; color:var(--navy); letter-spacing:-.3px; margin-bottom:5px; }
    .fp-sub { font-size:13px; color:var(--muted); margin-bottom:26px; }

    /* Alert boxes */
    .alert-box { border-radius:10px; padding:13px 15px; font-size:13px; display:flex; align-items:flex-start; gap:9px; margin-bottom:20px; }
    .alert-box i { font-size:17px; flex-shrink:0; margin-top:1px; }
    .alert-err  { background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; }
    .alert-warn { background:#FEF3C7; border:1px solid #FCD34D; color:#78350F; }

    /* Fields */
    .f-label { display:block; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-bottom:6px; }
    .f-wrap { position:relative; }
    .f-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#B0BAC8; font-size:14px; pointer-events:none; }
    .f-input {
      width:100%; height:44px; border:1.5px solid var(--border); border-radius:10px;
      padding:0 14px 0 40px; font-size:13.5px; font-family:'Inter',sans-serif;
      color:#0D1B30; background:#FAFBFD; outline:none;
      transition:border-color .14s, box-shadow .14s;
    }
    .f-input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(0,100,210,.09); }
    .f-mb { margin-bottom:16px; }

    /* Submit */
    .btn-login {
      width:100%; height:46px; background:var(--blue); color:#fff; border:none;
      border-radius:10px; font-size:14.5px; font-weight:700; font-family:'Inter',sans-serif;
      cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
      box-shadow:0 4px 14px rgba(0,100,210,.28); transition:background .14s, transform .1s;
    }
    .btn-login:hover { background:var(--blue-dk); transform:translateY(-1px); }

    /* Divider */
    .divider { display:flex; align-items:center; gap:10px; margin:20px 0 16px; color:var(--muted); font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
    .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--border); }

    /* Alt links */
    .alt-links { display:flex; flex-direction:column; gap:8px; }
    .alt-link {
      display:flex; align-items:center; justify-content:space-between;
      padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
      text-decoration:none; color:#0D1B30; background:none; cursor:pointer;
      font-family:'Inter',sans-serif; text-align:left; width:100%;
      transition:border-color .14s, background .14s, transform .1s;
    }
    .alt-link:hover { border-color:var(--blue); background:#F7FBFF; transform:translateY(-1px); color:#0D1B30; }
    .alt-link-l { display:flex; align-items:center; gap:10px; }
    .alt-link-icon { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .alt-link-icon.guest    { background:#E8F1FC; color:var(--blue); }
    .alt-link-icon.customer { background:#DCFCE7; color:#16A34A; }
    .alt-link-icon.provider { background:#FFE8DA; color:var(--orange); }
    .alt-link-title { font-size:13px; font-weight:600; line-height:1.2; }
    .alt-link-sub   { font-size:11px; color:var(--muted); margin-top:2px; }
    .alt-link-arr   { color:var(--muted); font-size:13px; flex-shrink:0; }

    @media (max-width: 860px) {
      .hero-panel { display:none; }
      .form-panel { padding:40px 32px; }
    }
    @media (max-width: 480px) {
      .form-panel { padding:32px 22px; }
    }
  </style>
</head>
<body>
<div class="card-wrap">

  <!-- Left: Hero panel -->
  <div class="hero-panel">
    <a href="/Traveloka/" class="hp-brand"><span class="t">t</span>raveloka</a>

    <div class="hp-body">
      <h2 class="hp-title">Your journey<br>starts <span>here.</span></h2>
      <p class="hp-sub">One login for everyone. We'll send you to the right place automatically.</p>

      <div class="hp-role">
        <div class="hp-role-dot"><i class="bi bi-person"></i></div>
        <div>
          <div class="hp-role-title">Customers</div>
          <div class="hp-role-sub">Find and book verified rental cars</div>
        </div>
      </div>
      <div class="hp-role">
        <div class="hp-role-dot"><i class="bi bi-building"></i></div>
        <div>
          <div class="hp-role-title">Providers</div>
          <div class="hp-role-sub">Manage your fleet and incoming orders</div>
        </div>
      </div>
      <div class="hp-role">
        <div class="hp-role-dot"><i class="bi bi-shield-lock"></i></div>
        <div>
          <div class="hp-role-title">Admins</div>
          <div class="hp-role-sub">Oversee the entire platform</div>
        </div>
      </div>
    </div>

    <div class="hp-foot">&copy; <?= date('Y') ?> Traveloka Car Rental</div>
  </div>

  <!-- Right: Form panel -->
  <div class="form-panel">
    <div class="fp-eyebrow">Sign in</div>
    <h1 class="fp-title">Welcome back</h1>
    <p class="fp-sub">Use your account credentials. We'll send you to the right place.</p>

    <?php if ($error === 'pending'): ?>
    <div class="alert-box alert-warn">
      <i class="bi bi-hourglass-split"></i>
      <div><strong>Application under review</strong><br>Your provider application is pending admin approval.</div>
    </div>
    <?php elseif ($error === 'rejected'): ?>
    <div class="alert-box alert-err">
      <i class="bi bi-x-circle-fill"></i>
      <div><strong>Application not approved</strong><br>Your provider application was not approved. Contact support.</div>
    </div>
    <?php elseif ($error): ?>
    <div class="alert-box alert-err">
      <i class="bi bi-exclamation-circle-fill"></i>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="f-mb">
        <label class="f-label">Email or username</label>
        <div class="f-wrap">
          <i class="bi bi-envelope"></i>
          <input type="text" name="email" class="f-input" placeholder="your@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required <?= $error ? 'autofocus' : '' ?>>
        </div>
      </div>
      <div class="f-mb">
        <label class="f-label">Password</label>
        <div class="f-wrap">
          <i class="bi bi-lock"></i>
          <input type="password" name="password" class="f-input" placeholder="Enter your password" required>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Sign in
      </button>
    </form>

    <div class="divider">or</div>

    <div class="alt-links">
      <form method="post" style="margin:0">
        <input type="hidden" name="action" value="guest">
        <button type="submit" class="alt-link">
          <span class="alt-link-l">
            <span class="alt-link-icon guest"><i class="bi bi-person-walking"></i></span>
            <span>
              <div class="alt-link-title">Continue as guest</div>
              <div class="alt-link-sub">Browse and book — no account needed</div>
            </span>
          </span>
          <i class="bi bi-arrow-right alt-link-arr"></i>
        </button>
      </form>
      <a href="/Traveloka/auth/customer_login.php" class="alt-link">
        <span class="alt-link-l">
          <span class="alt-link-icon customer"><i class="bi bi-person-plus"></i></span>
          <span>
            <div class="alt-link-title">Create a customer account</div>
            <div class="alt-link-sub">Save bookings &amp; unlock coupon codes</div>
          </span>
        </span>
        <i class="bi bi-arrow-right alt-link-arr"></i>
      </a>
      <a href="/Traveloka/auth/provider_register.php" class="alt-link">
        <span class="alt-link-l">
          <span class="alt-link-icon provider"><i class="bi bi-building-add"></i></span>
          <span>
            <div class="alt-link-title">Apply as a provider</div>
            <div class="alt-link-sub">List your fleet and start earning</div>
          </span>
        </span>
        <i class="bi bi-arrow-right alt-link-arr"></i>
      </a>
    </div>

    <p style="font-size:12.5px;color:var(--muted);text-align:center;margin-top:20px">
      <a href="/Traveloka/" style="color:var(--blue);font-weight:600;text-decoration:none">
        <i class="bi bi-arrow-left"></i> Back to home
      </a>
    </p>
  </div>

</div>
</body>
</html>
