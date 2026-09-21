<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReceiptRepository;
use App\Repositories\TransactionRepository;

class ReceiptService
{
    private ReceiptRepository $receipts;
    private TransactionRepository $transactions;

    public function __construct()
    {
        $this->receipts = new ReceiptRepository();
        $this->transactions = new TransactionRepository();
    }

    public function issueFor(int $institutionId, int $transactionId): string
    {
        $number = $this->generateNumber($transactionId);
        $this->receipts->create($institutionId, $transactionId, $number);
        return $number;
    }

    private function generateNumber(int $transactionId): string
    {
        return sprintf('RCT-%s-%06d', date('Y'), $transactionId);
    }

    /**
     * Loads a receipt only if it belongs to the given student — receipts are
     * generated on demand from the DB, never served as static files, and are
     * always access-checked against the requesting session.
     */
    public function forStudentTransaction(int $transactionId, int $studentId): ?array
    {
        $transaction = $this->transactions->findById($transactionId);
        if (!$transaction || (int) $transaction['student_id'] !== $studentId) {
            return null;
        }
        if ($transaction['status'] !== 'verified') {
            return null;
        }
        $receipt = $this->receipts->findByTransactionId($transactionId);
        if (!$receipt) {
            return null;
        }
        return ['transaction' => $transaction, 'receipt' => $receipt];
    }
}
