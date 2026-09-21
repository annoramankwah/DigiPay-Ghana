<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

class NotificationService
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function notify(int $institutionId, int $userId, string $message, string $type): void
    {
        $this->notifications->create($institutionId, $userId, $message, $type);
    }
}
