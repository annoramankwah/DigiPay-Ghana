<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class TransactionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * All transactions for a student, newest first, joined with the fee
     * category/name they belong to.
     */
    public function findByStudentId(int $studentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.category, f.program, f.level, f.academic_term
             FROM transactions t
             JOIN fee_structures f ON f.fee_id = t.fee_id
             WHERE t.student_id = ?
             ORDER BY t.timestamp DESC, t.transaction_id DESC'
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public function findById(int $transactionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.category, f.program, f.level, f.academic_term
             FROM transactions t
             JOIN fee_structures f ON f.fee_id = t.fee_id
             WHERE t.transaction_id = ?
             LIMIT 1'
        );
        $stmt->execute([$transactionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int,array> transactions keyed by transaction_id
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT t.*, f.category, f.program, f.level
             FROM transactions t
             JOIN fee_structures f ON f.fee_id = t.fee_id
             WHERE t.transaction_id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(int) $row['transaction_id']] = $row;
        }
        return $rows;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO transactions (institution_id, student_id, fee_id, amount_due, amount_paid, status, payment_method, timestamp)
             VALUES (:institution_id, :student_id, :fee_id, :amount_due, 0, :status, :payment_method, NOW())'
        );
        $stmt->execute([
            ':institution_id' => $data['institution_id'],
            ':student_id' => $data['student_id'],
            ':fee_id' => $data['fee_id'],
            ':amount_due' => $data['amount_due'],
            ':status' => $data['status'] ?? 'pending',
            ':payment_method' => $data['payment_method'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function markResolved(int $transactionId, string $status, float $amountPaid): void
    {
        $stmt = $this->db->prepare(
            'UPDATE transactions SET status = ?, amount_paid = ? WHERE transaction_id = ?'
        );
        $stmt->execute([$status, $amountPaid, $transactionId]);
    }

    /**
     * Office-facing transaction feed: joined with student/user/fee details,
     * newest first, with optional status/search/date filters.
     *
     * @param array{status?:string,search?:string,from?:string,to?:string} $filters
     * @param int|null $institutionId Scope to one school, or null for the platform-wide view (super_admin only).
     */
    public function findAllForOffice(array $filters = [], ?int $institutionId = null): array
    {
        $sql = 'SELECT t.*, f.category, f.program, f.level,
                       s.student_id AS s_student_id, u.name AS student_name, u.login_id AS student_login_id
                FROM transactions t
                JOIN fee_structures f ON f.fee_id = t.fee_id
                JOIN students s ON s.student_id = t.student_id
                JOIN users u ON u.user_id = s.user_id
                WHERE 1=1';
        $params = [];

        if ($institutionId !== null) {
            $sql .= ' AND t.institution_id = ?';
            $params[] = $institutionId;
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (u.name LIKE ? OR u.login_id LIKE ? OR t.transaction_id = ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = is_numeric($filters['search']) ? (int) $filters['search'] : 0;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(t.timestamp) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(t.timestamp) <= ?';
            $params[] = $filters['to'];
        }

        $sql .= ' ORDER BY t.timestamp DESC, t.transaction_id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByIdForOffice(int $transactionId, ?int $institutionId = null): ?array
    {
        $sql = 'SELECT t.*, f.category, f.program, f.level,
                    s.student_id AS s_student_id, s.contact_info, u.name AS student_name, u.login_id AS student_login_id
             FROM transactions t
             JOIN fee_structures f ON f.fee_id = t.fee_id
             JOIN students s ON s.student_id = t.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE t.transaction_id = ?';
        $params = [$transactionId];
        if ($institutionId !== null) {
            $sql .= ' AND t.institution_id = ?';
            $params[] = $institutionId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markVerifiedByOffice(int $transactionId): void
    {
        $this->db->prepare("UPDATE transactions SET status = 'verified' WHERE transaction_id = ?")
            ->execute([$transactionId]);
    }

    public function markRejectedByOffice(int $transactionId): void
    {
        $this->db->prepare("UPDATE transactions SET status = 'failed', amount_paid = 0 WHERE transaction_id = ?")
            ->execute([$transactionId]);
    }

    /**
     * Simple aggregate counts/totals for the reports screen and admin overview.
     *
     * @return array{total_received: float, count: int, verified: int, pending: int, failed: int}
     */
    public function summaryBetween(string $from, string $to, ?int $institutionId = null): array
    {
        $sql = "SELECT
                COALESCE(SUM(CASE WHEN status = 'verified' THEN amount_paid ELSE 0 END), 0) AS total_received,
                COUNT(*) AS count,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
             FROM transactions
             WHERE DATE(timestamp) BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($institutionId !== null) {
            $sql .= ' AND institution_id = ?';
            $params[] = $institutionId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [
            'total_received' => (float) $row['total_received'],
            'count' => (int) $row['count'],
            'verified' => (int) $row['verified'],
            'pending' => (int) $row['pending'],
            'failed' => (int) $row['failed'],
        ];
    }

    /**
     * Row detail for CSV export, joined for readable output.
     */
    public function listBetweenForExport(string $from, string $to, ?int $institutionId = null): array
    {
        $sql = 'SELECT t.transaction_id, t.timestamp, u.name AS student_name, u.login_id AS student_login_id,
                    f.category, t.amount_due, t.amount_paid, t.status, t.payment_method
             FROM transactions t
             JOIN fee_structures f ON f.fee_id = t.fee_id
             JOIN students s ON s.student_id = t.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE DATE(t.timestamp) BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($institutionId !== null) {
            $sql .= ' AND t.institution_id = ?';
            $params[] = $institutionId;
        }
        $sql .= ' ORDER BY t.timestamp ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
