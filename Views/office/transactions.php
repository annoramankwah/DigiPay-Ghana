<?php
/**
 * @var array $transactions
 * @var string $status
 * @var string $search
 */
use App\Core\Money;
use App\Core\Request;

$title = 'Transactions · DigiPay Ghana';
$active = 'transactions';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();

$statuses = ['all' => 'All', 'pending' => 'Pending', 'verified' => 'Verified', 'failed' => 'Failed'];
$badge = static function (string $s): array {
    return match ($s) {
        'verified' => ['bg' => '#ECFDF5', 'fg' => '#0D9F6E', 'icon' => '&#10003;', 'label' => 'Verified'],
        'pending' => ['bg' => '#FFFBEB', 'fg' => '#D4952A', 'icon' => '&#9203;', 'label' => 'Pending'],
        default => ['bg' => '#FEF2F2', 'fg' => '#DC3545', 'icon' => '&#10007;', 'label' => 'Failed'],
    };
};
?>
<div class="page-title">Transactions</div>
<div class="page-subtitle">Real-time incoming payments</div>

<div class="desktop-toolbar">
  <form method="get" action="<?= $base ?>/office/transactions" class="search-input-wrap">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES) ?>">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/></svg>
    <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" placeholder="Search by name, ID, or transaction #&hellip;" class="input">
  </form>
  <div class="filter-row">
    <?php foreach ($statuses as $key => $label): ?>
      <a href="<?= $base ?>/office/transactions?status=<?= $key ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="filter-pill<?= $status === $key ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($transactions === []): ?>
  <div class="empty-state">
    <svg width="48" height="48" fill="none" viewBox="0 0 24 24"><path d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" stroke="currentColor" stroke-width="1.5"/></svg>
    <div class="empty-state-title">No transactions</div>
    <div class="empty-state-desc">Transactions will appear here in real time.</div>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Student</th>
        <th>Category</th>
        <th>Amount</th>
        <th>Method</th>
        <th>Date</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($transactions as $t): ?>
        <?php $b = $badge($t['status']); ?>
        <tr class="clickable" onclick="window.location='<?= $base ?>/office/transactions/<?= (int) $t['transaction_id'] ?>'">
          <td>
            <div style="font-weight:600"><?= htmlspecialchars((string) $t['student_name'], ENT_QUOTES) ?></div>
            <div style="font-size:12px;color:var(--muted-2)"><?= htmlspecialchars((string) $t['student_login_id'], ENT_QUOTES) ?></div>
          </td>
          <td><?= htmlspecialchars((string) $t['category'], ENT_QUOTES) ?></td>
          <td style="font-weight:700;font-variant-numeric:tabular-nums"><?= Money::format((float) $t['amount_due']) ?></td>
          <td><?= ucwords(str_replace('_', ' ', (string) $t['payment_method'])) ?></td>
          <td style="color:var(--muted-2)"><?= date('M j, Y', strtotime((string) $t['timestamp'])) ?></td>
          <td><span class="badge" style="background:<?= $b['bg'] ?>;color:<?= $b['fg'] ?>"><?= $b['icon'] ?> <?= $b['label'] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
