<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\SessionInterface;
use App\Core\UrlGenerator;
use App\Core\View;
use App\Services\AuthService;

final class AccountController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private SessionInterface $session,
        private UrlGenerator $urls,
    ) {
    }

    /** @param array<string,string> $params */
    public function form(Request $request, array $params): Response
    {
        return $this->render(null, 200);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->auth->currentAdmin();
        if ($admin === null) {
            return Response::redirect($this->urls->to('/admin/login'), 303);
        }

        $current = $request->post('current_password');
        $new = $request->post('new_password');
        $confirm = $request->post('new_password_confirmation');
        $current = is_string($current) ? $current : '';
        $new = is_string($new) ? $new : '';
        $confirm = is_string($confirm) ? $confirm : '';

        $error = $new !== $confirm
            ? 'auth.password_mismatch'
            : $this->auth->changePassword((int) $admin['id'], $current, $new, $request->ip());

        if ($error !== null) {
            return $this->render($error, $error === 'auth.throttled' ? 429 : 422);
        }
        $this->session->set('flash_success', 'admin.password_changed');

        return Response::redirect($this->urls->to('/admin'), 303);
    }

    private function render(?string $errorKey, int $status): Response
    {
        $admin = $this->auth->currentAdmin();

        return Response::html($this->view->render('admin/password', [
            'title' => $this->view->t('admin.change_password.title'),
            'admin' => $admin,
            'error' => $errorKey,
            'forced' => $admin !== null && $admin['must_change_password'] === true,
        ], 'layouts/admin'), $status);
    }
}
