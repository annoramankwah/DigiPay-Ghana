<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;
use App\Core\Session;
use App\Repositories\InstitutionRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

class AuthService
{
    private UserRepository $users;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string, user?: array}
     */
    public function attempt(string $loginId, string $password): array
    {
        $user = $this->users->findByLoginId($loginId);

        if (!$user) {
            // Same generic message as a wrong password — do not reveal whether the ID exists.
            $this->audit->log(null, 'login_failed', 'failure', 'user', $loginId, null, ['reason' => 'unknown_login_id']);
            return ['ok' => false, 'message' => 'Invalid credentials.'];
        }

        $userId = (int) $user['user_id'];

        if ($user['status'] !== 'active') {
            $this->audit->log($userId, 'login_failed', 'failure', 'user', $loginId, null, ['reason' => 'inactive_account']);
            return ['ok' => false, 'message' => 'This account is inactive. Contact the administrator.'];
        }

        if (!empty($user['institution_id'])) {
            $institution = (new InstitutionRepository())->findById((int) $user['institution_id']);
            if (!$institution || $institution['status'] !== 'active') {
                $this->audit->log($userId, 'login_failed', 'failure', 'user', $loginId, null, ['reason' => 'institution_inactive']);
                return ['ok' => false, 'message' => 'This school is currently inactive. Contact the platform administrator.'];
            }
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $this->audit->log($userId, 'login_failed', 'failure', 'user', $loginId, null, ['reason' => 'account_locked']);
            return ['ok' => false, 'message' => 'Account temporarily locked due to repeated failed attempts. Try again later.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $maxAttempts = (int) Env::get('LOGIN_MAX_ATTEMPTS', 5);
            $attempts = $this->users->incrementFailedAttempts($userId);

            if ($attempts >= $maxAttempts) {
                $lockMinutes = (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15);
                $this->users->lockAccount($userId, new DateTimeImmutable("+{$lockMinutes} minutes"));
                $this->audit->log($userId, 'account_locked', 'failure', 'user', $loginId, null, ['attempts' => $attempts]);
                return ['ok' => false, 'message' => "Too many failed attempts. Account locked for {$lockMinutes} minutes."];
            }

            $this->audit->log($userId, 'login_failed', 'failure', 'user', $loginId, null, [
                'reason' => 'bad_password',
                'attempts' => $attempts,
            ]);
            return ['ok' => false, 'message' => 'Invalid credentials.'];
        }

        $this->users->resetFailedAttempts($userId);

        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('role', $user['role']);
        Session::set('name', $user['name']);
        Session::set('login_id', $user['login_id']);
        Session::set('institution_id', isset($user['institution_id']) ? (int) $user['institution_id'] : null);
        Session::remove('acting_institution_id');
        Session::touch();

        $this->audit->log($userId, 'login_success', 'success', 'user', $loginId);

        return ['ok' => true, 'user' => $user];
    }

    public function logout(): void
    {
        $userId = Session::get('user_id');
        $loginId = Session::get('login_id');
        $this->audit->log(is_int($userId) ? $userId : null, 'logout', 'success', 'user', is_string($loginId) ? $loginId : null);
        Session::destroy();
    }
}
