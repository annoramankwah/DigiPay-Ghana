<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRepository;
use App\Repositories\StudentRosterRepository;
use App\Repositories\UserRepository;

class StudentSignupService
{
    private StudentRosterRepository $roster;
    private UserRepository $users;
    private StudentRepository $students;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->roster = new StudentRosterRepository();
        $this->users = new UserRepository();
        $this->students = new StudentRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * Verifies the applicant against the pre-loaded roster (FR: no open
     * self-registration — identity must be confirmed against a record the
     * institution already holds) before any account is created.
     *
     * @return array{ok: bool, message?: string, roster?: array}
     */
    public function verify(string $studentNumber, string $fullName, string $dateOfBirth): array
    {
        $studentNumber = trim($studentNumber);
        $roster = $this->roster->findByStudentNumber($studentNumber);

        if (!$roster || !$this->namesMatch($roster['full_name'], $fullName) || $roster['date_of_birth'] !== $dateOfBirth) {
            // Deliberately generic: do not reveal which of the three fields was wrong.
            return ['ok' => false, 'message' => 'We could not match those details to a student record. Check your Student ID, full name, and date of birth, or contact the Accounts Office.'];
        }

        if ($roster['claimed_by_user_id'] !== null) {
            return ['ok' => false, 'message' => 'An account has already been created for this Student ID. Try signing in, or use "Forgot password".'];
        }

        if ($this->users->isLoginIdTaken($studentNumber)) {
            return ['ok' => false, 'message' => 'An account already exists for this Student ID. Try signing in, or use "Forgot password".'];
        }

        return ['ok' => true, 'roster' => $roster];
    }

    /**
     * @return array{ok: bool, message?: string, login_id?: string, password?: string}
     */
    public function register(int $rosterId, string $email, string $password): array
    {
        $roster = $this->roster->findById($rosterId);
        if (!$roster || $roster['claimed_by_user_id'] !== null) {
            return ['ok' => false, 'message' => 'This sign-up link has expired. Please start again.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($this->users->isEmailTaken($email)) {
            return ['ok' => false, 'message' => 'That email address is already in use.'];
        }

        $loginId = $roster['student_number'];

        $userId = $this->users->create([
            'login_id' => $loginId,
            'name' => $roster['full_name'],
            'email' => $email,
            'role' => 'student',
            'institution_id' => (int) $roster['institution_id'],
            'password' => $password,
        ]);

        $this->students->create($userId, (int) $roster['institution_id'], $roster['program'], $roster['level'], null);
        $this->roster->markClaimed($rosterId, $userId);

        $this->audit->log($userId, 'student_self_registered', 'success', 'user', (string) $userId, null, [
            'login_id' => $loginId,
        ]);

        return ['ok' => true, 'login_id' => $loginId, 'password' => $password];
    }

    private function namesMatch(string $a, string $b): bool
    {
        $normalize = static function (string $s): string {
            $s = mb_strtolower(trim($s));
            return (string) preg_replace('/\s+/', ' ', $s);
        };

        return $normalize($a) === $normalize($b);
    }
}
