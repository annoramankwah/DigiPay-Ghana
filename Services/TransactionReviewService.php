<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Money;
use App\Repositories\AuditLogRepository;
use App\Repositories\TransactionRepository;

/**
 * The Accounts Office side of the payment lifecycle (FR-ACC-03, FR-ACC-07):
 * a transaction the simulator marked 'pending' sits here until a human
 * verifies or rejects it, optionally leaving reconciliation remarks.
 */
class TransactionReviewService
{
    private TransactionRepository $transactions;
    private AuditLogRepository $auditRepo;
    private ReceiptService $receipts;
    private NotificationService $notifications;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->transactions = new TransactionRepository();
        $this->auditRepo = new AuditLogRepository();
        $this->receipts = new ReceiptService();
        $this->notifications = new NotificationService();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function verify(int $officerId, int $transactionId, ?string $remarks, ?int $institutionId = null): array
    {
        $transaction = $this->transactions->findByIdForOffice($transactionId, $institutionId);
        if (!$transaction) {
            return ['ok' => false, 'message' => 'Transaction not found.'];
        }
        if ($transaction['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending transactions can be verified.'];
        }

        if ($remarks !== null && $remarks !== '') {
            $this->addRemark($officerId, $transactionId, $remarks);
        }

        $this->transactions->markVerifiedByOffice($transactionId);
        $receiptNumber = $this->receipts->issueFor((int) $transaction['institution_id'], $transactionId);

        $studentUserId = $this->studentUserId($transaction);
        $this->notifications->notify(
            (int) $transaction['institution_id'],
            $studentUserId,
            sprintf('Payment of %s verified. Receipt %s issued.', Money::format((float) $transaction['amount_paid']), $receiptNumber),
            'success'
        );

        $this->audit->log($officerId, 'payment_verified_by_office', 'success', 'transaction', (string) $transactionId, null, [
            'receipt_number' => $receiptNumber,
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function reject(int $officerId, int $transactionId, ?string $remarks, ?int $institutionId = null): array
    {
        $transaction = $this->transactions->findByIdForOffice($transactionId, $institutionId);
        if (!$transaction) {
            return ['ok' => false, 'message' => 'Transaction not found.'];
        }
        if ($transaction['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending transactions can be rejected.'];
        }

        if ($remarks !== null && $remarks !== '') {
            $this->addRemark($officerId, $transactionId, $remarks);
        }

        $this->transactions->markRejectedByOffice($transactionId);

        $studentUserId = $this->studentUserId($transaction);
        $this->notifications->notify(
            (int) $transaction['institution_id'],
            $studentUserId,
            sprintf(
                'Your payment of %s was rejected by the accounts office.%s',
                Money::format((float) $transaction['amount_paid']),
                $remarks ? ' Reason: ' . $remarks : ''
            ),
            'error'
        );

        $this->audit->log($officerId, 'payment_rejected_by_office', 'success', 'transaction', (string) $transactionId, null, [
            'remarks' => $remarks,
        ]);

        return ['ok' => true];
    }

    public function addRemarkOnly(int $officerId, int $transactionId, string $remarks): void
    {
        $this->addRemark($officerId, $transactionId, $remarks);
    }

    public function remarksFor(int $transactionId): array
    {
        return $this->auditRepo->findByTargetTransaction($transactionId, 'reconciliation_remark');
    }

    private function addRemark(int $officerId, int $transactionId, string $remarks): void
    {
        $this->audit->log($officerId, 'reconciliation_remark', 'success', 'transaction', (string) $transactionId, null, [
            'remarks' => $remarks,
        ]);
    }

    /**
     * The transaction row only carries student_id; resolve it to the
     * underlying user_id for notifications.
     */
    private function studentUserId(array $transaction): int
    {
        $stmt = \App\Config\Database::connection()->prepare('SELECT user_id FROM students WHERE student_id = ?');
        $stmt->execute([(int) $transaction['s_student_id']]);
        return (int) $stmt->fetchColumn();
    }
}
