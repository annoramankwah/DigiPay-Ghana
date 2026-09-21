<?php
/**
 * @var array $groups
 * @var string|null $error
 * @var bool $saved
 * @var string $csrf
 */
use App\Core\Money;
use App\Core\Request;

$title = 'Fee Structures · DigiPay Ghana';
$active = 'fees';
require dirname(__DIR__) . '/partials/office_chrome_open.php';
$base = Request::basePath();
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <div class="page-title" style="margin-bottom:0">Fee Structures</div>
</div>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($saved): ?><div class="alert alert-success">Saved.</div><?php endif; ?>

<div class="desktop-two-col">
  <div>
    <?php foreach ($groups as $group): ?>
      <?php $total = array_sum(array_column($group['items'], 'amount')); ?>
      <div class="form-card" style="margin-bottom:10px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px">
          <div>
            <div style="font-size:15px;font-weight:600"><?= htmlspecialchars((string) $group['program'], ENT_QUOTES) ?> &ndash; Level <?= htmlspecialchars((string) $group['level'], ENT_QUOTES) ?></div>
            <div style="font-size:12px;color:var(--muted-2);margin-top:2px"><?= htmlspecialchars((string) $group['academic_term'], ENT_QUOTES) ?> &middot; Effective <?= date('M j, Y', strtotime((string) $group['effective_from'])) ?></div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:13px">
          <?php foreach ($group['items'] as $item): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--bg)">
              <span><?= htmlspecialchars((string) $item['category'], ENT_QUOTES) ?> <span style="color:var(--muted-2);font-size:11px">(due <?= date('M j', strtotime((string) $item['due_date'])) ?>)</span></span>
              <span style="display:flex;align-items:center;gap:10px">
                <span style="font-weight:700;font-variant-numeric:tabular-nums"><?= Money::format((float) $item['amount']) ?></span>
                <button type="button" class="filter-pill" style="padding:4px 10px;font-size:11px" onclick="toggleRevise(<?= (int) $item['fee_id'] ?>)">Edit</button>
              </span>
            </div>
            <form method="post" action="<?= $base ?>/office/fee-structures/<?= (int) $item['fee_id'] ?>/revise" id="revise-<?= (int) $item['fee_id'] ?>" style="display:none;background:var(--bg);border-radius:8px;padding:10px;margin:-4px 0 8px">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <div style="display:flex;gap:8px;align-items:flex-end">
                <div style="flex:1">
                  <label class="label" style="margin-top:0;font-size:11px">New Amount (GHS)</label>
                  <input class="input" type="number" step="0.01" min="0.01" name="amount" value="<?= $item['amount'] ?>" required>
                </div>
                <div style="flex:1">
                  <label class="label" style="margin-top:0;font-size:11px">New Due Date</label>
                  <input class="input" type="date" name="due_date" value="<?= htmlspecialchars((string) $item['due_date'], ENT_QUOTES) ?>" required>
                </div>
                <button type="submit" class="btn-primary" style="margin-top:0;width:auto;padding:12px 16px">Save</button>
              </div>
              <div style="font-size:11px;color:var(--muted-2);margin-top:8px">Saving closes the current fee item as of yesterday and starts a new one today — history is preserved for past transactions.</div>
            </form>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:2px solid var(--border);font-weight:700">
            <span>Total</span><span style="font-variant-numeric:tabular-nums;color:var(--amber-500)"><?= Money::format($total) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="form-card">
    <div style="font-size:15px;font-weight:600;margin-bottom:14px">Add Fee Item</div>
    <form method="post" action="<?= $base ?>/office/fee-structures">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <label class="label" style="margin-top:0" for="program">Program</label>
      <input class="input" type="text" id="program" name="program" placeholder="e.g. BSc Computer Science" required>
      <label class="label" for="level">Level</label>
      <input class="input" type="text" id="level" name="level" placeholder="e.g. 300" required>
      <label class="label" for="academic_term">Academic Term</label>
      <input class="input" type="text" id="academic_term" name="academic_term" placeholder="e.g. 2026/2027 Semester 1" required>
      <label class="label" for="category">Fee Category</label>
      <input class="input" type="text" id="category" name="category" placeholder="e.g. Hostel Fee" required>
      <label class="label" for="amount">Amount (GHS)</label>
      <input class="input" type="number" step="0.01" min="0.01" id="amount" name="amount" required>
      <label class="label" for="due_date">Due Date</label>
      <input class="input" type="date" id="due_date" name="due_date" required>
      <label class="label" for="effective_from">Effective From</label>
      <input class="input" type="date" id="effective_from" name="effective_from" value="<?= date('Y-m-d') ?>" required>
      <button type="submit" class="btn-primary">Add Fee Item</button>
    </form>
  </div>
</div>

<script>
  function toggleRevise(id) {
    var el = document.getElementById('revise-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
</script>

<?php require __DIR__ . '/../partials/office_chrome_close.php'; ?>
