<?php

declare(strict_types=1);

use App\Config\Env;
use App\Controllers\AuthController;
use App\Controllers\Student\DisputeController;
use App\Controllers\Student\HistoryController;
use App\Controllers\Student\HomeController;
use App\Controllers\Student\NotificationController;
use App\Controllers\Student\PaymentController;
use App\Controllers\Student\ProfileController;
use App\Controllers\Student\ReceiptController;
use App\Controllers\Office\DisputeController as OfficeDisputeController;
use App\Controllers\Office\FeeStructureController as OfficeFeeStructureController;
use App\Controllers\Office\ProfileController as OfficeProfileController;
use App\Controllers\Office\ReportController as OfficeReportController;
use App\Controllers\Office\TransactionController as OfficeTransactionController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\OverviewController;
use App\Controllers\Admin\ProfileController as AdminProfileController;
use App\Controllers\Admin\RosterController as AdminRosterController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Controllers\SuperAdmin\InstitutionController as SuperAdminInstitutionController;
use App\Controllers\SuperAdmin\OverviewController as SuperAdminOverviewController;
use App\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;
use App\Controllers\SignupController;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\RateLimitMiddleware;
use App\Core\Middleware\RoleMiddleware;
use App\Core\Middleware\SessionTimeoutMiddleware;
use App\Core\Response;
use App\Core\Router;

$router = new Router();

$auth = new AuthController();

$loginLimit = (int) Env::get('RATE_LIMIT_LOGIN_PER_MIN', 10);
$paymentLimit = (int) Env::get('RATE_LIMIT_PAYMENT_PER_MIN', 5);

$studentGuard = [
    new SessionTimeoutMiddleware(),
    new AuthMiddleware(),
    new RoleMiddleware(['student']),
];

// A super_admin can drop into the office/admin dashboards for whichever
// school they've picked via "manage as" (see InstitutionContext) — the
// underlying controllers/repositories already scope by that resolved id.
$officeGuard = [
    new SessionTimeoutMiddleware(),
    new AuthMiddleware(),
    new RoleMiddleware(['account_office', 'super_admin']),
];

$adminGuard = [
    new SessionTimeoutMiddleware(),
    new AuthMiddleware(),
    new RoleMiddleware(['admin', 'super_admin']),
];

// Whole-database backups are a platform-level operation, not a per-school one.
$superAdminGuard = [
    new SessionTimeoutMiddleware(),
    new AuthMiddleware(),
    new RoleMiddleware(['super_admin']),
];

$router->get('/', function (): void {
    Response::redirect('/login');
});

$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login'], [new RateLimitMiddleware('login', $loginLimit, 60)]);
$router->get('/logout', [$auth, 'logout']);

$signup = new SignupController();
$signupLimit = (int) Env::get('RATE_LIMIT_LOGIN_PER_MIN', 10);
$router->get('/signup', [$signup, 'showVerify']);
$router->post('/signup', [$signup, 'verify'], [new RateLimitMiddleware('signup-verify', $signupLimit, 60)]);
$router->get('/signup/complete', [$signup, 'showComplete']);
$router->post('/signup/complete', [$signup, 'complete'], [new RateLimitMiddleware('signup-complete', $signupLimit, 60)]);

$router->get('/password-reset', [$auth, 'showPasswordResetRequest']);
$router->post('/password-reset', [$auth, 'submitPasswordResetRequest'], [new RateLimitMiddleware('password-reset', 10, 60)]);
$router->get('/password-reset/{token}', [$auth, 'showPasswordResetForm']);
$router->post('/password-reset/{token}', [$auth, 'submitPasswordResetForm']);

$home = new HomeController();
$payment = new PaymentController();
$history = new HistoryController();
$receipt = new ReceiptController();
$dispute = new DisputeController();
$profile = new ProfileController();
$notifications = new NotificationController();

$router->get('/student', [$home, 'index'], $studentGuard);

$router->get('/student/pay', [$payment, 'show'], $studentGuard);
$router->post('/student/pay/amount', [$payment, 'setAmount'], $studentGuard);
$router->post('/student/pay/method', [$payment, 'setMethod'], $studentGuard);
$router->post('/student/pay/back', [$payment, 'back'], $studentGuard);
$router->post('/student/pay/confirm', [$payment, 'confirm'], [...$studentGuard, new RateLimitMiddleware('payment-initiate', $paymentLimit, 60)]);
$router->post('/student/pay/resolve', [$payment, 'resolve'], $studentGuard);
$router->post('/student/pay/reset', [$payment, 'reset'], $studentGuard);

$router->get('/student/history', [$history, 'index'], $studentGuard);
$router->get('/student/receipt/{transactionId}', [$receipt, 'show'], $studentGuard);

$router->get('/student/dispute', [$dispute, 'index'], $studentGuard);
$router->post('/student/dispute', [$dispute, 'store'], $studentGuard);

$router->get('/student/profile', [$profile, 'show'], $studentGuard);
$router->post('/student/profile', [$profile, 'update'], $studentGuard);

$router->get('/student/notifications', [$notifications, 'index'], $studentGuard);

$officeTxn = new OfficeTransactionController();
$officeFees = new OfficeFeeStructureController();
$officeReports = new OfficeReportController();
$officeDisputes = new OfficeDisputeController();
$officeProfile = new OfficeProfileController();

