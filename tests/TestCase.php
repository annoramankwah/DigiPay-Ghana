<?php

declare(strict_types=1);

namespace Tests;

use App\Config\Database;
use App\Support\Migrator;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connection();
        Migrator::truncateAll();
    }

    protected function makeInstitution(): int
    {
        $stmt = $this->db->prepare('INSERT INTO institutions (name, contact_info) VALUES (?, ?)');
        $stmt->execute(['Test University', 'info@test.edu']);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array{user_id: int, student_id: int}
     */
    protected function makeStudent(int $institutionId, string $program = 'BSc Computer Science', string $level = '300'): array
    {
        static $seq = 0;
        $seq++;

        $stmt = $this->db->prepare(
            'INSERT INTO users (login_id, name, email, role, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(["STU/TEST/{$seq}", "Test Student {$seq}", "student{$seq}@test.edu", 'student', password_hash('Password123', PASSWORD_BCRYPT), 'active']);
        $userId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO students (user_id, institution_id, program, level, contact_info) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $institutionId, $program, $level, null]);
        $studentId = (int) $this->db->lastInsertId();

        return ['user_id' => $userId, 'student_id' => $studentId];
    }

    protected function makeOfficeUser(): int
    {
        static $seq = 0;
        $seq++;

        $stmt = $this->db->prepare(
            'INSERT INTO users (login_id, name, email, role, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(["STF/TEST/{$seq}", "Test Officer {$seq}", "officer{$seq}@test.edu", 'account_office', password_hash('Password123', PASSWORD_BCRYPT), 'active']);
        return (int) $this->db->lastInsertId();
    }

    protected function makeFeeStructure(
        int $institutionId,
        int $createdBy,
        string $category,
        float $amount,
        string $program = 'BSc Computer Science',
        string $level = '300',
        ?string $dueDate = null,
        ?string $effectiveFrom = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO fee_structures
                (institution_id, program, level, academic_term, category, amount, currency, due_date, effective_from, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $institutionId, $program, $level, '2026/2027 Semester 1', $category, $amount, 'GHS',
            $dueDate ?? date('Y-m-d', strtotime('+30 days')),
            $effectiveFrom ?? date('Y-m-d', strtotime('-1 day')),
            $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Loads a "student" array shaped like StudentRepository::findByUserId(),
     * which is what FeeService/PaymentService expect as input.
     */
    protected function studentRow(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, u.name, u.email, u.login_id
             FROM students s JOIN users u ON u.user_id = s.user_id
             WHERE s.user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}
