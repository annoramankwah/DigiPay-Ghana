<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

class StaffProfileService
{
    private UserRepository $users;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function update(int $userId, string $name, string $email): array
    {
        $name = trim($name);
        $email = trim($email);

        if ($name === '' || $email === '') {
            return ['ok' => false, 'message' => 'Name and email are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if ($this->users->isEmailTakenByOther($email, $userId)) {
            return ['ok' => false, 'message' => 'That email is already in use by another account.'];
        }

        $this->users->updateProfile($userId, $name, $email);
        $this->audit->log($userId, 'profile_updated', 'success', 'user', (string) $userId);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->users->findById($userId);
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
        $this->audit->log($userId, 'password_changed', 'success', 'user', (string) $userId);

        return ['ok' => true];
    }
}
