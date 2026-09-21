<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\PasswordResetService;

class AuthController
{
    public function showLogin(): void
    {
        if (Session::has('user_id')) {
            self::redirectToDashboard();
            return;
        }
        Response::view('auth/login', [
            'error' => Session::flash('error'),
            'notice' => Session::flash('login_notice'),
            'expired' => Request::input('expired') === '1',
            'csrf' => Csrf::token(),
        ]);
    }

    public function login(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Session::flash('error', 'Your form session expired. Please try again.');
            Response::redirect('/login');
        }

        $loginId = trim((string) Request::input('login_id', ''));
        $password = (string) Request::input('password', '');

        if ($loginId === '' || $password === '') {
            Session::flash('error', 'Please enter your ID and password.');
            Response::redirect('/login');
        }

        $result = (new AuthService())->attempt($loginId, $password);

        if (!$result['ok']) {
            Session::flash('error', $result['message']);
            Response::redirect('/login');
        }

        self::redirectToDashboard();
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        Response::redirect('/login');
    }

    public static function redirectToDashboard(): never
    {
        $map = [
            'student' => '/student',
            'account_office' => '/office',
            'admin' => '/admin',
            'super_admin' => '/super-admin',
        ];
        $role = Session::get('role');
        Response::redirect($map[$role] ?? '/login');
    }

    public function showPasswordResetRequest(): void
    {
        Response::view('auth/password_reset_request', [
            'sent' => Session::flash('reset_requested') === '1',
            'csrf' => Csrf::token(),
        ]);
    }

    public function submitPasswordResetRequest(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/password-reset');
        }

        $loginId = trim((string) Request::input('login_id', ''));
        $email = trim((string) Request::input('email', ''));

        if ($loginId !== '' && $email !== '') {
            (new PasswordResetService())->requestReset($loginId, $email);
        }

        Session::flash('reset_requested', '1');
        Response::redirect('/password-reset');
    }

    public function showPasswordResetForm(array $params): void
    {
        $service = new PasswordResetService();
        $user = $service->validateToken($params['token']);

        if (!$user) {
            Response::view('auth/password_reset_invalid');
            return;
        }

        Response::view('auth/password_reset_form', [
            'token' => $params['token'],
            'error' => Session::flash('error'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function submitPasswordResetForm(array $params): void
    {
        $token = $params['token'];

        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/password-reset/' . $token);
        }

        $password = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');

        if (strlen($password) < 8 || $password !== $confirm) {
            Session::flash('error', 'Passwords must match and be at least 8 characters.');
            Response::redirect('/password-reset/' . $token);
        }

        $ok = (new PasswordResetService())->resetPassword($token, $password);

        if (!$ok) {
            Response::view('auth/password_reset_invalid');
            return;
        }

        Session::flash('login_notice', 'Your password has been updated. Please sign in.');
        Response::redirect('/login');
    }
}
