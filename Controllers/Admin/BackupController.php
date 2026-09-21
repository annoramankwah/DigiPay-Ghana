<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BackupService;

class BackupController
{
    public function index(): void
    {
        $service = new BackupService();

        Response::view('admin/backup', [
            'history' => $service->history(),
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/backup');
        }

        (new BackupService())->create((int) Session::get('user_id'));
        Response::redirect('/admin/backup');
    }
}
