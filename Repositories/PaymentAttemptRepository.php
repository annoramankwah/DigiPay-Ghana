<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class PaymentAttemptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payment_attempts (institution_id, transaction_id, provider, provider_reference, status, raw_callback_payload)
             VALUES (:institution_id, :transaction_id, :provider, :provider_reference, :status, :raw_callback_payload)'
        );
        $stmt->execute([
            ':institution_id' => $data['institution_id'],
            ':transaction_id' => $data['transaction_id'],
            ':provider' => $data['provider'],
            ':provider_reference' => $data['provider_reference'],
            ':status' => $data['status'] ?? 'pending',
            ':raw_callback_payload' => isset($data['payload']) ? json_encode($data['payload']) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findByProviderReference(string $reference): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payment_attempts WHERE provider_reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTransactionId(int $transactionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_attempts WHERE transaction_id = ? ORDER BY attempt_id DESC'
        );
        $stmt->execute([$transactionId]);
        return $stmt->fetchAll();
    }

    /**
     * Merges $payload into whatever was already recorded (e.g. the payer
     * details captured at creation) rather than overwriting it, so the
     * provider's callback result and the payer's submitted details both
     * survive in the same row.
     */
    public function updateStatus(int $attemptId, string $status, ?array $payload = null): void
    {
        $stmt = $this->db->prepare('SELECT raw_callback_payload FROM payment_attempts WHERE attempt_id = ?');
        $stmt->execute([$attemptId]);
        $existingRaw = $stmt->fetchColumn();
        $existing = $existingRaw ? (json_decode((string) $existingRaw, true) ?: []) : [];
        $merged = $payload !== null ? array_merge($existing, $payload) : $existing;

        $stmt = $this->db->prepare(
            'UPDATE payment_attempts SET status = ?, raw_callback_payload = ? WHERE attempt_id = ?'
        );
        $stmt->execute([$status, $merged !== [] ? json_encode($merged) : null, $attemptId]);
    }
}
