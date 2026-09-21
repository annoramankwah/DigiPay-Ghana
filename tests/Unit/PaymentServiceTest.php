<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentService;
use Tests\TestCase;

final class PaymentServiceTest extends TestCase
{
    private function forceSuccessRate(float $rate): void
    {
        $_ENV['PAYMENT_SIMULATOR_SUCCESS_RATE'] = (string) $rate;
        putenv('PAYMENT_SIMULATOR_SUCCESS_RATE=' . $rate);
    }

    public function testAllocationPreviewFullCoversEveryUnpaidItem(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);

        $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $preview = (new PaymentService())->buildAllocationPreview($this->studentRow($student['user_id']), 'full', null);

        self::assertNull($preview['error']);
        self::assertCount(2, $preview['items']);
        self::assertEqualsWithDelta(3400.00, $preview['total'], 0.001);
    }

    public function testAllocationPreviewPartialFillsOldestDueDateFirst(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);

        // Later due date but created first — allocation must still prefer the earlier due date.
        $this->makeFeeStructure($institutionId, $officer, 'Examination Fee', 500.00, dueDate: '2026-12-01');
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00, dueDate: '2026-10-01');

        $preview = (new PaymentService())->buildAllocationPreview($this->studentRow($student['user_id']), 'partial', 200.00);

        self::assertNull($preview['error']);
        self::assertCount(1, $preview['items']);
        self::assertSame('SRC Dues', $preview['items'][0]['category']);
        self::assertEqualsWithDelta(200.00, $preview['total'], 0.001);
    }

    public function testAllocationPreviewSpillsIntoNextItemOnceFirstIsCovered(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);

        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00, dueDate: '2026-10-01');
        $this->makeFeeStructure($institutionId, $officer, 'Examination Fee', 500.00, dueDate: '2026-12-01');

        $preview = (new PaymentService())->buildAllocationPreview($this->studentRow($student['user_id']), 'partial', 300.00);

        self::assertCount(2, $preview['items']);
        self::assertSame('SRC Dues', $preview['items'][0]['category']);
        self::assertEqualsWithDelta(200.00, $preview['items'][0]['amount'], 0.001);
        self::assertSame('Examination Fee', $preview['items'][1]['category']);
        self::assertEqualsWithDelta(100.00, $preview['items'][1]['amount'], 0.001);
    }

    public function testAllocationPreviewCapsAmountAtTotalOutstandingToPreventOverpayment(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);

        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        // Student mistypes an amount far larger than what's actually owed.
        $preview = (new PaymentService())->buildAllocationPreview($this->studentRow($student['user_id']), 'partial', 5000.00);

        self::assertEqualsWithDelta(200.00, $preview['total'], 0.001);
    }

    public function testAllocationPreviewErrorsWhenNothingIsOutstanding(): void
    {
        $institutionId = $this->makeInstitution();
        $student = $this->makeStudent($institutionId);

        $preview = (new PaymentService())->buildAllocationPreview($this->studentRow($student['user_id']), 'full', null);

        self::assertNotNull($preview['error']);
        self::assertSame([], $preview['items']);
    }

    public function testCreateBatchPersistsOneTransactionAndAttemptPerFeeItem(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);

        $studentRow = $this->studentRow($student['user_id']);
        $service = new PaymentService();
        $preview = $service->buildAllocationPreview($studentRow, 'full', null);
        $batch = $service->createBatch($studentRow, $preview['items'], 'momo');

        self::assertCount(1, $batch['transaction_ids']);
        self::assertCount(1, $batch['attempts']);

        $stmt = $this->db->prepare('SELECT status, amount_due, amount_paid FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$batch['transaction_ids'][0]]);
        $row = $stmt->fetch();
        self::assertSame('pending', $row['status']);
        self::assertEqualsWithDelta(3200.00, (float) $row['amount_due'], 0.001);
        self::assertEqualsWithDelta(0.0, (float) $row['amount_paid'], 0.001);
    }

    public function testResolveBatchOnSuccessLeavesTransactionPendingForOfficeReview(): void
    {
        $this->forceSuccessRate(1.0);

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $studentRow = $this->studentRow($student['user_id']);
        $service = new PaymentService();
        $preview = $service->buildAllocationPreview($studentRow, 'full', null);
        $batch = $service->createBatch($studentRow, $preview['items'], 'momo');

        $result = $service->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);

        self::assertSame('success', $result['outcome']);
        self::assertFalse($result['already_resolved']);

        $stmt = $this->db->prepare('SELECT status, amount_paid FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$batch['transaction_ids'][0]]);
        $row = $stmt->fetch();

        // A confirmed charge is NOT the same as an office-verified payment
        // (FR-ACC-03) — it must land 'pending', never auto-'verified'.
        self::assertSame('pending', $row['status']);
        self::assertEqualsWithDelta(200.00, (float) $row['amount_paid'], 0.001);

        $receiptCount = (int) $this->db->query('SELECT COUNT(*) FROM receipts')->fetchColumn();
        self::assertSame(0, $receiptCount, 'No receipt should exist before office verification.');
    }

    public function testResolveBatchOnFailureZeroesAmountPaid(): void
    {
        $this->forceSuccessRate(0.0);

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $studentRow = $this->studentRow($student['user_id']);
        $service = new PaymentService();
        $preview = $service->buildAllocationPreview($studentRow, 'full', null);
        $batch = $service->createBatch($studentRow, $preview['items'], 'momo');

        $result = $service->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);

        self::assertSame('failed', $result['outcome']);

        $stmt = $this->db->prepare('SELECT status, amount_paid FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$batch['transaction_ids'][0]]);
        $row = $stmt->fetch();
        self::assertSame('failed', $row['status']);
        self::assertEqualsWithDelta(0.0, (float) $row['amount_paid'], 0.001);
    }

    public function testResolveBatchIsIdempotentAgainstADuplicateCallback(): void
    {
        $this->forceSuccessRate(1.0);

        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $studentRow = $this->studentRow($student['user_id']);
        $service = new PaymentService();
        $preview = $service->buildAllocationPreview($studentRow, 'full', null);
        $batch = $service->createBatch($studentRow, $preview['items'], 'momo');

        $first = $service->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);
        $second = $service->resolveBatch($batch['attempts'], $student['user_id'], $institutionId);

        self::assertFalse($first['already_resolved']);
        self::assertTrue($second['already_resolved']);
        self::assertSame($first['outcome'], $second['outcome']);

        // The critical assertion: the retried callback must not double-credit.
        $stmt = $this->db->prepare('SELECT amount_paid FROM transactions WHERE transaction_id = ?');
        $stmt->execute([$batch['transaction_ids'][0]]);
        self::assertEqualsWithDelta(200.00, (float) $stmt->fetchColumn(), 0.001);

        $duplicateLogCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'duplicate_callback'"
        )->fetchColumn();
        self::assertSame(1, $duplicateLogCount);
    }
}
