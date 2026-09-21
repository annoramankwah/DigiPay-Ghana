<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class StudentRosterRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByStudentNumber(string $studentNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM student_roster WHERE student_number = ? LIMIT 1');
        $stmt->execute([$studentNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isStudentNumberTaken(string $studentNumber): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM student_roster WHERE student_number = ?');
        $stmt->execute([$studentNumber]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO student_roster (institution_id, student_number, full_name, date_of_birth, program, level)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['institution_id'],
            $data['student_number'],
            $data['full_name'],
            $data['date_of_birth'],
            $data['program'],
            $data['level'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function markClaimed(int $rosterId, int $userId): void
    {
        $this->db->prepare('UPDATE student_roster SET claimed_by_user_id = ?, claimed_at = NOW() WHERE roster_id = ?')
            ->execute([$userId, $rosterId]);
    }

    /**
     * @param array{status?:string,search?:string} $filters
     * @param int|null $institutionId Scope to one school, or null for the platform-wide view (super_admin only).
     */
    public function findAllForAdmin(array $filters = [], ?int $institutionId = null): array
    {
        $sql = 'SELECT * FROM student_roster WHERE 1=1';
        $params = [];

        if ($institutionId !== null) {
            $sql .= ' AND institution_id = ?';
            $params[] = $institutionId;
        }
        if (!empty($filters['status'])) {
            $sql .= $filters['status'] === 'claimed' ? ' AND claimed_by_user_id IS NOT NULL' : ' AND claimed_by_user_id IS NULL';
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (full_name LIKE ? OR student_number LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $rosterId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM student_roster WHERE roster_id = ? LIMIT 1');
        $stmt->execute([$rosterId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteUnclaimed(int $rosterId, ?int $institutionId = null): bool
    {
        $sql = 'DELETE FROM student_roster WHERE roster_id = ? AND claimed_by_user_id IS NULL';
        $params = [$rosterId];
        if ($institutionId !== null) {
            $sql .= ' AND institution_id = ?';
            $params[] = $institutionId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
