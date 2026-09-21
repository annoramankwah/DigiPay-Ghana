<?php
/**
 * Desktop sidebar shell for the Administrator role.
 * @var string|null $title
 * @var string|null $active  one of: overview, users, audit, settings, backup
 */
use App\Core\Request;
use App\Core\Session;
use App\Support\InstitutionContext;

$title = $title ?? 'DigiPay Ghana';
require __DIR__ . '/head.php';

$base = Request::basePath();
$active = $active ?? '';
$isSuperAdmin = InstitutionContext::isSuperAdmin();

$initials = '';
foreach (explode(' ', (string) Session::get('name')) as $part) {
    $initials .= mb_substr($part, 0, 1);
}

$navLink = static fn (string $key) => $active === $key ? 'sidebar-link active' : 'sidebar-link';
?>
<div class="desktop-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="app-header-badge">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="#0A1628" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div>
        <div class="sidebar-brand-name">DigiPay</div>
        <div class="sidebar-brand-role">Administration</div>
      </div>
      <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Collapse sidebar" aria-expanded="true">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= $base ?>/admin/overview" class="<?= $navLink('overview') ?>">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/></svg>
        <span>Overview</span>
      </a>
      <a href="<?= $base ?>/admin/users" class="<?= $navLink('users') ?>">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2" stroke="currentColor" stroke-width="2"/></svg>
        <span>Users</span>
      </a>
      <a href="<?= $base ?>/admin/roster" class="<?= $navLink('roster') ?>">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Student Roster</span>
      </a>
      <a href="<?= $base ?>/admin/audit-log" class="<?= $navLink('audit') ?>">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2"/></svg>
        <span>Audit Log</span>
      </a>
      <a href="<?= $base ?>/admin/settings" class="<?= $navLink('settings') ?>">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.32 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="2"/></svg>
        <span>Settings</span>
      </a>
      <?php if ($isSuperAdmin): ?>
        <a href="<?= $base ?>/admin/backup" class="<?= $navLink('backup') ?>">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3" stroke="currentColor" stroke-width="2"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" stroke="currentColor" stroke-width="2"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" stroke="currentColor" stroke-width="2"/></svg>
          <span>Backup</span>
        </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <?php if ($isSuperAdmin): ?>
        <a href="<?= $base ?>/super-admin/exit-school" class="sidebar-link" style="margin-bottom:4px">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span>Exit to Super Admin</span>
        </a>
      <?php endif; ?>
      <a href="<?= $base ?>/admin/profile" class="sidebar-user<?= $active === 'profile' ? ' active' : '' ?>">
        <div class="sidebar-user-avatar"><?= htmlspecialchars(strtoupper($initials), ENT_QUOTES) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= htmlspecialchars((string) Session::get('name'), ENT_QUOTES) ?></div>
          <div class="sidebar-user-hint">View profile</div>
        </div>
      </a>
      <a href="<?= $base ?>/logout" class="sidebar-link" style="margin-top:2px" data-confirm="You'll need to sign in again to continue." data-confirm-title="Sign out?">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Sign out</span>
      </a>
    </div>
  </aside>
  <div class="desktop-main">
    <main class="desktop-content">
