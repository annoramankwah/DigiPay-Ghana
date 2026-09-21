<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Services\AdminAnalyticsService;
use App\Support\InstitutionContext;

class OverviewController
{
    public function index(): void
    {
        $institutionId = InstitutionContext::id();
        $overview = (new AdminAnalyticsService())->overview($institutionId);
        $recentActivity = (new AuditLogRepository())->findAll([], 8, $institutionId);

        Response::view('admin/overview', [
            'overview' => $overview,
            'recentActivity' => $recentActivity,
        ]);
    }
}
