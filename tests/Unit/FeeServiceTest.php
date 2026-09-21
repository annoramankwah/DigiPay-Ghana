<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FeeService;
use Tests\TestCase;

final class FeeServiceTest extends TestCase
{
    public function testBalanceExcludesAmountsAlreadyPendingVerification(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $stmt = $this->db->prepare(
            "INSERT INTO transactions (institution_id, student_id, fee_id, amount_due, amount_paid, status, payment_method)
             VALUES (?, ?, ?, 200.00, 200.00, 'pending', 'momo')"
        );
        $stmt->execute([$institutionId, $student['student_id'], $feeId]);

        $balance = (new FeeService())->balanceFor($this->studentRow($student['user_id']));

        self::assertEqualsWithDelta(0.0, $balance['totalOutstanding'], 0.001);
        self::assertSame('pending', $balance['items'][0]['status']);
    }

    public function testBalanceReflectsVerifiedPaymentsAsPaid(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $stmt = $this->db->prepare(
            "INSERT INTO transactions (institution_id, student_id, fee_id, amount_due, amount_paid, status, payment_method)
             VALUES (?, ?, ?, 200.00, 200.00, 'verified', 'momo')"
        );
        $stmt->execute([$institutionId, $student['student_id'], $feeId]);

        $balance = (new FeeService())->balanceFor($this->studentRow($student['user_id']));

        self::assertEqualsWithDelta(0.0, $balance['totalOutstanding'], 0.001);
        self::assertSame('paid', $balance['items'][0]['status']);
    }

    public function testFailedPaymentDoesNotReduceOutstandingBalance(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'SRC Dues', 200.00);

        $stmt = $this->db->prepare(
            "INSERT INTO transactions (institution_id, student_id, fee_id, amount_due, amount_paid, status, payment_method)
             VALUES (?, ?, ?, 200.00, 0.00, 'failed', 'momo')"
        );
        $stmt->execute([$institutionId, $student['student_id'], $feeId]);

        $balance = (new FeeService())->balanceFor($this->studentRow($student['user_id']));

        self::assertEqualsWithDelta(200.00, $balance['totalOutstanding'], 0.001);
        self::assertSame('unpaid', $balance['items'][0]['status']);
    }
}
