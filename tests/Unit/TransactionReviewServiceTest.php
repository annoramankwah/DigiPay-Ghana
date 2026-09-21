<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentService;
use App\Services\TransactionReviewService;
use Tests\TestCase;

final class TransactionReviewServiceTest extends TestCase
{
    /**
     * @return array{transaction_id: int, institution_id: int, officer_id: int}
     */
    private function makePendingTransaction(): array
    {
        $_ENV['PAYMENT_SIMULATOR_SUCCESS_RATE'] = '1';
        putenv('PAYMENT_SIMULATOR_SUCCESS_RATE=1');

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $studentRow = $this->studentRow($student['user_id']);
        $service = new PaymentService();
        $preview = $service->buildAllocationPreview($studentRow, 'full', null);
        $batch = $service->createBatch($studentRow, $preview['items'], 'momo');
        $service->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);

        return [
            'transaction_id' => $batch['transaction_ids'][0],
            'institution_id' => $institutionId,
            'officer_id' => $officer,
            'student_user_id' => $student['user_id'],
        ];
    }

    public function testVerifyIssuesAReceiptAndMarksTransactionVerified(): void
    {
        $ctx = $this->makePendingTransaction();

        $result = (new TransactionReviewService())->verify($ctx['officer_id'], $ctx['transaction_id'], 'Confirmed against statement');

        self::assertTrue($result['ok']);

        $stmt = $this->db->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$ctx['transaction_id']]);
        self::assertSame('verified', $stmt->fetchColumn());

        $receiptCount = (int) $this->db->query('SELECT COUNT(*) FROM receipts')->fetchColumn();
        self::assertSame(1, $receiptCount);
    }

    public function testRejectZeroesAmountPaidAndMarksFailed(): void
    {
        $ctx = $this->makePendingTransaction();

        $result = (new TransactionReviewService())->reject($ctx['officer_id'], $ctx['transaction_id'], 'Does not match settlement');

        self::assertTrue($result['ok']);

        $stmt = $this->db->prepare('SELECT status, amount_paid FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$ctx['transaction_id']]);
        $row = $stmt->fetch();
        self::assertSame('failed', $row['status']);
        self::assertEqualsWithDelta(0.0, (float) $row['amount_paid'], 0.001);
    }

    public function testCannotVerifyATransactionTwice(): void
    {
        $ctx = $this->makePendingTransaction();
        $service = new TransactionReviewService();

        $service->verify($ctx['officer_id'], $ctx['transaction_id'], null);
        $second = $service->verify($ctx['officer_id'], $ctx['transaction_id'], null);

        self::assertFalse($second['ok']);
        $receiptCount = (int) $this->db->query('SELECT COUNT(*) FROM receipts')->fetchColumn();
        self::assertSame(1, $receiptCount, 'Verifying twice must not issue a second receipt.');
    }

    public function testCannotRejectAnAlreadyVerifiedTransaction(): void
    {
        $ctx = $this->makePendingTransaction();
        $service = new TransactionReviewService();

        $service->verify($ctx['officer_id'], $ctx['transaction_id'], null);
        $result = $service->reject($ctx['officer_id'], $ctx['transaction_id'], null);

        self::assertFalse($result['ok']);

        $stmt = $this->db->prepare('SELECT status FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$ctx['transaction_id']]);
        self::assertSame('verified', $stmt->fetchColumn());
    }
}
