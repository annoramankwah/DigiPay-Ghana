<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DisputeRepository;
use App\Repositories\TransactionRepository;

class DisputeService
{
    private DisputeRepository $disputes;
    private TransactionRepository $transactions;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->disputes = new DisputeRepository();
        $this->transactions = new TransactionRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function raise(int $userId, int $studentId, int $transactionId, string $issueSummary, ?string $description): array
    {
        $transaction = $this->transactions->findById($transactionId);
        if (!$transaction || (int) $transaction['student_id'] !== $studentId) {
            return ['ok' => false, 'message' => 'That transaction reference could not be found on your account.'];
        }

        if ($this->disputes->hasOpenForTransaction($transactionId)) {
            return ['ok' => false, 'message' => 'There is already an open dispute for this transaction.'];
        }

        $disputeId = $this->disputes->create([
            'institution_id' => $transaction['institution_id'],
            'transaction_id' => $transactionId,
            'raised_by' => $userId,
            'issue_summary' => $issueSummary,
            'description' => $description,
        ]);

        $this->audit->log($userId, 'dispute_raised', 'success', 'dispute', (string) $disputeId, null, [
            'transaction_id' => $transactionId,
            'issue_summary' => $issueSummary,
        ]);

        return ['ok' => true];
    }

    public function listForUser(int $userId): array
    {
        return $this->disputes->findByRaisedBy($userId);
    }
}
