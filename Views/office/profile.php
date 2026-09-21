<?php
/**
 * @var array $user
 * @var string|null $error
 * @var bool $saved
 * @var string $csrf
 */
use App\Core\Request;

$title = 'Profile · DigiPay Ghana';
$active = 'profile';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();

$initials = '';
foreach (explode(' ', (string) $user['name']) as $part) {
    $initials .= mb_substr($part, 0, 1);
}
?>
<div class="page-title">Profile</div>

<div style="text-align:center;margin-bottom:24px">
  <div class="avatar-lg"><?= htmlspecialchars(strtoupper($initials), ENT_QUOTES) ?></div>
  <div style="font-size:16px;font-weight:600"><?= htmlspecialchars((string) $user['name'], ENT_QUOTES) ?></div>
  <div style="font-size:13px;color:var(--muted-2)"><?= htmlspecialchars((string) $user['login_id'], ENT_QUOTES) ?></div>
</div>

<?php if ($saved): ?><div class="alert alert-success content-narrow">Your profile has been updated.</div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error content-narrow"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="content-narrow">
  <div class="form-card">
    <form method="post" action="<?= $base ?>/office/profile">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <label class="label" style="margin-top:0" for="name">Full Name</label>
      <input class="input" type="text" id="name" name="name" value="<?= htmlspecialchars((string) $user['name'], ENT_QUOTES) ?>" required>
      <label class="label" for="email">Email Address</label>
      <input class="input" type="email" id="email" name="email" value="<?= htmlspecialchars((string) $user['email'], ENT_QUOTES) ?>" required>

      <div style="height:1px;background:var(--border);margin:20px 0"></div>
      <div style="font-size:13px;font-weight:600;margin-bottom:4px">Change Password (optional)</div>
      <label class="label" for="current_password">Current Password</label>
      <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password">
      <label class="label" for="new_password">New Password</label>
      <input class="input" type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current password">

      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
