<?php
use App\Core\Request;
$title = 'Reset Password · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="auth-shell">
  <div class="auth-wrap">
    <div class="auth-logo">
      <div class="logo-badge">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="#0A1628" stroke-width="2"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#0A1628" stroke-width="2"/></svg>
      </div>
      <div class="auth-title">Reset Password</div>
      <div class="auth-subtitle">We'll send a reset link to your email</div>
    </div>
    <div class="card">
      <?php if (!empty($sent)): ?>
        <div class="alert alert-success">If that ID and email match an account, a reset link has been sent.</div>
      <?php endif; ?>
      <form method="post" action="<?= $base ?>/password-reset">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label class="label" for="login_id">Student ID / Staff ID</label>
        <input class="input" type="text" id="login_id" name="login_id" placeholder="e.g. STU/2024/0847" required autofocus>
        <label class="label" for="email">Registered Email</label>
        <input class="input" type="email" id="email" name="email" placeholder="you@example.com" required>
        <button class="btn-primary" type="submit">Send Reset Link</button>
      </form>
      <div style="text-align:center;margin-top:16px">
        <a href="<?= $base ?>/login" style="font-size:14px;font-weight:500">← Back to Sign In</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
