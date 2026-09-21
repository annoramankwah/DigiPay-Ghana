<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FeeService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use App\Services\TransactionReviewService;
use Tests\TestCase;

/**
 * End-to-end: student initiates a payment, the simulated provider confirms
 * it, the Accounts Office verifies it, and the student can retrieve the
 * resulting receipt — then the provider callback fires again (as a real
 * mobile-money webhook sometimes does) and must be rejected as a duplicate
 * without any double-crediting.
 */
final class PaymentLifecycleTest extends TestCase
{
    public function testFullPayCallbackVerifyReceiptFlowIncludingDuplicateCallback(): void
    {
        $_ENV['PAYMENT_SIMULATOR_SUCCESS_RATE'] = '1';
        putenv('PAYMENT_SIMULATOR_SUCCESS_RATE=1');

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $studentRow = $this->studentRow($student['user_id']);

        // 1. Student sees the full outstanding balance.
        $balanceBefore = (new FeeService())->balanceFor($studentRow);
        self::assertEqualsWithDelta(3400.00, $balanceBefore['totalOutstanding'], 0.001);

        // 2. Student initiates a full payment.
        $paymentService = new PaymentService();
        $preview = $paymentService->buildAllocationPreview($studentRow, 'full', null);
        self::assertNull($preview['error']);
        $batch = $paymentService->createBatch($studentRow, $preview['items'], 'momo');
        self::assertCount(2, $batch['transaction_ids']);

        // 3. Provider "calls back" confirming the charge.
        $callbackResult = $paymentService->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);
        self::assertSame('success', $callbackResult['outcome']);

        foreach ($batch['transaction_ids'] as $transactionId) {
            $stmt = $this->db->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
            $stmt->execute([$transactionId]);
            self::assertSame('pending', $stmt->fetchColumn());
        }

        // Balance reflects money received but not yet verified — not double-counted, not still owed.
        $balanceAfterCallback = (new FeeService())->balanceFor($studentRow);
        self::assertEqualsWithDelta(0.0, $balanceAfterCallback['totalOutstanding'], 0.001);

        // 4. Accounts Office verifies both transactions.
        $review = new TransactionReviewService();
        foreach ($batch['transaction_ids'] as $transactionId) {
            $result = $review->verify($officer, $transactionId, 'Matched settlement report');
            self::assertTrue($result['ok']);
        }

        // 5. Student can retrieve a receipt for each, and only their own.
        $receiptService = new ReceiptService();
        foreach ($batch['transaction_ids'] as $transactionId) {
            $data = $receiptService->forStudentTransaction($transactionId, $student['student_id']);
            self::assertNotNull($data);
            self::assertSame('verified', $data['transaction']['status']);
        }

        $otherStudent = $this->makeStudent($institutionId);
        $stolenAccess = $receiptService->forStudentTransaction($batch['transaction_ids'][0], $otherStudent['student_id']);
        self::assertNull($stolenAccess, 'A student must never be able to fetch another student\'s receipt.');

        // 6. The provider retries the same webhook (network timeout, etc.) —
        // must be a no-op, not a second credit or a second receipt.
        $receiptCountBefore = (int) $this->db->query('SELECT COUNT(*) FROM receipts')->fetchColumn();
        $duplicateResult = $paymentService->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);
        $receiptCountAfter = (int) $this->db->query('SELECT COUNT(*) FROM receipts')->fetchColumn();

        self::assertTrue($duplicateResult['already_resolved']);
        self::assertSame($receiptCountBefore, $receiptCountAfter);

        foreach ($batch['transaction_ids'] as $transactionId) {
            $stmt = $this->db->prepare('SELECT status, amount_paid FROM transactions WHERE transaction_id = ?');
            $stmt->execute([$transactionId]);
            $row = $stmt->fetch();
            self::assertSame('verified', $row['status'], 'Duplicate callback must not revert office-verified status.');
        }
    }

    public function testRejectedPaymentLeavesNoReceiptAndBalanceStaysOwed(): void
    {
        $_ENV['PAYMENT_SIMULATOR_SUCCESS_RATE'] = '1';
        putenv('PAYMENT_SIMULATOR_SUCCESS_RATE=1');

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);
        $studentRow = $this->studentRow($student['user_id']);

        $paymentService = new PaymentService();
        $preview = $paymentService->buildAllocationPreview($studentRow, 'full', null);
        $batch = $paymentService->createBatch($studentRow, $preview['items'], 'card');
        $paymentService->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);

        (new TransactionReviewService())->reject($officer, $batch['transaction_ids'][0], 'Card statement mismatch');

        $receiptService = new ReceiptService();
        self::assertNull($receiptService->forStudentTransaction($batch['transaction_ids'][0], $student['student_id']));

        $balance = (new FeeService())->balanceFor($studentRow);
        self::assertEqualsWithDelta(200.00, $balance['totalOutstanding'], 0.001, 'A rejected payment must leave the fee owed again.');
    }
}
