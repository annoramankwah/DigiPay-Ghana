<?php
use App\Core\Request;
$title = 'Link Expired · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="auth-shell">
  <div class="auth-wrap">
    <div class="card" style="text-align:center">
      <div class="card-title">Link expired or invalid</div>
      <div class="card-subtitle">This password reset link is no longer valid. Please request a new one.</div>
      <a href="<?= $base ?>/password-reset" class="btn-primary" style="display:block">Request New Link</a>
    </div>
  </div>
</div>
</body>
</html>
