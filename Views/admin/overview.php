<?php
/**
 * @var array $overview
 * @var array $recentActivity
 */
use App\Core\Money;

$title = 'Dashboard · DigiPay Ghana';
$active = 'overview';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
?>
<div class="page-title">Dashboard</div>

<div class="stat-grid">
  <div class="form-card">
    <div style="font-size:11px;color:var(--muted-2);font-weight:500;margin-bottom:6px">Total Collections</div>
    <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--green)"><?= Money::format($overview['totalCollections']) ?></div>
    <div style="font-size:11px;color:var(--muted-2);margin-top:4px">All-time verified</div>
  </div>
  <div class="form-card">
    <div style="font-size:11px;color:var(--muted-2);font-weight:500;margin-bottom:6px">Active Users</div>
    <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums"><?= $overview['activeUsers'] ?></div>
    <div style="font-size:11px;color:var(--muted-2);margin-top:4px"><?= $overview['userCounts']['student'] ?> students</div>
  </div>
  <div class="form-card">
    <div style="font-size:11px;color:var(--muted-2);font-weight:500;margin-bottom:6px">Accounts Office</div>
    <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums"><?= $overview['userCounts']['account_office'] ?></div>
    <div style="font-size:11px;color:var(--muted-2);margin-top:4px">Active staff</div>
  </div>
  <div class="form-card">
    <div style="font-size:11px;color:var(--muted-2);font-weight:500;margin-bottom:6px">Pending Txns</div>
    <div style="font-size:22px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--amber)"><?= $overview['pendingCount'] ?></div>
    <div style="font-size:11px;color:var(--amber);margin-top:4px">Needs attention</div>
  </div>
</div>

<div class="desktop-two-col">
  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:14px">Monthly Collections Trend</div>
    <div style="display:flex;align-items:flex-end;gap:10px;height:180px;padding-bottom:24px">
      <?php foreach ($overview['chart'] as $point): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%">
          <div style="flex:1;display:flex;align-items:flex-end;width:100%">
            <div style="width:100%;border-radius:6px 6px 0 0;background:<?= $point['pct'] >= 80 ? 'var(--green)' : ($point['pct'] >= 50 ? 'var(--amber-500)' : 'var(--navy-700)') ?>;height:<?= max(4, $point['pct']) ?>%" title="<?= Money::format($point['total']) ?>"></div>
          </div>
          <div style="font-size:11px;color:var(--muted-2);font-weight:500"><?= $point['month'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Recent Activity</div>
    <?php if ($recentActivity === []): ?>
      <div style="font-size:13px;color:var(--muted-2)">No activity yet.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($recentActivity as $log): ?>
          <?php $dotColor = $log['status'] === 'failure' ? 'var(--red)' : 'var(--green)'; ?>
          <div style="display:flex;gap:10px;font-size:13px">
            <div style="width:6px;height:6px;border-radius:50%;background:<?= $dotColor ?>;margin-top:6px;flex-shrink:0"></div>
            <div>
              <span style="font-weight:600"><?= htmlspecialchars((string) ($log['actor_name'] ?? 'System'), ENT_QUOTES) ?></span>
              <span style="color:var(--muted-2)"> <?= htmlspecialchars(str_replace('_', ' ', (string) $log['action']), ENT_QUOTES) ?><?= $log['target_id'] ? ' · ' . htmlspecialchars((string) $log['target_id'], ENT_QUOTES) : '' ?></span>
              <div style="font-size:11px;color:var(--muted-2);margin-top:2px"><?= date('M j, g:i A', strtotime((string) $log['timestamp'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
