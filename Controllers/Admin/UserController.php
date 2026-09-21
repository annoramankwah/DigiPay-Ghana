<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\AdminUserService;
use App\Support\InstitutionContext;

class UserController
{
    public function index(): void
    {
        $filters = array_filter([
            'role' => (string) Request::input('role', ''),
            'status' => (string) Request::input('status', ''),
            'search' => trim((string) Request::input('search', '')),
        ]);

        $users = (new AdminUserService())->list($filters, InstitutionContext::id());

        Response::view('admin/users', [
            'users' => $users,
            'filters' => $filters,
            'error' => Session::flash('user_error'),
            'saved' => Session::flash('user_saved') === '1',
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        Response::view('admin/user_form', [
            'user' => null,
            'error' => Session::flash('user_error'),
            'csrf' => Csrf::token(),
            'isSuperAdmin' => InstitutionContext::isSuperAdmin(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/users');
        }

        $institutionId = InstitutionContext::id();
        if ($institutionId === null && !InstitutionContext::isSuperAdmin()) {
            Session::flash('user_error', 'Select a school to manage before creating users.');
            Response::redirect('/admin/users/new');
        }

        $result = (new AdminUserService())->create(
            (int) Session::get('user_id'),
            $institutionId,
            [
                'login_id' => Request::input('login_id', ''),
                'name' => Request::input('name', ''),
                'email' => Request::input('email', ''),
                'role' => Request::input('role', ''),
                'password' => Request::input('password', ''),
                'program' => Request::input('program', ''),
                'level' => Request::input('level', ''),
            ],
            InstitutionContext::isSuperAdmin()
        );

        if (!$result['ok']) {
            Session::flash('user_error', $result['message']);
            Response::redirect('/admin/users/new');
        }

        Session::flash('user_saved', '1');
        Response::redirect('/admin/users');
    }

    public function edit(array $params): void
    {
        $user = (new UserRepository())->findByIdIncludingDeleted((int) $params['userId']);
        if (!$user) {
            http_response_code(404);
            Response::view('errors/404');
            return;
        }

        Response::view('admin/user_form', [
            'user' => $user,
            'error' => Session::flash('user_error'),
            'csrf' => Csrf::token(),
            'isSuperAdmin' => InstitutionContext::isSuperAdmin(),
        ]);
    }

    public function update(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/users');
        }

        $userId = (int) $params['userId'];

        $result = (new AdminUserService())->update(
            (int) Session::get('user_id'),
            $userId,
            InstitutionContext::id(),
            [
                'name' => Request::input('name', ''),
                'email' => Request::input('email', ''),
                'role' => Request::input('role', ''),
                'program' => Request::input('program', ''),
                'level' => Request::input('level', ''),
            ],
            InstitutionContext::isSuperAdmin()
        );

        if (!$result['ok']) {
            Session::flash('user_error', $result['message']);
            Response::redirect('/admin/users/' . $userId . '/edit');
        }

        Session::flash('user_saved', '1');
        Response::redirect('/admin/users');
    }

    public function setStatus(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/users');
        }

        $result = (new AdminUserService())->setStatus(
            (int) Session::get('user_id'),
            (int) $params['userId'],
            (string) Request::input('status', ''),
            InstitutionContext::id(),
            InstitutionContext::isSuperAdmin()
        );

        if (!$result['ok']) {
            Session::flash('user_error', $result['message']);
        } else {
            Session::flash('user_saved', '1');
        }

        Response::redirect('/admin/users');
    }

    public function delete(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/users');
        }

        $result = (new AdminUserService())->delete(
            (int) Session::get('user_id'),
            (int) $params['userId'],
            InstitutionContext::id(),
            InstitutionContext::isSuperAdmin()
        );

        if (!$result['ok']) {
            Session::flash('user_error', $result['message']);
        } else {
            Session::flash('user_saved', '1');
        }

        Response::redirect('/admin/users');
    }

    public function restore(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/users');
        }

        $result = (new AdminUserService())->restore(
            (int) Session::get('user_id'),
            (int) $params['userId'],
            InstitutionContext::id(),
            InstitutionContext::isSuperAdmin()
        );

        if (!$result['ok']) {
            Session::flash('user_error', $result['message']);
        } else {
            Session::flash('user_saved', '1');
        }

        Response::redirect('/admin/users');
    }
}
