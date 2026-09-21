<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Money;
use App\Repositories\PaymentAttemptRepository;
use App\Repositories\TransactionRepository;

/**
 * Drives the simulated payment lifecycle described in the PRD:
 *   initiate -> (simulated provider decides) -> resolve -> receipt/notification
 *
 * A "batch" groups every fee item covered by one payment action (e.g. "Pay
 * Full Balance" against several outstanding fee categories) so the student
 * experiences it as a single charge, even though each fee item still gets
 * its own Transaction/PaymentAttempt row per the schema. One coin flip
 * (PaymentSimulator) decides the outcome for the whole batch, mirroring a
 * single real-world wallet charge.
 */
class PaymentService
{
    private TransactionRepository $transactions;
    private PaymentAttemptRepository $attempts;
    private PaymentSimulator $simulator;
    private ReceiptService $receipts;
    private NotificationService $notifications;
    private AuditLogger $audit;
    private FeeService $feeService;

    public function __construct()
    {
        $this->transactions = new TransactionRepository();
        $this->attempts = new PaymentAttemptRepository();
        $this->simulator = new PaymentSimulator();
        $this->receipts = new ReceiptService();
        $this->notifications = new NotificationService();
        $this->audit = new AuditLogger();
        $this->feeService = new FeeService();
    }

    /**
     * Works out which fee items a payment covers, without writing anything
     * to the database yet. "full" covers every outstanding item; "partial"
     * fills outstanding items oldest-due-first until the amount is used up,
     * capping at the total outstanding so a mistyped amount can't overpay.
     *
     * @return array{items: array, total: float, error: ?string}
     */
    public function buildAllocationPreview(array $student, string $mode, ?float $partialAmount): array
    {
        $balance = $this->feeService->balanceFor($student);
        $payable = array_values(array_filter(
            $balance['items'],
            static fn (array $item) => $item['status'] === 'unpaid' && $item['remaining'] > 0.0
        ));

        if ($payable === []) {
            return ['items' => [], 'total' => 0.0, 'error' => 'You have no outstanding balance to pay.'];
        }

        usort($payable, static function (array $a, array $b) {
            return $a['due_date'] <=> $b['due_date'] ?: $a['fee_id'] <=> $b['fee_id'];
        });

        if ($mode === 'full') {
            $items = array_map(
                static fn (array $i) => ['fee_id' => $i['fee_id'], 'category' => $i['category'], 'amount' => round($i['remaining'], 2)],
                $payable
            );
            return ['items' => $items, 'total' => array_sum(array_column($items, 'amount')), 'error' => null];
        }

        $amount = $partialAmount ?? 0.0;
        if ($amount <= 0.0) {
            return ['items' => [], 'total' => 0.0, 'error' => 'Enter an amount greater than zero.'];
        }

        $totalOutstanding = array_sum(array_column($payable, 'remaining'));
        $amount = min($amount, $totalOutstanding);

        $remaining = $amount;
        $items = [];
        foreach ($payable as $i) {
            if ($remaining <= 0.0) {
                break;
            }
            $alloc = round(min($i['remaining'], $remaining), 2);
            if ($alloc <= 0.0) {
                continue;
            }
            $items[] = ['fee_id' => $i['fee_id'], 'category' => $i['category'], 'amount' => $alloc];
            $remaining -= $alloc;
        }

        return ['items' => $items, 'total' => array_sum(array_column($items, 'amount')), 'error' => null];
    }

