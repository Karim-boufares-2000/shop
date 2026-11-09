<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\UrlGenerator;
use App\Core\View;
use App\Enums\LoginOutcome;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private View $view, private AuthService $auth, private UrlGenerator $urls)
    {
    }

    /** @param array<string,string> $params */
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->auth->currentAdmin() !== null) {
            return Response::redirect($this->urls->to('/admin'), 303);
        }

        return $this->form(null, '', 200);
    }

    /** @param array<string,string> $params */
    public function login(Request $request, array $params): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');
        $username = is_string($username) ? $username : '';
        $password = is_string($password) ? $password : '';

        $outcome = $this->auth->attempt($username, $password, $request->ip());
        if ($outcome === LoginOutcome::Success) {
            return Response::redirect($this->urls->to('/admin'), 303);
        }
        if ($outcome === LoginOutcome::Throttled) {
            return $this->form('auth.throttled', $username, 429);
        }

        return $this->form('auth.login_failed', $username, 401); // identique pour inconnu / faux / verrouillé
    }

    /** @param array<string,string> $params */
    public function logout(Request $request, array $params): Response
    {
        $this->auth->logout($request->ip());

        return Response::redirect($this->urls->to('/admin/login'), 303);
    }

    private function form(?string $errorKey, string $username, int $status): Response
    {
        return Response::html($this->view->render('admin/login', [
            'title' => $this->view->t('admin.login.title'),
            'error' => $errorKey,
            'username' => mb_substr($username, 0, 50),
        ], 'layouts/admin'), $status)->withHeader('Cache-Control', 'no-store');
    }
}
