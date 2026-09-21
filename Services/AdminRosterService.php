<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRosterRepository;
use DateTimeImmutable;

class AdminRosterService
{
    private StudentRosterRepository $roster;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->roster = new StudentRosterRepository();
        $this->audit = new AuditLogger();
    }

    public function list(array $filters, ?int $institutionId = null): array
    {
        return $this->roster->findAllForAdmin($filters, $institutionId);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function addEntry(int $adminId, int $institutionId, array $data): array
    {
        $row = $this->normalizeRow($data);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Student number, full name, date of birth, program, and level are all required, with a valid date of birth.'];
        }
        if ($this->roster->isStudentNumberTaken($row['student_number'])) {
            return ['ok' => false, 'message' => 'That Student ID is already on the roster.'];
        }

        $rosterId = $this->roster->create([...$row, 'institution_id' => $institutionId]);
        $this->audit->log($adminId, 'roster_entry_added', 'success', 'student_roster', (string) $rosterId, null, [
            'student_number' => $row['student_number'],
        ]);

        return ['ok' => true];
    }

    /**
     * Expects a CSV with header row: student_number,full_name,date_of_birth,program,level
     *
     * @return array{ok: bool, message: string, imported?: int, skipped?: array<int,string>}
     */
    public function importCsv(int $adminId, int $institutionId, string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if ($lines === false || count($lines) < 2) {
            return ['ok' => false, 'message' => 'The CSV file is empty or missing a header row.'];
        }

        $header = array_map(static fn ($h) => strtolower(trim($h)), str_getcsv((string) array_shift($lines)));
        $expected = ['student_number', 'full_name', 'date_of_birth', 'program', 'level'];
        if ($header !== $expected) {
            return ['ok' => false, 'message' => 'CSV header must be exactly: ' . implode(',', $expected)];
        }

        $imported = 0;
        $skipped = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $rowNum = $i + 2;
            if (count($cols) < 5) {
                $skipped[] = "Row {$rowNum}: not enough columns.";
                continue;
            }

            $row = $this->normalizeRow(array_combine($expected, array_slice($cols, 0, 5)));
            if ($row === null) {
                $skipped[] = "Row {$rowNum}: invalid or missing fields.";
                continue;
            }
            if ($this->roster->isStudentNumberTaken($row['student_number'])) {
                $skipped[] = "Row {$rowNum}: Student ID {$row['student_number']} already on the roster.";
                continue;
            }

            $this->roster->create([...$row, 'institution_id' => $institutionId]);
            $imported++;
        }

        $this->audit->log($adminId, 'roster_csv_imported', 'success', 'student_roster', null, null, [
            'imported' => $imported,
            'skipped' => count($skipped),
        ]);

        return ['ok' => true, 'message' => "Imported {$imported} student(s).", 'imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function deleteEntry(int $adminId, int $rosterId, ?int $institutionId = null): array
    {
        if (!$this->roster->deleteUnclaimed($rosterId, $institutionId)) {
            return ['ok' => false, 'message' => 'That roster entry was not found, or has already been claimed by a student account.'];
        }

        $this->audit->log($adminId, 'roster_entry_deleted', 'success', 'student_roster', (string) $rosterId);

        return ['ok' => true];
    }

    /**
     * @return array{student_number:string,full_name:string,date_of_birth:string,program:string,level:string}|null
     */
    private function normalizeRow(array $data): ?array
    {
        $studentNumber = trim((string) ($data['student_number'] ?? ''));
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $dob = trim((string) ($data['date_of_birth'] ?? ''));
        $program = trim((string) ($data['program'] ?? ''));
        $level = trim((string) ($data['level'] ?? ''));

        if ($studentNumber === '' || $fullName === '' || $dob === '' || $program === '' || $level === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob) {
            return null;
        }

        return [
            'student_number' => $studentNumber,
            'full_name' => $fullName,
            'date_of_birth' => $dob,
            'program' => $program,
            'level' => $level,
        ];
    }
}