    /**
     * Persists the preview as real Transactions + PaymentAttempts. This is
     * the point of no return in the flow: from here the provider (simulator)
     * has been "charged" and a callback is expected.
     *
     * @param array<string,string> $payerDetails Payer-supplied routing info for this
     *                                            method (e.g. momo_number, or bank_name
     *                                            + account_number) — stored for the
     *                                            office to cross-check during review.
     * @return array{transaction_ids: int[], attempts: array}
     */
    public function createBatch(array $student, array $previewItems, string $method, array $payerDetails = []): array
    {
        $institutionId = (int) $student['institution_id'];
        $studentId = (int) $student['student_id'];
        $userId = (int) $student['user_id'];

        $transactionIds = [];
        $attemptsInfo = [];

        foreach ($previewItems as $item) {
            $transactionId = $this->transactions->create([
                'institution_id' => $institutionId,
                'student_id' => $studentId,
                'fee_id' => $item['fee_id'],
                'amount_due' => $item['amount'],
                'status' => 'pending',
                'payment_method' => $method,
            ]);

            $reference = $this->simulator->generateReference(strtoupper($method));
            $attemptId = $this->attempts->create([
                'institution_id' => $institutionId,
                'transaction_id' => $transactionId,
                'provider' => $method,
                'provider_reference' => $reference,
                'status' => 'pending',
                'payload' => $payerDetails !== [] ? ['payer_details' => $payerDetails] : null,
            ]);

            $transactionIds[] = $transactionId;
            $attemptsInfo[] = [
                'transaction_id' => $transactionId,
                'attempt_id' => $attemptId,
                'provider_reference' => $reference,
            ];

            $this->audit->log($userId, 'payment_initiated', 'success', 'transaction', (string) $transactionId, null, [
                'amount' => $item['amount'],
                'method' => $method,
                'provider_reference' => $reference,
                'payer_details' => $payerDetails,
            ]);
        }

        return ['transaction_ids' => $transactionIds, 'attempts' => $attemptsInfo];
    }

    /**
     * Resolves a batch. Idempotent: a repeated call for attempts that are no
     * longer 'pending' is treated as a duplicate callback and short-circuits
     * to the already-recorded outcome instead of reprocessing.
     *
     * @return array{outcome: 'success'|'failed', already_resolved: bool}
     */
    public function resolveBatch(array $attemptsInfo, int $userId, int $institutionId): array
    {
        if ($attemptsInfo === []) {
            return ['outcome' => 'failed', 'already_resolved' => false];
        }

        $first = $this->attempts->findByProviderReference($attemptsInfo[0]['provider_reference']);
        if ($first && $first['status'] !== 'pending') {
            $this->audit->log($userId, 'duplicate_callback', 'failure', 'payment_attempt', (string) $first['attempt_id']);
            return ['outcome' => $first['status'] === 'success' ? 'success' : 'failed', 'already_resolved' => true];
        }

        $success = $this->simulator->decideOutcome();

        foreach ($attemptsInfo as $info) {
            $transaction = $this->transactions->findById($info['transaction_id']);
            if (!$transaction) {
                continue;
            }

            if ($success) {
                $amountDue = (float) $transaction['amount_due'];
                $amountPaid = $amountDue;
                if ($amountPaid > $amountDue) {
                    // Defensive guard; the preview step already caps allocations
                    // so this should not occur in the normal flow.
                    $this->audit->log(
                        $userId,
                        'overpayment_detected',
                        'failure',
                        'transaction',
                        (string) $info['transaction_id'],
                        ['amount_due' => $amountDue],
                        ['attempted_amount_paid' => $amountPaid]
                    );
                    $amountPaid = $amountDue;
                }

                // The provider confirming the charge is not the same as the
                // institution accepting it: money received stays 'pending'
                // until the Accounts Office verifies it (FR-ACC-03). Only
                // that manual verification issues a receipt.
                $this->transactions->markResolved($info['transaction_id'], 'pending', $amountPaid);
                $this->attempts->updateStatus($info['attempt_id'], 'success', ['result' => 'approved']);
                $this->notifications->notify(
                    $institutionId,
                    $userId,
                    sprintf('Payment of %s received and pending verification by the accounts office.', Money::format($amountPaid)),
                    'pending'
                );
                $this->audit->log($userId, 'payment_received', 'success', 'transaction', (string) $info['transaction_id'], null, [
                    'amount_paid' => $amountPaid,
                ]);
            } else {
                $this->transactions->markResolved($info['transaction_id'], 'failed', 0.0);
                $this->attempts->updateStatus($info['attempt_id'], 'failed', ['result' => 'declined']);
                $this->notifications->notify(
                    $institutionId,
                    $userId,
                    sprintf('Payment of %s failed. Please try again.', Money::format((float) $transaction['amount_due'])),
                    'error'
                );
                $this->audit->log($userId, 'payment_failed', 'failure', 'transaction', (string) $info['transaction_id']);
            }
        }

        return ['outcome' => $success ? 'success' : 'failed', 'already_resolved' => false];
    }
}