$router->get('/office', [$officeTxn, 'index'], $officeGuard);
$router->get('/office/transactions', [$officeTxn, 'index'], $officeGuard);
$router->get('/office/transactions/{transactionId}', [$officeTxn, 'show'], $officeGuard);
$router->post('/office/transactions/{transactionId}/verify', [$officeTxn, 'verify'], $officeGuard);
$router->post('/office/transactions/{transactionId}/reject', [$officeTxn, 'reject'], $officeGuard);
$router->post('/office/transactions/{transactionId}/remark', [$officeTxn, 'remark'], $officeGuard);

$router->get('/office/fee-structures', [$officeFees, 'index'], $officeGuard);
$router->post('/office/fee-structures', [$officeFees, 'store'], $officeGuard);
$router->post('/office/fee-structures/{feeId}/revise', [$officeFees, 'revise'], $officeGuard);

$router->get('/office/reports', [$officeReports, 'index'], $officeGuard);
$router->get('/office/reports/export', [$officeReports, 'export'], $officeGuard);

$router->get('/office/disputes', [$officeDisputes, 'index'], $officeGuard);
$router->post('/office/disputes/{disputeId}/respond', [$officeDisputes, 'respond'], $officeGuard);

$router->get('/office/profile', [$officeProfile, 'show'], $officeGuard);
$router->post('/office/profile', [$officeProfile, 'update'], $officeGuard);

$adminOverview = new OverviewController();
$adminUsers = new AdminUserController();
$adminAuditLog = new AuditLogController();
$adminSettings = new SettingsController();
$adminBackup = new BackupController();
$adminRoster = new AdminRosterController();
$adminProfile = new AdminProfileController();

$router->get('/admin', [$adminOverview, 'index'], $adminGuard);
$router->get('/admin/overview', [$adminOverview, 'index'], $adminGuard);

$router->get('/admin/users', [$adminUsers, 'index'], $adminGuard);
$router->get('/admin/users/new', [$adminUsers, 'create'], $adminGuard);
$router->post('/admin/users', [$adminUsers, 'store'], $adminGuard);
$router->get('/admin/users/{userId}/edit', [$adminUsers, 'edit'], $adminGuard);
$router->post('/admin/users/{userId}', [$adminUsers, 'update'], $adminGuard);
$router->post('/admin/users/{userId}/status', [$adminUsers, 'setStatus'], $adminGuard);
$router->post('/admin/users/{userId}/delete', [$adminUsers, 'delete'], $adminGuard);
$router->post('/admin/users/{userId}/restore', [$adminUsers, 'restore'], $adminGuard);

$router->get('/admin/roster', [$adminRoster, 'index'], $adminGuard);
$router->post('/admin/roster', [$adminRoster, 'store'], $adminGuard);
$router->post('/admin/roster/import', [$adminRoster, 'import'], $adminGuard);
$router->post('/admin/roster/{rosterId}/delete', [$adminRoster, 'delete'], $adminGuard);

$router->get('/admin/audit-log', [$adminAuditLog, 'index'], $adminGuard);

$router->get('/admin/settings', [$adminSettings, 'index'], $adminGuard);

$router->get('/admin/profile', [$adminProfile, 'show'], $adminGuard);
$router->post('/admin/profile', [$adminProfile, 'update'], $adminGuard);

// Whole-database backups are platform-level, so this stays super_admin-only
// even though it lives under /admin for reuse of the admin chrome/CSS.
$router->get('/admin/backup', [$adminBackup, 'index'], $superAdminGuard);
$router->post('/admin/backup', [$adminBackup, 'create'], $superAdminGuard);

$superAdminOverview = new SuperAdminOverviewController();
$superAdminInstitutions = new SuperAdminInstitutionController();
$superAdminAuditLog = new SuperAdminAuditLogController();
$superAdminProfile = new SuperAdminProfileController();

$router->get('/super-admin', [$superAdminOverview, 'index'], $superAdminGuard);

$router->get('/super-admin/profile', [$superAdminProfile, 'show'], $superAdminGuard);
$router->post('/super-admin/profile', [$superAdminProfile, 'update'], $superAdminGuard);

$router->get('/super-admin/institutions', [$superAdminInstitutions, 'index'], $superAdminGuard);
$router->get('/super-admin/institutions/new', [$superAdminInstitutions, 'create'], $superAdminGuard);
$router->post('/super-admin/institutions', [$superAdminInstitutions, 'store'], $superAdminGuard);
$router->get('/super-admin/institutions/{institutionId}/edit', [$superAdminInstitutions, 'edit'], $superAdminGuard);
$router->post('/super-admin/institutions/{institutionId}', [$superAdminInstitutions, 'update'], $superAdminGuard);
$router->post('/super-admin/institutions/{institutionId}/status', [$superAdminInstitutions, 'setStatus'], $superAdminGuard);
$router->post('/super-admin/institutions/{institutionId}/manage-as', [$superAdminInstitutions, 'manageAs'], $superAdminGuard);
$router->get('/super-admin/exit-school', [$superAdminInstitutions, 'exitManagedSchool'], $superAdminGuard);

$router->get('/super-admin/audit-log', [$superAdminAuditLog, 'index'], $superAdminGuard);

return $router;
