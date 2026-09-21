<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;

class ProfileService
{
    private UserRepository $users;
    private StudentRepository $students;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->students = new StudentRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function update(int $userId, int $studentId, string $name, string $email, string $phone): array
    {
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);

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
        $this->students->updateContactInfo($studentId, $phone);

        $this->audit->log($userId, 'profile_updated', 'success', 'user', (string) $userId, null, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        return ['ok' => true];
    }
}
