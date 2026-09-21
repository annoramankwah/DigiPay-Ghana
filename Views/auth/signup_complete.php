<?php
use App\Core\Request;
$title = 'Create Account · DigiPay Ghana';
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
      <div class="card-title">You're verified</div>
      <div class="card-subtitle">Add an email and set a password to finish</div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= $base ?>/signup/complete">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label class="label" for="email">Email Address</label>
        <input class="input" type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
        <label class="label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" required>
        <label class="label" for="password_confirmation">Confirm Password</label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
        <button class="btn-primary" type="submit">Create Account</button>
      </form>
    </div>
    <div class="auth-footer">© 2026 DigiPay Ghana · University of Ghana</div>
  </div>
</div>
</body>
</html>
