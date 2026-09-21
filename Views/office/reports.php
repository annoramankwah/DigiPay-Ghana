<?php
/**
 * @var string $from
 * @var string $to
 * @var bool $generated
 * @var array|null $summary
 * @var string $csrf
 */
use App\Core\Money;
use App\Core\Request;

$title = 'Reports · DigiPay Ghana';
$active = 'reports';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();
?>
<div class="page-title">Reports</div>

<div class="desktop-two-col">
  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:14px">Date Range</div>
    <form method="get" action="<?= $base ?>/office/reports">
      <label class="label" style="margin-top:0" for="from">From</label>
      <input class="input" type="date" id="from" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES) ?>">
      <label class="label" for="to">To</label>
      <input class="input" type="date" id="to" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES) ?>">
      <button type="submit" class="btn-primary">Generate Report</button>
    </form>
  </div>

  <?php if (!$generated): ?>
    <div class="empty-state"><div class="empty-state-desc">Select a date range and generate a report.</div></div>
  <?php else: ?>
    <div class="form-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:15px;font-weight:600"><?= date('M j', strtotime($from)) ?> &ndash; <?= date('M j, Y', strtotime($to)) ?></div>
        <a href="<?= $base ?>/office/reports/export?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="filter-pill" style="text-decoration:none">Export CSV</a>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div style="background:var(--bg);border-radius:10px;padding:12px">
          <div style="font-size:11px;color:var(--muted-2);margin-bottom:4px">Total Received</div>
          <div style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--green)"><?= Money::format($summary['total_received']) ?></div>
        </div>
        <div style="background:var(--bg);border-radius:10px;padding:12px">
          <div style="font-size:11px;color:var(--muted-2);margin-bottom:4px">Transactions</div>
          <div style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums"><?= $summary['count'] ?></div>
        </div>
        <div style="background:var(--bg);border-radius:10px;padding:12px">
          <div style="font-size:11px;color:var(--muted-2);margin-bottom:4px">Verified</div>
          <div style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--green)"><?= $summary['verified'] ?></div>
        </div>
        <div style="background:var(--bg);border-radius:10px;padding:12px">
          <div style="font-size:11px;color:var(--muted-2);margin-bottom:4px">Pending</div>
          <div style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--amber)"><?= $summary['pending'] ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
