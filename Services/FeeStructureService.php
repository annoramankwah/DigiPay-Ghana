<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeeStructureRepository;
use DateTimeImmutable;

/**
 * Fee structure edits are effective-dated (FR-ACC-06): revising an amount
 * never overwrites history. It closes the current row as of yesterday and
 * inserts a new one effective today, so past transactions still resolve
 * against the fee amount that was actually in force when they were made.
 */
class FeeStructureService
{
    private FeeStructureRepository $fees;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->fees = new FeeStructureRepository();
        $this->audit = new AuditLogger();
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function create(int $officerId, array $data): array
    {
        $required = ['institution_id', 'program', 'level', 'academic_term', 'category', 'amount', 'due_date', 'effective_from'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                return ['ok' => false, 'message' => 'All fields are required.'];
            }
        }
        if ((float) $data['amount'] <= 0) {
            return ['ok' => false, 'message' => 'Amount must be greater than zero.'];
        }

        $feeId = $this->fees->create([
            'institution_id' => $data['institution_id'],
            'program' => $data['program'],
            'level' => $data['level'],
            'academic_term' => $data['academic_term'],
            'category' => $data['category'],
            'amount' => (float) $data['amount'],
            'due_date' => $data['due_date'],
            'effective_from' => $data['effective_from'],
            'created_by' => $officerId,
        ]);

        $this->audit->log($officerId, 'fee_structure_created', 'success', 'fee_structure', (string) $feeId, null, $data);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function revise(int $officerId, int $feeId, float $newAmount, string $newDueDate, ?int $institutionId = null): array
    {
        $existing = $this->fees->findById($feeId);
        if (!$existing || ($institutionId !== null && (int) $existing['institution_id'] !== $institutionId)) {
            return ['ok' => false, 'message' => 'Fee item not found.'];
        }
        if ($newAmount <= 0) {
            return ['ok' => false, 'message' => 'Amount must be greater than zero.'];
        }

        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $this->fees->closeAsOf($feeId, $yesterday);

        $newFeeId = $this->fees->create([
            'institution_id' => $existing['institution_id'],
            'program' => $existing['program'],
            'level' => $existing['level'],
            'academic_term' => $existing['academic_term'],
            'category' => $existing['category'],
            'amount' => $newAmount,
            'due_date' => $newDueDate,
            'effective_from' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'created_by' => $officerId,
        ]);

        $this->audit->log($officerId, 'fee_structure_revised', 'success', 'fee_structure', (string) $newFeeId, [
            'previous_fee_id' => $feeId,
            'previous_amount' => $existing['amount'],
        ], [
            'new_amount' => $newAmount,
            'new_due_date' => $newDueDate,
        ]);

        return ['ok' => true];
    }
}
