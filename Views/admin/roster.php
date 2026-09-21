<?php
/**
 * @var array $roster
 * @var array $filters
 * @var string|null $error
 * @var string|null $saved
 * @var string $csrf
 */
use App\Core\Request;

$title = 'Student Roster · DigiPay Ghana';
$active = 'roster';
require dirname(__DIR__) . '/partials/admin_chrome_open.php';
$base = Request::basePath();
?>
<div class="page-title">Student Roster</div>
<div class="card-subtitle" style="margin-bottom:16px">Pre-load expected students here so they can create their own accounts at <code>/signup</code> by matching these details. Accounts are only created once a student successfully matches a roster entry — nothing here grants access by itself.</div>

<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="alert alert-success"><?= htmlspecialchars($saved, ENT_QUOTES) ?></div><?php endif; ?>

<div class="desktop-two-col">
  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Add One Student</div>
    <form method="post" action="<?= $base ?>/admin/roster">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <label class="label" style="margin-top:0" for="student_number">Student ID</label>
      <input class="input" type="text" id="student_number" name="student_number" placeholder="e.g. STU/2024/0900" required>
      <label class="label" for="full_name">Full Name</label>
      <input class="input" type="text" id="full_name" name="full_name" required>
      <label class="label" for="date_of_birth">Date of Birth</label>
      <input class="input" type="date" id="date_of_birth" name="date_of_birth" required>
      <label class="label" for="program">Program</label>
      <input class="input" type="text" id="program" name="program" placeholder="e.g. BSc Computer Science" required>
      <label class="label" for="level">Level</label>
      <input class="input" type="text" id="level" name="level" placeholder="e.g. 300" required>
      <button type="submit" class="btn-primary">Add to Roster</button>
    </form>
  </div>

  <div class="form-card">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Bulk Import (CSV)</div>
    <p style="font-size:12px;color:var(--muted-2);margin:0 0 12px">
      Header row required, in this exact order:<br>
      <code>student_number,full_name,date_of_birth,program,level</code><br>
      Dates must be in <code>YYYY-MM-DD</code> format.
    </p>
    <form method="post" action="<?= $base ?>/admin/roster/import" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <input class="input" type="file" name="csv" accept=".csv,text/csv" required>
      <button type="submit" class="btn-primary">Import CSV</button>
    </form>
  </div>
</div>

<div class="desktop-toolbar" style="margin-top:24px">
  <form method="get" action="<?= $base ?>/admin/roster" class="search-input-wrap">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/></svg>
    <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES) ?>" placeholder="Search roster&hellip;" class="input">
  </form>
  <div class="filter-row">
    <?php foreach (['' => 'All', 'unclaimed' => 'Awaiting Sign-up', 'claimed' => 'Claimed'] as $key => $label): ?>
      <a href="<?= $base ?>/admin/roster?status=<?= $key ?>" class="filter-pill<?= ($filters['status'] ?? '') === $key ? ' active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($roster === []): ?>
  <div class="empty-state"><div class="empty-state-desc">No roster entries match this filter.</div></div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Student ID</th>
        <th>Full Name</th>
        <th>Date of Birth</th>
        <th>Program / Level</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($roster as $r): ?>
        <?php $claimed = !empty($r['claimed_by_user_id']); ?>
        <tr>
          <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars((string) $r['student_number'], ENT_QUOTES) ?></td>
          <td style="font-weight:600"><?= htmlspecialchars((string) $r['full_name'], ENT_QUOTES) ?></td>
          <td style="color:var(--muted-2)"><?= htmlspecialchars((string) $r['date_of_birth'], ENT_QUOTES) ?></td>
          <td style="color:var(--muted-2)"><?= htmlspecialchars((string) $r['program'], ENT_QUOTES) ?> &middot; <?= htmlspecialchars((string) $r['level'], ENT_QUOTES) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= $claimed ? '#0D9F6E' : '#D1D9E4' ?>"></div>
              <span style="color:var(--muted-2)"><?= $claimed ? 'Claimed' : 'Awaiting sign-up' ?></span>
            </div>
          </td>
          <td style="text-align:right">
            <?php if (!$claimed): ?>
              <form method="post" action="<?= $base ?>/admin/roster/<?= (int) $r['roster_id'] ?>/delete" style="display:inline" data-confirm="This cannot be undone." data-confirm-title="Remove this roster entry?" data-confirm-danger>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <button type="submit" class="filter-pill" style="padding:5px 10px;font-size:12px;color:var(--red)">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../partials/admin_chrome_close.php'; ?>
