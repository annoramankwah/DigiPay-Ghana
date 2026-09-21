<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\StaffProfileService;

class ProfileController
{
    public function show(): void
    {
        $user = (new UserRepository())->findById((int) Session::get('user_id'));

        Response::view('admin/profile', [
            'user' => $user,
            'error' => Session::flash('profile_error'),
            'saved' => Session::flash('profile_saved') === '1',
            'csrf' => Csrf::token(),
        ]);
    }

    public function update(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/profile');
        }

        $userId = (int) Session::get('user_id');
        $service = new StaffProfileService();

        $name = (string) Request::input('name', '');
        $result = $service->update($userId, $name, (string) Request::input('email', ''));

        if ($result['ok'] && Request::input('new_password') !== null && (string) Request::input('new_password', '') !== '') {
            $result = $service->changePassword(
                $userId,
                (string) Request::input('current_password', ''),
                (string) Request::input('new_password', '')
            );
        }

        if (!$result['ok']) {
            Session::flash('profile_error', $result['message']);
            Response::redirect('/admin/profile');
        }

        Session::set('name', $name);
        Session::flash('profile_saved', '1');
        Response::redirect('/admin/profile');
    }
}
