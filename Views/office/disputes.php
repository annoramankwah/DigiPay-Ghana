<?php
/**
 * @var array $disputes
 * @var string $status
 * @var string|null $error
 * @var string $csrf
 */
use App\Core\Money;
use App\Core\Request;

$title = 'Disputes Queue · DigiPay Ghana';
$active = 'disputes';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();

$statuses = ['' => 'All', 'open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'rejected' => 'Rejected'];
$badge = static function (string $s): array {
    return match ($s) {
        'open' => ['bg' => '#FFFBEB', 'fg' => '#D4952A', 'label' => 'Open'],
        'under_review' => ['bg' => '#EFF6FF', 'fg' => '#3B82F6', 'label' => 'Under Review'],
        'rejected' => ['bg' => '#FEF2F2', 'fg' => '#DC3545', 'label' => 'Rejected'],
        default => ['bg' => '#ECFDF5', 'fg' => '#0D9F6E', 'label' => 'Resolved'],
    };
};
?>
<div class="page-title">Disputes Queue</div>
<div class="page-subtitle"><?= count($disputes) ?> disputes</div>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<div class="filter-row">
  <?php foreach ($statuses as $key => $label): ?>
    <a href="<?= $base ?>/office/disputes?status=<?= $key ?>" class="filter-pill<?= $status === $key ? ' active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if ($disputes === []): ?>
  <div class="empty-state">
    <div class="empty-state-title">No disputes</div>
    <div class="empty-state-desc">Student-raised disputes will appear here.</div>
  </div>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($disputes as $d): ?>
      <?php $b = $badge($d['status']); ?>
      <div class="form-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px">
          <div>
            <div class="row-name"><?= htmlspecialchars((string) $d['student_name'], ENT_QUOTES) ?></div>
            <div class="row-sub">TXN-<?= (int) $d['transaction_id'] ?> &middot; <?= htmlspecialchars((string) $d['category'], ENT_QUOTES) ?> &middot; <?= Money::format((float) $d['amount_due']) ?></div>
          </div>
          <div class="badge" style="background:<?= $b['bg'] ?>;color:<?= $b['fg'] ?>"><?= $b['label'] ?></div>
        </div>
        <div style="font-size:13px;color:#4A5568;margin-bottom:10px"><?= htmlspecialchars((string) $d['issue_summary'], ENT_QUOTES) ?></div>
        <?php if ($d['description']): ?>
          <div style="font-size:12px;color:var(--muted-2);margin-bottom:10px"><?= htmlspecialchars((string) $d['description'], ENT_QUOTES) ?></div>
        <?php endif; ?>
        <?php if ($d['resolution_notes']): ?>
          <div style="font-size:12px;color:var(--muted);background:var(--bg);border-radius:8px;padding:8px 10px;margin-bottom:10px">
            <strong>Response:</strong> <?= htmlspecialchars((string) $d['resolution_notes'], ENT_QUOTES) ?>
          </div>
        <?php endif; ?>
        <?php if (in_array($d['status'], ['open', 'under_review'], true)): ?>
          <form method="post" action="<?= $base ?>/office/disputes/<?= (int) $d['dispute_id'] ?>/respond">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <textarea class="textarea" name="notes" rows="2" placeholder="Response notes&hellip;" style="margin-bottom:8px"></textarea>
            <div class="btn-row">
              <?php if ($d['status'] === 'open'): ?>
                <button type="submit" name="status" value="under_review" class="btn-secondary" style="margin-top:0">Mark Under Review</button>
              <?php endif; ?>
              <button type="submit" name="status" value="resolved" class="btn-primary" style="margin-top:0;background:#0D9F6E;color:#fff">Resolve</button>
              <button type="submit" name="status" value="rejected" class="btn-primary" style="margin-top:0;background:#FEF2F2;color:#DC3545;border:1px solid #FECACA">Reject</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
