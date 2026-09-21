<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TransactionRepository;

class ReportService
{
    private TransactionRepository $transactions;

    public function __construct()
    {
        $this->transactions = new TransactionRepository();
    }

    public function summary(string $from, string $to, ?int $institutionId = null): array
    {
        return $this->transactions->summaryBetween($from, $to, $institutionId);
    }

    /**
     * Streams a CSV of every transaction in the date range directly to the
     * client — no temp file, matches the "export in under 5 seconds" target
     * for reasonable data volumes.
     */
    public function streamCsv(string $from, string $to, ?int $institutionId = null): void
    {
        $rows = $this->transactions->listBetweenForExport($from, $to, $institutionId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="digipay-report-' . $from . '_to_' . $to . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Transaction ID', 'Date', 'Student', 'Student ID', 'Category', 'Amount Due', 'Amount Paid', 'Status', 'Method']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['transaction_id'],
                $row['timestamp'],
                $row['student_name'],
                $row['student_login_id'],
                $row['category'],
                $row['amount_due'],
                $row['amount_paid'],
                $row['status'],
                $row['payment_method'],
            ]);
        }
        fclose($out);
    }
}
