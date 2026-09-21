<?php
use App\Core\Request;
$title = 'Set New Password · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="auth-shell">
  <div class="auth-wrap">
    <div class="auth-logo">
      <div class="logo-badge">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="#0A1628" stroke-width="2"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#0A1628" stroke-width="2"/></svg>
      </div>
      <div class="auth-title">Set New Password</div>
      <div class="auth-subtitle">Choose a new password for your account</div>
    </div>
    <div class="card">
      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= $base ?>/password-reset/<?= urlencode($token) ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label class="label" for="password">New Password</label>
        <input class="input" type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required autofocus>
        <label class="label" for="password_confirmation">Confirm Password</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" minlength="8" required>
        <button class="btn-primary" type="submit">Update Password</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
