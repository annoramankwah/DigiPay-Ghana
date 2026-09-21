<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FeeStructureService;
use Tests\TestCase;

final class FeeStructureServiceTest extends TestCase
{
    public function testReviseClosesOldRowAndOpensANewOneRatherThanOverwriting(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);

        $result = (new FeeStructureService())->revise($officer, $feeId, 3400.00, date('Y-m-d', strtotime('+60 days')));

        self::assertTrue($result['ok']);

        $stmt = $this->db->prepare('SELECT amount, effective_to FROM fee_structures WHERE fee_id = ?');
        $stmt->execute([$feeId]);
        $old = $stmt->fetch();
        self::assertEqualsWithDelta(3200.00, (float) $old['amount'], 0.001);
        self::assertNotNull($old['effective_to'], 'The old fee row must be closed, not overwritten.');

        $newRow = $this->db->query(
            'SELECT amount, effective_to FROM fee_structures ORDER BY fee_id DESC LIMIT 1'
        )->fetch();
        self::assertEqualsWithDelta(3400.00, (float) $newRow['amount'], 0.001);
        self::assertNull($newRow['effective_to']);
    }

    public function testTransactionsMadeUnderTheOldAmountKeepResolvingAgainstIt(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $student = $this->makeStudent($institutionId);
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);

        $stmt = $this->db->prepare(
            "INSERT INTO transactions (institution_id, student_id, fee_id, amount_due, amount_paid, status, payment_method)
             VALUES (?, ?, ?, 3200.00, 3200.00, 'verified', 'momo')"
        );
        $stmt->execute([$institutionId, $student['student_id'], $feeId]);

        (new FeeStructureService())->revise($officer, $feeId, 3400.00, date('Y-m-d', strtotime('+60 days')));

        // The historical transaction still points at fee_id, whose amount is untouched.
        $stmt = $this->db->prepare(
            'SELECT f.amount FROM transactions t JOIN fee_structures f ON f.fee_id = t.fee_id WHERE t.fee_id = ?'
        );
        $stmt->execute([$feeId]);
        self::assertEqualsWithDelta(3200.00, (float) $stmt->fetchColumn(), 0.001);
    }

    public function testReviseRejectsAZeroOrNegativeAmount(): void
    {
        $institutionId = $this->makeInstitution();
        $officer = $this->makeOfficeUser();
        $feeId = $this->makeFeeStructure($institutionId, $officer, 'Tuition', 3200.00);

        $result = (new FeeStructureService())->revise($officer, $feeId, 0.0, date('Y-m-d'));

        self::assertFalse($result['ok']);
    }
}
