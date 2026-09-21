<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class FeeStructureRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Fee items in effect today for a given program/level.
     */
    public function findActiveForProgramLevel(int $institutionId, string $program, string $level): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fee_structures
             WHERE institution_id = ? AND program = ? AND level = ?
               AND effective_from <= CURDATE()
               AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY fee_id ASC'
        );
        $stmt->execute([$institutionId, $program, $level]);
        return $stmt->fetchAll();
    }

    public function findById(int $feeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fee_structures WHERE fee_id = ? LIMIT 1');
        $stmt->execute([$feeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Every fee item currently in effect (effective_to IS NULL or in the
     * future), grouped for the office's fee-structures screen — one entry
     * per program/level/term, each with its category line items.
     */
    public function findAllCurrent(int $institutionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM fee_structures
             WHERE institution_id = ?
               AND effective_from <= CURDATE()
               AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY program ASC, level ASC, fee_id ASC'
        );
        $stmt->execute([$institutionId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO fee_structures
                (institution_id, program, level, academic_term, category, amount, currency, due_date, effective_from, effective_to, created_by)
             VALUES (:institution_id, :program, :level, :academic_term, :category, :amount, :currency, :due_date, :effective_from, :effective_to, :created_by)'
        );
        $stmt->execute([
            ':institution_id' => $data['institution_id'],
            ':program' => $data['program'],
            ':level' => $data['level'],
            ':academic_term' => $data['academic_term'],
            ':category' => $data['category'],
            ':amount' => $data['amount'],
            ':currency' => $data['currency'] ?? 'GHS',
            ':due_date' => $data['due_date'],
            ':effective_from' => $data['effective_from'],
            ':effective_to' => $data['effective_to'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Closes out a fee item as of a given date (used when a revision
     * supersedes it), preserving history instead of overwriting the amount.
     */
    public function closeAsOf(int $feeId, string $effectiveTo): void
    {
        $this->db->prepare('UPDATE fee_structures SET effective_to = ? WHERE fee_id = ?')
            ->execute([$effectiveTo, $feeId]);
    }
}
