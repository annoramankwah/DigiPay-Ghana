<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeeStructureRepository;
use App\Repositories\TransactionRepository;

/**
 * Computes a student's live balance by combining the fee schedule in effect
 * for their program/level with whatever has already been verified or is
 * mid-flight (pending) against each fee item.
 */
class FeeService
{
    private FeeStructureRepository $fees;
    private TransactionRepository $transactions;

    public function __construct()
    {
        $this->fees = new FeeStructureRepository();
        $this->transactions = new TransactionRepository();
    }

    /**
     * @return array{items: array, totalDue: float, totalOutstanding: float}
     */
    public function balanceFor(array $student): array
    {
        $feeItems = $this->fees->findActiveForProgramLevel(
            (int) $student['institution_id'],
            (string) $student['program'],
            (string) $student['level']
        );

        $transactions = $this->transactions->findByStudentId((int) $student['student_id']);

        $paidByFee = [];
        $pendingByFee = [];
        foreach ($transactions as $txn) {
            $feeId = (int) $txn['fee_id'];
            if ($txn['status'] === 'verified') {
                $paidByFee[$feeId] = ($paidByFee[$feeId] ?? 0.0) + (float) $txn['amount_paid'];
            } elseif ($txn['status'] === 'pending') {
                $pendingByFee[$feeId] = ($pendingByFee[$feeId] ?? 0.0) + (float) $txn['amount_due'];
            }
        }

        $items = [];
        $totalDue = 0.0;
        $totalOutstanding = 0.0;

        foreach ($feeItems as $fee) {
            $feeId = (int) $fee['fee_id'];
            $amount = (float) $fee['amount'];
            $paid = $paidByFee[$feeId] ?? 0.0;
            $pending = $pendingByFee[$feeId] ?? 0.0;
            $remaining = max(0.0, $amount - $paid - $pending);

            // Order matters: a fee item can be fully covered by a pending
            // payment (remaining == 0) without being office-verified yet, so
            // "pending" must be checked before "paid".
            $status = 'unpaid';
            if ($pending > 0.0) {
                $status = 'pending';
            } elseif ($remaining <= 0.0) {
                $status = 'paid';
            }

            $items[] = [
                'fee_id' => $feeId,
                'category' => $fee['category'],
                'amount' => $amount,
                'paid' => $paid,
                'pending' => $pending,
                'remaining' => $remaining,
                'status' => $status,
                'due_date' => $fee['due_date'],
            ];

            $totalDue += $amount;
            $totalOutstanding += $remaining;
        }

        return [
            'items' => $items,
            'totalDue' => $totalDue,
            'totalOutstanding' => $totalOutstanding,
        ];
    }
}
