<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;
use App\Repositories\UserRepository;
use DateTimeImmutable;

class PasswordResetService
{
    private UserRepository $users;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * Always behaves the same whether or not a matching account exists, so the
     * response never reveals which student IDs / emails are registered.
     */
    public function requestReset(string $loginId, string $email): void
    {
        $user = $this->users->findByEmailAndLoginId($loginId, $email);
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->users->setResetToken((int) $user['user_id'], $hash, new DateTimeImmutable('+30 minutes'));
        $this->deliver((string) $user['email'], $token);
        $this->audit->log((int) $user['user_id'], 'password_reset_requested', 'success', 'user', $loginId);
    }

    /**
     * No real mail transport for the academic prototype — the reset link is
     * written to storage/logs/mail.log so it can be picked up during a demo.
     */
    private function deliver(string $email, string $token): void
    {
        $link = rtrim((string) Env::get('APP_URL', ''), '/') . '/password-reset/' . $token;
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        file_put_contents(
            $logDir . '/mail.log',
            sprintf("[%s] Password reset link for %s: %s\n", date('c'), $email, $link),
            FILE_APPEND | LOCK_EX
        );
    }

    public function validateToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $user = $this->users->findByResetTokenHash($hash);
        if (!$user) {
            return null;
        }
        if (empty($user['reset_token_expires_at']) || strtotime((string) $user['reset_token_expires_at']) < time()) {
            return null;
        }
        return $user;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->validateToken($token);
        if (!$user) {
            return false;
        }
        $this->users->updatePassword((int) $user['user_id'], password_hash($newPassword, PASSWORD_BCRYPT));
        $this->audit->log((int) $user['user_id'], 'password_reset_completed', 'success', 'user', (string) $user['login_id']);
        return true;
    }
}
