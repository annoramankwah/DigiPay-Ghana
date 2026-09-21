<?php
/**
 * @var array $history
 * @var string $csrf
 */
use App\Core\Request;

$title = 'Backup & Restore · DigiPay Ghana';
$active = 'backup';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
$base = Request::basePath();
$last = $history[0] ?? null;
?>
<div class="page-title">Backup &amp; Restore</div>

<div class="desktop-two-col">
  <div class="form-card">
    <?php if ($last): ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="width:48px;height:48px;border-radius:12px;background:#ECFDF5;display:flex;align-items:center;justify-content:center">
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" stroke="#0D9F6E" stroke-width="2.5" stroke-linecap="round"/></svg>
        </div>
        <div>
          <div style="font-size:15px;font-weight:600">Last Backup</div>
          <div style="font-size:13px;color:var(--muted-2)"><?= date('M j, Y \a\t g:i A', strtotime($last['created_at'])) ?></div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between"><span style="color:var(--muted-2)">Size</span><span style="font-weight:600"><?= number_format($last['size_bytes'] / 1024, 1) ?> KB</span></div>
        <div style="display:flex;justify-content:space-between"><span style="color:var(--muted-2)">File</span><span style="font-weight:600;font-family:monospace;font-size:12px"><?= htmlspecialchars($last['filename'], ENT_QUOTES) ?></span></div>
      </div>
    <?php else: ?>
      <div style="font-size:13px;color:var(--muted-2);margin-bottom:16px">No backups yet.</div>
    <?php endif; ?>
    <form method="post" action="<?= $base ?>/admin/backup" data-confirm="This creates a full snapshot of the platform database." data-confirm-title="Create a backup now?">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <button type="submit" class="btn-primary">Backup Now</button>
    </form>
    <button type="button" class="btn-secondary" style="margin-top:10px;cursor:not-allowed;opacity:.6" disabled title="Restore is intentionally disabled in this prototype — overwriting live data is a destructive action disproportionate to this feature's priority.">Restore from Backup</button>
  </div>

  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Backup History</div>
    <?php if ($history === []): ?>
      <div style="font-size:13px;color:var(--muted-2)">No backups yet.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <?php foreach ($history as $b): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bg)">
            <div><div style="font-weight:600"><?= date('M j, Y \a\t g:i A', strtotime($b['created_at'])) ?></div><div style="font-size:11px;color:var(--muted-2)"><?= number_format($b['size_bytes'] / 1024, 1) ?> KB</div></div>
            <div style="color:var(--green);font-weight:600;font-size:12px">&#10003; Success</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
