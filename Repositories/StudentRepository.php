<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class StudentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, u.name, u.email, u.login_id
             FROM students s
             JOIN users u ON u.user_id = s.user_id
             WHERE s.user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateContactInfo(int $studentId, string $contactInfo): void
    {
        $this->db->prepare('UPDATE students SET contact_info = ? WHERE student_id = ?')
            ->execute([$contactInfo, $studentId]);
    }

    public function create(int $userId, int $institutionId, string $program, string $level, ?string $contactInfo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO students (user_id, institution_id, program, level, contact_info) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $institutionId, $program, $level, $contactInfo]);
        return (int) $this->db->lastInsertId();
    }

    public function updateProgramLevel(int $studentId, string $program, string $level): void
    {
        $this->db->prepare('UPDATE students SET program = ?, level = ? WHERE student_id = ?')
            ->execute([$program, $level, $studentId]);
    }
}
