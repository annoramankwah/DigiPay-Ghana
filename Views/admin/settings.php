<?php
/**
 * @var array $categories
 * @var array $terms
 */
$title = 'System Settings · DigiPay Ghana';
$active = 'settings';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
?>
<div class="page-title">System Settings</div>

<div class="card-grid">
  <div class="form-card">
    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Fee Categories</div>
    <div style="font-size:12px;color:var(--muted-2);margin-bottom:12px">Categories are set by the Accounts Office when a fee item is created — shown here for reference.</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($categories as $c): ?>
        <span style="padding:6px 14px;background:var(--bg);border-radius:20px;font-size:13px;font-weight:500"><?= htmlspecialchars((string) $c, ENT_QUOTES) ?></span>
      <?php endforeach; ?>
      <?php if ($categories === []): ?><span style="font-size:13px;color:var(--muted-2)">No fee categories yet.</span><?php endif; ?>
    </div>
  </div>

  <div class="form-card">
    <div style="font-size:15px;font-weight:600;margin-bottom:12px">Academic Terms</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($terms as $t): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--bg);border-radius:8px">
          <div>
            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars((string) $t['academic_term'], ENT_QUOTES) ?></div>
            <div style="font-size:11px;color:var(--muted-2)"><?= date('M j, Y', strtotime((string) $t['starts'])) ?> &ndash; <?= $t['ends'] === '9999-12-31' ? 'ongoing' : date('M j, Y', strtotime((string) $t['ends'])) ?></div>
          </div>
          <div style="padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;background:<?= $t['is_active'] ? '#ECFDF5' : 'var(--bg)' ?>;color:<?= $t['is_active'] ? 'var(--green)' : 'var(--muted-2)' ?>"><?= $t['is_active'] ? 'Active' : 'Ended' ?></div>
        </div>
      <?php endforeach; ?>
      <?php if ($terms === []): ?><span style="font-size:13px;color:var(--muted-2)">No academic terms yet.</span><?php endif; ?>
    </div>
  </div>

  <div class="form-card">
    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Notification Templates</div>
    <div style="font-size:12px;color:var(--muted-2);margin-bottom:12px">These are generated in code (not editable here) — shown for reference.</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div style="padding:10px 12px;background:var(--bg);border-radius:8px;font-size:13px"><span style="font-weight:600">Payment Pending:</span> <span style="color:var(--muted)">"Payment of {amount} received and pending verification by the accounts office."</span></div>
      <div style="padding:10px 12px;background:var(--bg);border-radius:8px;font-size:13px"><span style="font-weight:600">Payment Verified:</span> <span style="color:var(--muted)">"Payment of {amount} verified. Receipt {number} issued."</span></div>
      <div style="padding:10px 12px;background:var(--bg);border-radius:8px;font-size:13px"><span style="font-weight:600">Payment Rejected:</span> <span style="color:var(--muted)">"Your payment of {amount} was rejected by the accounts office. Reason: {remarks}"</span></div>
      <div style="padding:10px 12px;background:var(--bg);border-radius:8px;font-size:13px"><span style="font-weight:600">Dispute Update:</span> <span style="color:var(--muted)">"Update on your dispute for {transaction}: {status}. {notes}"</span></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
