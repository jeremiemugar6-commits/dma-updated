<?php
require_once __DIR__ . '/includes/auth.php';

$session = getSession();
if ($session) {
    header('Location: ' . ($session['role'] === 'ADMIN' ? '/dms-php/admin/storage.php' : '/dms-php/profile.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, fullname, email, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            createSession($user['id'], $user['role']);
            setcookie('dms_name', $user['fullname'], time() + SESSION_EXPIRY, '/', '', false, false);
            logAudit($user['id'], 'ACCOUNT_LOGIN', 'User logged in');
            header('Location: ' . ($user['role'] === 'ADMIN' ? '/dms-php/admin/storage.php' : '/dms-php/profile.php'));
            exit;
        } else {
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
  <title>Sign In — DMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/dms-php/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <img src="/dms-php/assets/img/mns-logo.jpg" alt="MNS Logo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #1e553e;box-shadow:0 4px 12px rgba(30,85,62,.2);">
      <h1>Maharlika National Service</h1>
      <p>Document Management System</p>
    </div>

    <?php if ($error): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:.875rem;display:flex;align-items:center;gap:8px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/dms-php/login.php">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="admin@mns.local"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div style="position:relative">
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Enter your password" required>
          <button type="button" onclick="togglePwd()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;display:flex;align-items:center">
            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-full" style="padding:10px;margin-top:8px">
        Sign In
      </button>
    </form>
  </div>
</div>
<script>
function togglePwd() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    inp.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
</script>
</body>
</html>
