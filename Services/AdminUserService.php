<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;

class AdminUserService
{
    private const ROLES = ['student', 'account_office', 'admin', 'super_admin'];

    private UserRepository $users;
    private StudentRepository $students;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->students = new StudentRepository();
        $this->audit = new AuditLogger();
    }

    public function list(array $filters, ?int $institutionId = null): array
    {
        return $this->users->findAllForAdmin($filters, $institutionId);
    }

    /**
     * @param int|null $institutionId The acting admin's own school (null only for super_admin);
     *                                 a plain admin can only ever create users in their own school.
     * @return array{ok: bool, message?: string}
     */
    public function create(int $adminId, ?int $institutionId, array $data, bool $actingAsSuperAdmin = false): array
    {
        $loginId = trim((string) ($data['login_id'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $role = (string) ($data['role'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $program = trim((string) ($data['program'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));

        if ($loginId === '' || $name === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'All fields are required.'];
        }
        if (!in_array($role, self::ROLES, true)) {
            return ['ok' => false, 'message' => 'Invalid role.'];
        }
        if ($role === 'super_admin' && !$actingAsSuperAdmin) {
            return ['ok' => false, 'message' => 'Only a super administrator can create another super administrator.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($this->users->isLoginIdTaken($loginId)) {
            return ['ok' => false, 'message' => 'That Student/Staff ID is already in use.'];
        }
        if ($this->users->isEmailTaken($email)) {
            return ['ok' => false, 'message' => 'That email is already in use.'];
        }
        if ($role === 'student' && ($program === '' || $level === '')) {
            return ['ok' => false, 'message' => 'Program and level are required for student accounts.'];
        }
        if ($role !== 'super_admin' && $institutionId === null) {
            return ['ok' => false, 'message' => 'Select a school before creating this account.'];
        }

        $userId = $this->users->create([
            'login_id' => $loginId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'institution_id' => $role === 'super_admin' ? null : $institutionId,
            'password' => $password,
        ]);

        if ($role === 'student') {
            $this->students->create($userId, $institutionId, $program, $level, null);
        }

        $this->audit->log($adminId, 'user_created', 'success', 'user', (string) $userId, null, [
            'login_id' => $loginId,
            'role' => $role,
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function update(int $adminId, int $userId, ?int $institutionId, array $data, bool $actingAsSuperAdmin = false): array
    {
        $target = $this->users->findByIdIncludingDeleted($userId);
        if (!$target) {
            return ['ok' => false, 'message' => 'User not found.'];
        }
        if (!$actingAsSuperAdmin && (int) $target['institution_id'] !== $institutionId) {
            return ['ok' => false, 'message' => 'You cannot manage a user from another school.'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $role = (string) ($data['role'] ?? '');
        $program = trim((string) ($data['program'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));

        if ($name === '' || $email === '' || !in_array($role, self::ROLES, true)) {
            return ['ok' => false, 'message' => 'Name, email, and a valid role are required.'];
        }
        if ($role === 'super_admin' && !$actingAsSuperAdmin) {
            return ['ok' => false, 'message' => 'Only a super administrator can assign that role.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if ($this->users->isEmailTakenByOther($email, $userId)) {
            return ['ok' => false, 'message' => 'That email is already in use by another account.'];
        }
        if ($adminId === $userId && $role !== 'admin' && $role !== 'super_admin') {
            return ['ok' => false, 'message' => 'You cannot remove your own administrator role.'];
        }
        if ($role === 'student' && (empty($target['student_id']) && ($program === '' || $level === ''))) {
            return ['ok' => false, 'message' => 'Program and level are required to make this account a student.'];
        }

        $before = ['name' => $target['name'], 'email' => $target['email'], 'role' => $target['role']];

        $this->users->updateProfile($userId, $name, $email);
        if ($role !== $target['role']) {
            $this->users->updateRole($userId, $role);
            if ($role === 'super_admin') {
                $this->users->updateInstitution($userId, null);
            } elseif ((int) $target['institution_id'] === 0 || $target['institution_id'] === null) {
                $this->users->updateInstitution($userId, $institutionId);
            }
        }

        if ($role === 'student') {
            if (!empty($target['student_id'])) {
                if ($program !== '' && $level !== '') {
                    $this->students->updateProgramLevel((int) $target['student_id'], $program, $level);
                }
            } else {
                $this->students->create($userId, $institutionId ?? (int) $target['institution_id'], $program, $level, null);
            }
        }

        $this->audit->log($adminId, 'user_updated', 'success', 'user', (string) $userId, $before, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function setStatus(int $adminId, int $userId, string $status, ?int $institutionId = null, bool $actingAsSuperAdmin = false): array
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }
        if ($adminId === $userId && $status === 'inactive') {
            return ['ok' => false, 'message' => 'You cannot deactivate your own account.'];
        }
        $target = $this->users->findByIdIncludingDeleted($userId);
        if (!$target) {
            return ['ok' => false, 'message' => 'User not found.'];
        }
        if (!$actingAsSuperAdmin && (int) $target['institution_id'] !== $institutionId) {
            return ['ok' => false, 'message' => 'You cannot manage a user from another school.'];
        }

        $this->users->setStatus($userId, $status);
        $this->audit->log($adminId, 'user_status_changed', 'success', 'user', (string) $userId, null, ['status' => $status]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function delete(int $adminId, int $userId, ?int $institutionId = null, bool $actingAsSuperAdmin = false): array
    {
        if ($adminId === $userId) {
            return ['ok' => false, 'message' => 'You cannot delete your own account.'];
        }
        $target = $this->users->findByIdIncludingDeleted($userId);
        if (!$target) {
            return ['ok' => false, 'message' => 'User not found.'];
        }
        if (!$actingAsSuperAdmin && (int) $target['institution_id'] !== $institutionId) {
            return ['ok' => false, 'message' => 'You cannot manage a user from another school.'];
        }

        $this->users->softDelete($userId);
        $this->audit->log($adminId, 'user_deleted', 'success', 'user', (string) $userId);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function restore(int $adminId, int $userId, ?int $institutionId = null, bool $actingAsSuperAdmin = false): array
    {
        $target = $this->users->findByIdIncludingDeleted($userId);
        if (!$target) {
            return ['ok' => false, 'message' => 'User not found.'];
        }
        if (!$actingAsSuperAdmin && (int) $target['institution_id'] !== $institutionId) {
            return ['ok' => false, 'message' => 'You cannot manage a user from another school.'];
        }

        $this->users->restore($userId);
        $this->audit->log($adminId, 'user_restored', 'success', 'user', (string) $userId);

        return ['ok' => true];
    }
}
