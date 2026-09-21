<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Config\Database;

$pdo = Database::connection();

/**
 * @param array{short_name?:string,email?:string,phone?:string,address?:string,website?:string} $details
 */
function upsertInstitution(PDO $pdo, string $name, array $details): int
{
    $stmt = $pdo->prepare('SELECT institution_id FROM institutions WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        // Backfill any details a prior seed run left empty, without
        // clobbering anything a super admin has since edited by hand.
        $stmt = $pdo->prepare(
            'UPDATE institutions SET
                short_name = COALESCE(short_name, ?),
                email = COALESCE(email, ?),
                phone = COALESCE(phone, ?),
                address = COALESCE(address, ?),
                website = COALESCE(website, ?)
             WHERE institution_id = ?'
        );
        $stmt->execute([
            $details['short_name'] ?? null,
            $details['email'] ?? null,
            $details['phone'] ?? null,
            $details['address'] ?? null,
            $details['website'] ?? null,
            $id,
        ]);
        return (int) $id;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO institutions (name, short_name, email, phone, address, website, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $details['short_name'] ?? null,
        $details['email'] ?? null,
        $details['phone'] ?? null,
        $details['address'] ?? null,
        $details['website'] ?? null,
        'active',
    ]);
    return (int) $pdo->lastInsertId();
}

function upsertUser(PDO $pdo, array $u): int
{
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE login_id = ? LIMIT 1');
    $stmt->execute([$u['login_id']]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (login_id, name, email, role, institution_id, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $u['login_id'],
        $u['name'],
        $u['email'],
        $u['role'],
        $u['institution_id'] ?? null,
        password_hash($u['password'], PASSWORD_BCRYPT),
        'active',
    ]);
    return (int) $pdo->lastInsertId();
}

$demoPassword = 'DigiPay@2026';

$schools = [
    ['code' => 'GCTU', 'name' => 'Ghana Communication Technology University', 'domain' => 'gctu.edu.gh', 'phone' => '0302 401 681', 'address' => 'PMB 100, Tesano, Accra'],
    ['code' => 'UG', 'name' => 'University of Ghana', 'domain' => 'ug.edu.gh', 'phone' => '0302 213 820', 'address' => 'P.O. Box LG 25, Legon, Accra'],
    ['code' => 'KNUST', 'name' => 'Kwame Nkrumah University of Science and Technology', 'domain' => 'knust.edu.gh', 'phone' => '0322 060 331', 'address' => 'Private Mail Bag, Kumasi'],
    ['code' => 'UCC', 'name' => 'University of Cape Coast', 'domain' => 'ucc.edu.gh', 'phone' => '0332 132 480', 'address' => 'P.O. Box UC, Cape Coast'],
    ['code' => 'UEW', 'name' => 'University of Education, Winneba', 'domain' => 'uew.edu.gh', 'phone' => '0332 091 855', 'address' => 'P.O. Box 25, Winneba'],
    ['code' => 'UDS', 'name' => 'University for Development Studies', 'domain' => 'uds.edu.gh', 'phone' => '0372 022 191', 'address' => 'P.O. Box TL 1350, Tamale'],
    ['code' => 'HTU', 'name' => 'Ho Technical University', 'domain' => 'htu.edu.gh', 'phone' => '0362 026 631', 'address' => 'P.O. Box HP 217, Ho'],
];

$firstNames = ['Kwame', 'Kofi', 'Kwabena', 'Kwaku', 'Yaw', 'Kwadwo', 'Ama', 'Abena', 'Akosua', 'Yaa', 'Afia', 'Adwoa', 'Kojo', 'Nana', 'Efua', 'Esi', 'Akua', 'Kobina', 'Araba', 'Adjoa'];
$lastNames = ['Asante', 'Mensah', 'Owusu', 'Boateng', 'Osei', 'Appiah', 'Tetteh', 'Addo', 'Amoah', 'Agyemang', 'Darko', 'Sarpong', 'Ofori', 'Yeboah', 'Frimpong', 'Adjei', 'Antwi', 'Gyasi', 'Nkrumah', 'Quaye'];
$programs = [
    ['program' => 'BSc Computer Science', 'level' => '100'],
    ['program' => 'BSc Computer Science', 'level' => '200'],
    ['program' => 'BSc Information Technology', 'level' => '300'],
    ['program' => 'BSc Accounting', 'level' => '100'],
    ['program' => 'BA Economics', 'level' => '200'],
];
$feeItems = [
    ['category' => 'Tuition', 'amount' => 3200.00],
    ['category' => 'Library & ICT Levy', 'amount' => 950.00],
    ['category' => 'SRC Dues', 'amount' => 200.00],
    ['category' => 'Examination Fee', 'amount' => 500.00],
];

