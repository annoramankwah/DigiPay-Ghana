<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class ReceiptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $institutionId, int $transactionId, string $receiptNumber): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO receipts (institution_id, transaction_id, receipt_number) VALUES (?, ?, ?)'
        );
        $stmt->execute([$institutionId, $transactionId, $receiptNumber]);
        return (int) $this->db->lastInsertId();
    }

    public function findByTransactionId(int $transactionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM receipts WHERE transaction_id = ? LIMIT 1');
        $stmt->execute([$transactionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
