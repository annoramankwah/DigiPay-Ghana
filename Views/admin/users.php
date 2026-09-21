<?php
/**
 * @var array $users
 * @var array $filters
 * @var string|null $error
 * @var bool $saved
 * @var string $csrf
 */
use App\Core\Request;

$title = 'Users · DigiPay Ghana';
$active = 'users';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
$base = Request::basePath();

$roleBadge = static function (string $role): array {
    return match ($role) {
        'admin' => ['bg' => '#FEF2F2', 'fg' => '#DC3545', 'label' => 'Admin'],
        'account_office' => ['bg' => '#FFFBEB', 'fg' => '#D4952A', 'label' => 'Accounts'],
        default => ['bg' => '#EFF6FF', 'fg' => '#3B82F6', 'label' => 'Student'],
    };
};
$avatarColors = ['#1B3A5C', '#7C3AED', '#D4952A', '#DC3545', '#0D9F6E'];
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <div class="page-title" style="margin-bottom:0">Users</div>
  <a href="<?= $base ?>/admin/users/new" class="filter-pill active" style="text-decoration:none">+ Add User</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="desktop-toolbar">
  <form method="get" action="<?= $base ?>/admin/users" class="search-input-wrap">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/></svg>
    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES) ?>" placeholder="Search users&hellip;" class="input">
  </form>
  <div class="filter-row">
    <?php foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted'] as $key => $label): ?>
      <a href="<?= $base ?>/admin/users?status=<?= $key ?>" class="filter-pill<?= ($filters['status'] ?? '') === $key ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($users === []): ?>
  <div class="empty-state"><div class="empty-state-desc">No users match this filter.</div></div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Login ID</th>
        <th>Email</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <?php
        $rb = $roleBadge($u['role']);
        $initials = '';
        foreach (explode(' ', (string) $u['name']) as $part) { $initials .= mb_substr($part, 0, 1); }
        $avatarBg = $avatarColors[crc32($u['login_id']) % count($avatarColors)];
        $isDeleted = !empty($u['deleted_at']);
        $statusDot = $isDeleted ? '#8896A7' : ($u['status'] === 'active' ? '#0D9F6E' : '#D1D9E4');
        $statusLabel = $isDeleted ? 'Deleted' : ucfirst((string) $u['status']);
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $avatarBg ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0"><?= htmlspecialchars(strtoupper($initials), ENT_QUOTES) ?></div>
              <span style="font-weight:600"><?= htmlspecialchars((string) $u['name'], ENT_QUOTES) ?></span>
            </div>
          </td>
          <td><span class="badge" style="background:<?= $rb['bg'] ?>;color:<?= $rb['fg'] ?>"><?= $rb['label'] ?></span></td>
          <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars((string) $u['login_id'], ENT_QUOTES) ?></td>
          <td style="color:var(--muted-2)"><?= htmlspecialchars((string) $u['email'], ENT_QUOTES) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= $statusDot ?>"></div>
              <span style="color:var(--muted-2)"><?= $statusLabel ?></span>
            </div>
          </td>
          <td style="text-align:right">
            <?php if (!$isDeleted): ?>
              <a href="<?= $base ?>/admin/users/<?= (int) $u['user_id'] ?>/edit" class="filter-pill" style="text-decoration:none;padding:5px 10px;font-size:12px">Edit</a>
            <?php else: ?>
              <form method="post" action="<?= $base ?>/admin/users/<?= (int) $u['user_id'] ?>/restore" style="display:inline" data-confirm="This will reactivate the account." data-confirm-title="Restore this user?">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <button type="submit" class="filter-pill" style="padding:5px 10px;font-size:12px">Restore</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
