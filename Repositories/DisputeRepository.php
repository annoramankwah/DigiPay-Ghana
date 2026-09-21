<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class DisputeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO disputes (institution_id, transaction_id, raised_by, issue_summary, description, status)
             VALUES (:institution_id, :transaction_id, :raised_by, :issue_summary, :description, \'open\')'
        );
        $stmt->execute([
            ':institution_id' => $data['institution_id'],
            ':transaction_id' => $data['transaction_id'],
            ':raised_by' => $data['raised_by'],
            ':issue_summary' => $data['issue_summary'],
            ':description' => $data['description'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findByRaisedBy(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, t.amount_due, t.amount_paid, f.category
             FROM disputes d
             JOIN transactions t ON t.transaction_id = d.transaction_id
             JOIN fee_structures f ON f.fee_id = t.fee_id
             WHERE d.raised_by = ?
             ORDER BY d.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function hasOpenForTransaction(int $transactionId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM disputes WHERE transaction_id = ? AND status IN ('open','under_review')"
        );
        $stmt->execute([$transactionId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Office-facing queue: every dispute, newest first, joined with the
     * transaction and the student who raised it.
     */
    public function findAllForOffice(?string $status = null, ?int $institutionId = null): array
    {
        $sql = 'SELECT d.*, t.amount_due, t.amount_paid, f.category, u.name AS student_name, u.login_id AS student_login_id
                FROM disputes d
                JOIN transactions t ON t.transaction_id = d.transaction_id
                JOIN fee_structures f ON f.fee_id = t.fee_id
                JOIN users u ON u.user_id = d.raised_by
                WHERE 1=1';
        $params = [];
        if ($institutionId !== null) {
            $sql .= ' AND d.institution_id = ?';
            $params[] = $institutionId;
        }
        if ($status !== null && $status !== '') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY d.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByIdForOffice(int $disputeId, ?int $institutionId = null): ?array
    {
        $sql = 'SELECT d.*, t.amount_due, t.amount_paid, t.transaction_id, f.category, u.name AS student_name, u.login_id AS student_login_id
             FROM disputes d
             JOIN transactions t ON t.transaction_id = d.transaction_id
             JOIN fee_structures f ON f.fee_id = t.fee_id
             JOIN users u ON u.user_id = d.raised_by
             WHERE d.dispute_id = ?';
        $params = [$disputeId];
        if ($institutionId !== null) {
            $sql .= ' AND d.institution_id = ?';
            $params[] = $institutionId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function resolve(int $disputeId, int $resolvedBy, string $status, ?string $notes): void
    {
        $stmt = $this->db->prepare(
            "UPDATE disputes SET status = ?, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE dispute_id = ?"
        );
        $stmt->execute([$status, $notes, $resolvedBy, $disputeId]);
    }
}
