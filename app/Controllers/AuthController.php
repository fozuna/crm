<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserRepository;

final class AuthController
{
    public function showLogin(Request $request): void
    {
        View::render('auth/login', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
        ], null);
    }

    public function login(Request $request): void
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            View::render('auth/login', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'error' => 'Credenciais inválidas.',
            ], null);
            return;
        }

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            View::render('auth/login', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'error' => 'Credenciais inválidas.',
            ], null);
            return;
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_name', (string) $user['name']);
        Session::set('is_admin', (int) ($user['is_admin'] ?? 0));
        $role = trim((string) ($user['role'] ?? ''));
        if ((int) ($user['is_admin'] ?? 0) === 1) {
            $role = 'admin';
        }
        if ($role === '') {
            $role = 'pm';
        }
        Session::set('user_role', $role);
        Response::redirect($request->basePath() . '/dashboard');
    }

    public function logout(Request $request): void
    {
        Session::remove('user_id');
        Session::remove('user_name');
        Session::remove('is_admin');
        Session::remove('user_role');
        Session::regenerate();
        Response::redirect($request->basePath() . '/login');
    }
}