$rosterStmt = $pdo->prepare('SELECT roster_id FROM student_roster WHERE student_number = ? LIMIT 1');
$rosterInsert = $pdo->prepare(
    'INSERT INTO student_roster (institution_id, student_number, full_name, date_of_birth, program, level)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$feeCountStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM fee_structures WHERE institution_id = ? AND program = ? AND level = ? AND academic_term = ?'
);
$feeInsert = $pdo->prepare(
    'INSERT INTO fee_structures
        (institution_id, program, level, academic_term, category, amount, currency, due_date, effective_from, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$summary = [];

foreach ($schools as $schoolIndex => $school) {
    $institutionId = upsertInstitution($pdo, $school['name'], [
        'short_name' => $school['code'],
        'email' => 'info@' . $school['domain'],
        'phone' => $school['phone'],
        'address' => $school['address'],
        'website' => 'https://www.' . $school['domain'],
    ]);

    $adminId = upsertUser($pdo, [
        'login_id' => $school['code'] . '-ADM-0001',
        'name' => $firstNames[$schoolIndex] . ' ' . $lastNames[$schoolIndex],
        'email' => 'admin@' . $school['domain'],
        'role' => 'admin',
        'institution_id' => $institutionId,
        'password' => $demoPassword,
    ]);

    upsertUser($pdo, [
        'login_id' => $school['code'] . '-STF-0001',
        'name' => $firstNames[$schoolIndex + 1] . ' ' . $lastNames[$schoolIndex + 1],
        'email' => 'accounts@' . $school['domain'],
        'role' => 'account_office',
        'institution_id' => $institutionId,
        'password' => $demoPassword,
    ]);

    $demoStudentUserId = upsertUser($pdo, [
        'login_id' => $school['code'] . '-STU-0001',
        'name' => $firstNames[$schoolIndex + 2] . ' ' . $lastNames[$schoolIndex + 2],
        'email' => 'student@' . $school['domain'],
        'role' => 'student',
        'institution_id' => $institutionId,
        'password' => $demoPassword,
    ]);

    $stmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$demoStudentUserId]);
    if (!$stmt->fetchColumn()) {
        $stmt = $pdo->prepare(
            'INSERT INTO students (user_id, institution_id, program, level, contact_info) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$demoStudentUserId, $institutionId, 'BSc Computer Science', '300', '+233 24 100 0000']);
    }

    // 20 unclaimed roster rows per school, for exercising /signup.
    $rosterCount = 0;
    for ($i = 1; $i <= 20; $i++) {
        $first = $firstNames[($schoolIndex * 7 + $i) % count($firstNames)];
        $last = $lastNames[($schoolIndex * 11 + $i * 3) % count($lastNames)];
        $prog = $programs[($schoolIndex + $i) % count($programs)];
        $studentNumber = sprintf('%s/2024/%04d', $school['code'], 2000 + $i);
        $dob = sprintf('200%d-%02d-%02d', ($i % 6) + 1, (($i * 3) % 12) + 1, (($i * 7) % 28) + 1);

        $rosterStmt->execute([$studentNumber]);
        if ($rosterStmt->fetchColumn()) {
            continue;
        }
        $rosterInsert->execute([
            $institutionId,
            $studentNumber,
            $first . ' ' . $last,
            $dob,
            $prog['program'],
            $prog['level'],
        ]);
        $rosterCount++;
    }

    $feeCountStmt->execute([$institutionId, 'BSc Computer Science', '300', '2026/2027 Semester 1']);
    if ((int) $feeCountStmt->fetchColumn() === 0) {
        foreach ($feeItems as $item) {
            $feeInsert->execute([
                $institutionId,
                'BSc Computer Science',
                '300',
                '2026/2027 Semester 1',
                $item['category'],
                $item['amount'],
                'GHS',
                '2026-10-15',
                '2026-09-01',
                $adminId,
            ]);
        }
    }

    $summary[] = [
        'school' => $school['name'],
        'admin' => $school['code'] . '-ADM-0001',
        'office' => $school['code'] . '-STF-0001',
        'student' => $school['code'] . '-STU-0001',
        'roster_added' => $rosterCount,
    ];
}

$superAdminLoginId = 'SUPER/0001';
upsertUser($pdo, [
    'login_id' => $superAdminLoginId,
    'name' => 'Frederick (Platform Super Admin)',
    'email' => 'fredericknti22@gmail.com',
    'role' => 'super_admin',
    'institution_id' => null,
    'password' => $demoPassword,
]);

echo "Seed complete.\n";
echo "Demo credentials (same password for every account): {$demoPassword}\n\n";
echo "Super Admin: {$superAdminLoginId} (fredericknti22@gmail.com) — change this password after first login.\n\n";
foreach ($summary as $row) {
    echo "{$row['school']}:\n";
    echo "  Admin:   {$row['admin']}\n";
    echo "  Office:  {$row['office']}\n";
    echo "  Student: {$row['student']}\n";
    echo "  Roster:  {$row['roster_added']} new unclaimed entries (for /signup testing)\n\n";
}
