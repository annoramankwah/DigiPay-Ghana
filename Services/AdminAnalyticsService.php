<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Repositories\UserRepository;
use DateTimeImmutable;

class AdminAnalyticsService
{
    public function overview(?int $institutionId = null): array
    {
        $db = Database::connection();

        $totalCollectionsSql = "SELECT COALESCE(SUM(amount_paid), 0) FROM transactions WHERE status = 'verified'";
        $pendingCountSql = "SELECT COUNT(*) FROM transactions WHERE status = 'pending'";
        $params = [];
        if ($institutionId !== null) {
            $totalCollectionsSql .= ' AND institution_id = ?';
            $pendingCountSql .= ' AND institution_id = ?';
            $params = [$institutionId];
        }

        $stmt = $db->prepare($totalCollectionsSql);
        $stmt->execute($params);
        $totalCollections = (float) $stmt->fetchColumn();

        $stmt = $db->prepare($pendingCountSql);
        $stmt->execute($params);
        $pendingCount = (int) $stmt->fetchColumn();

        $userCounts = (new UserRepository())->countActiveByRole($institutionId);

        $chart = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = new DateTimeImmutable("first day of -{$i} months");
            $start = $month->format('Y-m-01');
            $end = $month->format('Y-m-t');

            $sql = "SELECT COALESCE(SUM(amount_paid), 0) FROM transactions
                 WHERE status = 'verified' AND DATE(timestamp) BETWEEN ? AND ?";
            $chartParams = [$start, $end];
            if ($institutionId !== null) {
                $sql .= ' AND institution_id = ?';
                $chartParams[] = $institutionId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($chartParams);
            $chart[] = ['month' => $month->format('M'), 'total' => (float) $stmt->fetchColumn()];
        }

        $maxTotal = max(array_column($chart, 'total')) ?: 1.0;
        foreach ($chart as &$point) {
            $point['pct'] = (int) round(($point['total'] / $maxTotal) * 100);
        }
        unset($point);

        return [
            'totalCollections' => $totalCollections,
            'activeUsers' => array_sum($userCounts),
            'userCounts' => $userCounts,
            'pendingCount' => $pendingCount,
            'chart' => $chart,
        ];
    }
}
