<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Config\Database;
use App\Core\Response;
use App\Support\InstitutionContext;

class SettingsController
{
    public function index(): void
    {
        $db = Database::connection();
        $institutionId = InstitutionContext::id();

        $categorySql = 'SELECT DISTINCT category FROM fee_structures';
        $termSql = "SELECT academic_term,
                    MIN(effective_from) AS starts,
                    MAX(COALESCE(effective_to, '9999-12-31')) AS ends,
                    MAX(CASE WHEN effective_to IS NULL OR effective_to >= CURDATE() THEN 1 ELSE 0 END) AS is_active
             FROM fee_structures";
        $params = [];
        if ($institutionId !== null) {
            $categorySql .= ' WHERE institution_id = ?';
            $termSql .= ' WHERE institution_id = ?';
            $params = [$institutionId];
        }
        $categorySql .= ' ORDER BY category';
        $termSql .= ' GROUP BY academic_term ORDER BY starts DESC';

        $stmt = $db->prepare($categorySql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $stmt = $db->prepare($termSql);
        $stmt->execute($params);
        $terms = $stmt->fetchAll();

        Response::view('admin/settings', [
            'categories' => $categories,
            'terms' => $terms,
        ]);
    }
}
