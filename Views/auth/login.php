<?php
use App\Core\Request;
$title = 'Sign in · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="auth-shell">
  <div class="auth-wrap">
    <div class="auth-logo">
      <div class="logo-badge">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="#0A1628" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div class="auth-title">DigiPay Ghana</div>
      <div class="auth-subtitle">Fee Collection Portal</div>
    </div>
    <div class="card">
      <div class="card-title">Sign in</div>
      <div class="card-subtitle">Enter your credentials to continue</div>

      <?php if (!empty($expired)): ?>
        <div class="alert alert-warning">Your session expired. Please sign in again.</div>
      <?php endif; ?>
      <?php if (!empty($notice)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($notice, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= $base ?>/login">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label class="label" for="login_id">Student ID / Staff ID</label>
        <input class="input" type="text" id="login_id" name="login_id" placeholder="e.g. STU/2024/0847" required autofocus>
        <label class="label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
        <div class="auth-links"><a href="<?= $base ?>/password-reset">Forgot password?</a></div>
        <button class="btn-primary" type="submit">Sign in</button>
      </form>
    </div>
    <div class="auth-footer">New student? <a href="<?= $base ?>/signup">Create an account</a></div>
    <div class="auth-footer">© 2026 DigiPay Ghana · University of Ghana</div>
  </div>
</div>
</body>
</html>
