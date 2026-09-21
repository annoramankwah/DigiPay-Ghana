<?php
/**
 * @var array $logs
 * @var array $filters
 */
use App\Core\Request;

$title = 'Audit Log · DigiPay Ghana';
$active = 'audit';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
$base = Request::basePath();

$categoryFor = static function (string $action): array {
    return match (true) {
        str_starts_with($action, 'payment') => ['bg' => '#ECFDF5', 'fg' => '#0D9F6E', 'label' => 'payment'],
        str_starts_with($action, 'login') || str_starts_with($action, 'account_locked') || str_starts_with($action, 'unauthorized') => ['bg' => '#FEF2F2', 'fg' => '#DC3545', 'label' => 'security'],
        str_starts_with($action, 'user_') => ['bg' => '#EFF6FF', 'fg' => '#3B82F6', 'label' => 'user'],
        str_starts_with($action, 'fee_structure') || str_starts_with($action, 'reconciliation') => ['bg' => '#FFFBEB', 'fg' => '#D4952A', 'label' => 'config'],
        default => ['bg' => '#F4F6F9', 'fg' => '#8896A7', 'label' => 'system'],
    };
};
?>
<div class="page-title">Audit Log</div>

<div class="desktop-toolbar">
  <form method="get" action="<?= $base ?>/admin/audit-log" class="search-input-wrap">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/></svg>
    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES) ?>" placeholder="Search actions or actor&hellip;" class="input">
  </form>
  <div class="filter-row">
    <?php foreach (['' => 'All', 'success' => 'Success', 'failure' => 'Failure'] as $key => $label): ?>
      <a href="<?= $base ?>/admin/audit-log?status=<?= $key ?>" class="filter-pill<?= ($filters['status'] ?? '') === $key ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($logs === []): ?>
  <div class="empty-state"><div class="empty-state-desc">No matching log entries.</div></div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>When</th>
        <th>Actor</th>
        <th>Action</th>
        <th>Target</th>
        <th>IP</th>
        <th>Category</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <?php $cat = $categoryFor((string) $log['action']); ?>
        <tr>
          <td style="color:var(--muted-2);white-space:nowrap"><?= date('M j, Y g:i A', strtotime((string) $log['timestamp'])) ?></td>
          <td style="font-weight:600"><?= htmlspecialchars((string) ($log['actor_name'] ?? 'System'), ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars(str_replace('_', ' ', (string) $log['action']), ENT_QUOTES) ?></td>
          <td style="color:var(--muted-2)"><?= $log['target_id'] ? htmlspecialchars((string) $log['target_entity'], ENT_QUOTES) . ' ' . htmlspecialchars((string) $log['target_id'], ENT_QUOTES) : '&mdash;' ?></td>
          <td style="color:var(--muted-2);font-family:monospace;font-size:12px"><?= htmlspecialchars((string) ($log['ip_address'] ?? ''), ENT_QUOTES) ?></td>
          <td><span class="badge" style="background:<?= $cat['bg'] ?>;color:<?= $cat['fg'] ?>"><?= $cat['label'] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
