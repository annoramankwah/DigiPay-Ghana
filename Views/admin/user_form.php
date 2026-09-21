<?php
/**
 * @var array|null $user  null when creating
 * @var string|null $error
 * @var string $csrf
 * @var bool $isSuperAdmin
 */
use App\Core\Request;
use App\Core\Session;

$isEdit = $user !== null;
$title = ($isEdit ? 'Edit User' : 'Add User') . ' · DigiPay Ghana';
$active = 'users';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
$base = Request::basePath();
$isSelf = $isEdit && (int) $user['user_id'] === (int) Session::get('user_id');
?>
<div class="back-row">
  <a href="<?= $base ?>/admin/users" class="back-btn" aria-label="Back">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  </a>
  <div class="page-title" style="margin-bottom:0"><?= $isEdit ? 'Edit User' : 'Add User' ?></div>
</div>

<?php if (!empty($error)): ?><div class="alert alert-error content-narrow"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="<?= $isEdit ? 'desktop-two-col' : 'content-narrow' ?>">
  <div class="form-card">
    <form method="post" action="<?= $isEdit ? $base . '/admin/users/' . (int) $user['user_id'] : $base . '/admin/users' ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

      <?php if (!$isEdit): ?>
        <label class="label" style="margin-top:0" for="login_id">Student ID / Staff ID</label>
        <input class="input" type="text" id="login_id" name="login_id" placeholder="e.g. STU/2024/0900" required>
      <?php endif; ?>

      <label class="label" style="<?= $isEdit ? 'margin-top:0' : '' ?>" for="name">Full Name</label>
      <input class="input" type="text" id="name" name="name" value="<?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES) ?>" required>

      <label class="label" for="email">Email Address</label>
      <input class="input" type="email" id="email" name="email" value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES) ?>" required>

      <label class="label" for="role">Role</label>
      <select class="select" id="role" name="role" onchange="toggleStudentFields()" <?= $isSelf ? 'disabled' : '' ?> required>
        <?php
        $roleOptions = ['student' => 'Student', 'account_office' => 'Accounts Office', 'admin' => 'Administrator'];
        if (!empty($isSuperAdmin)) {
            $roleOptions['super_admin'] = 'Super Administrator';
        }
        ?>
        <?php foreach ($roleOptions as $key => $label): ?>
          <option value="<?= $key ?>" <?= ($user['role'] ?? 'student') === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($isSelf): ?><input type="hidden" name="role" value="admin"><div style="font-size:11px;color:var(--muted-2);margin-top:4px">You cannot change your own role.</div><?php endif; ?>

      <div id="student-fields" style="display:none">
        <label class="label" for="program">Program</label>
        <input class="input" type="text" id="program" name="program" value="<?= htmlspecialchars((string) ($user['program'] ?? ''), ENT_QUOTES) ?>" placeholder="e.g. BSc Computer Science">
        <label class="label" for="level">Level</label>
        <input class="input" type="text" id="level" name="level" value="<?= htmlspecialchars((string) ($user['level'] ?? ''), ENT_QUOTES) ?>" placeholder="e.g. 300">
      </div>

      <?php if (!$isEdit): ?>
        <label class="label" for="password">Initial Password</label>
        <input class="input" type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" required>
      <?php endif; ?>

      <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
    </form>
  </div>

  <?php if ($isEdit && !$isSelf): ?>
    <div class="form-card">
      <div style="font-size:14px;font-weight:600;margin-bottom:12px">Account Status</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php if ($user['status'] === 'active'): ?>
          <form method="post" action="<?= $base ?>/admin/users/<?= (int) $user['user_id'] ?>/status" data-confirm="They will not be able to sign in until reactivated." data-confirm-title="Deactivate this account?">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="status" value="inactive">
            <button type="submit" class="btn-secondary" style="width:100%">Deactivate</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= $base ?>/admin/users/<?= (int) $user['user_id'] ?>/status">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="status" value="active">
            <button type="submit" class="btn-primary" style="width:100%;margin-top:0">Reactivate</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= $base ?>/admin/users/<?= (int) $user['user_id'] ?>/delete" data-confirm="This can be undone later from the Deleted filter." data-confirm-title="Delete this account?" data-confirm-danger>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
          <button type="submit" class="btn-secondary" style="width:100%;color:var(--red);border-color:#FEF2F2">Delete</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  function toggleStudentFields() {
    var role = document.getElementById('role').value;
    document.getElementById('student-fields').style.display = role === 'student' ? 'block' : 'none';
  }
  toggleStudentFields();
</script>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
