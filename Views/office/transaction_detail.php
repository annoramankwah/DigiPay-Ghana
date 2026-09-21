<?php
/**
 * @var array $transaction
 * @var array|null $payerDetails
 * @var array $remarks
 * @var string|null $error
 * @var bool $saved
 * @var string $csrf
 */
use App\Core\Money;
use App\Core\Request;

$title = 'Transaction Detail · DigiPay Ghana';
$active = 'transactions';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();

$badge = match ($transaction['status']) {
    'verified' => ['bg' => '#ECFDF5', 'fg' => '#0D9F6E', 'label' => '&#10003; Verified'],
    'pending' => ['bg' => '#FFFBEB', 'fg' => '#D4952A', 'label' => '&#9203; Pending verification'],
    default => ['bg' => '#FEF2F2', 'fg' => '#DC3545', 'label' => '&#10007; Failed'],
};
?>
<div class="back-row">
  <a href="<?= $base ?>/office/transactions" class="back-btn" aria-label="Back">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
  </a>
  <div class="page-title" style="margin-bottom:0">Transaction Detail</div>
</div>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="desktop-two-col">
  <div>
    <div class="confirm-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="badge" style="background:<?= $badge['bg'] ?>;color:<?= $badge['fg'] ?>"><?= $badge['label'] ?></div>
        <div style="font-size:11px;color:var(--muted-2);font-family:monospace">TXN-<?= (int) $transaction['transaction_id'] ?></div>
      </div>
      <div style="text-align:center;margin-bottom:16px">
        <div style="font-size:32px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--amber-500)"><?= Money::format((float) $transaction['amount_due']) ?></div>
        <div style="font-size:13px;color:var(--muted-2);margin-top:4px"><?= htmlspecialchars((string) $transaction['category'], ENT_QUOTES) ?></div>
      </div>
      <div style="height:1px;background:var(--border);margin-bottom:16px"></div>
      <div class="confirm-row"><span class="label-col">Student</span><span class="value-col"><?= htmlspecialchars((string) $transaction['student_name'], ENT_QUOTES) ?></span></div>
      <div class="confirm-row"><span class="label-col">Student ID</span><span class="value-col"><?= htmlspecialchars((string) $transaction['student_login_id'], ENT_QUOTES) ?></span></div>
      <div class="confirm-row"><span class="label-col">Date</span><span class="value-col"><?= date('M j, Y \a\t g:i A', strtotime((string) $transaction['timestamp'])) ?></span></div>
      <div class="confirm-row"><span class="label-col">Method</span><span class="value-col"><?= ucwords(str_replace('_', ' ', (string) $transaction['payment_method'])) ?></span></div>
      <?php if (!empty($payerDetails['momo_number'])): ?>
        <div class="confirm-row"><span class="label-col">Paid From</span><span class="value-col"><?= htmlspecialchars((string) $payerDetails['momo_number'], ENT_QUOTES) ?></span></div>
      <?php elseif (!empty($payerDetails['bank_name'])): ?>
        <div class="confirm-row"><span class="label-col">Bank</span><span class="value-col"><?= htmlspecialchars((string) $payerDetails['bank_name'], ENT_QUOTES) ?></span></div>
        <div class="confirm-row"><span class="label-col">Account No.</span><span class="value-col"><?= htmlspecialchars((string) $payerDetails['account_number'], ENT_QUOTES) ?></span></div>
      <?php endif; ?>
      <div class="confirm-row"><span class="label-col">Amount Paid</span><span class="value-col"><?= Money::format((float) $transaction['amount_paid']) ?></span></div>
    </div>

    <?php if ($remarks !== []): ?>
      <div class="section-label" style="margin-top:20px">Reconciliation Remarks</div>
      <div class="form-card">
        <?php foreach ($remarks as $r): ?>
          <?php $payload = json_decode((string) $r['after_value'], true); ?>
          <div style="font-size:13px;padding:8px 0;border-bottom:1px solid var(--bg)">
            <div style="color:#4A5568"><?= htmlspecialchars((string) ($payload['remarks'] ?? ''), ENT_QUOTES) ?></div>
            <div style="font-size:11px;color:var(--muted-2);margin-top:2px"><?= htmlspecialchars((string) $r['actor_name'], ENT_QUOTES) ?> &middot; <?= date('M j, Y g:i A', strtotime((string) $r['timestamp'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($transaction['status'] === 'pending'): ?>
      <div class="form-card">
        <div style="font-size:14px;font-weight:600;margin-bottom:12px">Review Payment</div>
        <label class="label" style="margin-top:0" for="remarks">Reconciliation Remarks (optional)</label>
        <form method="post" id="review-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
          <textarea class="textarea" id="remarks" name="remarks" rows="3" placeholder="Add notes for this transaction&hellip;" style="margin-bottom:12px"></textarea>
          <div style="display:flex;flex-direction:column;gap:10px">
            <button type="submit" formaction="<?= $base ?>/office/transactions/<?= (int) $transaction['transaction_id'] ?>/verify" data-confirm="This issues a receipt and notifies the student." data-confirm-title="Verify this payment?" class="btn-primary" style="margin-top:0;background:#0D9F6E;color:#fff;display:flex;align-items:center;justify-content:center;gap:6px">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
              Verify
            </button>
            <button type="submit" formaction="<?= $base ?>/office/transactions/<?= (int) $transaction['transaction_id'] ?>/reject" data-confirm="The student will be notified their payment was rejected." data-confirm-title="Reject this payment?" data-confirm-danger class="btn-primary" style="margin-top:0;background:#FEF2F2;color:#DC3545;border:1px solid #FECACA;display:flex;align-items:center;justify-content:center;gap:6px">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
              Reject
            </button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="form-card">
        <div style="font-size:14px;font-weight:600;margin-bottom:12px">Add a Remark</div>
        <form method="post" action="<?= $base ?>/office/transactions/<?= (int) $transaction['transaction_id'] ?>/remark">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
          <textarea class="textarea" id="remarks2" name="remarks" rows="3" placeholder="Add notes for this transaction&hellip;" style="margin-bottom:12px"></textarea>
          <button type="submit" class="btn-secondary">Save Remark</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
