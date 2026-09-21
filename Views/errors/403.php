<?php
use App\Core\Request;
$title = 'Access Denied · DigiPay Ghana';
require dirname(__DIR__) . '/partials/head.php';
$base = Request::basePath();
?>
<div class="error-shell">
  <div class="error-code">403</div>
  <div class="error-title">Access denied</div>
  <div class="error-desc">You don't have permission to view this page with your current role.</div>
  <a href="<?= $base ?>/login" class="btn-primary" style="display:inline-block;width:auto;padding:14px 32px">Back to Sign In</a>
</div>
</body>
</html>
