<?php
use App\Core\Request;
$title = 'Page Not Found · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="error-shell">
  <div class="error-code">404</div>
  <div class="error-title">Page not found</div>
  <div class="error-desc">The page you're looking for doesn't exist or has been moved.</div>
  <a href="<?= $base ?>/login" class="btn-primary" style="display:inline-block;width:auto;padding:14px 32px">Go to Sign In</a>
</div>
</body>
</html>
