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
      <div class="card-title">Create your account</div>
      <div class="card-subtitle">Confirm your details exactly as held by the Accounts Office</div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= $base ?>/signup">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label class="label" for="student_number">Student ID</label>
        <input class="input" type="text" id="student_number" name="student_number" placeholder="e.g. STU/2024/0847" required autofocus>
        <label class="label" for="full_name">Full Name</label>
        <input class="input" type="text" id="full_name" name="full_name" placeholder="As it appears on your admission letter" required>
        <label class="label" for="date_of_birth">Date of Birth</label>
        <input class="input" type="date" id="date_of_birth" name="date_of_birth" required>
        <button class="btn-primary" type="submit">Continue</button>
      </form>
    </div>
    <div class="auth-footer">Already have an account? <a href="<?= $base ?>/login">Sign in</a></div>
  </div>
</div>
</body>
</html>
