<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\SessionInterface;
use App\Core\UrlGenerator;
use App\Core\View;
use App\Services\AuthService;

/** Aide commune aux contrôleurs d'administration : rendu avec mise en page, messages flash, redirections. */
final class AdminPage
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private SessionInterface $session,
        private UrlGenerator $urls,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $flash = $this->session->pull('flash_success');
        if (is_string($flash)) {
            $flash = ['key' => $flash, 'params' => [], 'type' => 'success'];
        }
        $data['admin'] = $this->auth->currentAdmin();
        $data['flash'] = is_array($flash) ? $flash : null;

        return Response::html($this->view->render($template, $data, 'layouts/admin'), $status);
    }

    /** @param array<string,scalar> $params */
    public function flash(string $key, array $params = []): void
    {
        $this->session->set('flash_success', ['key' => $key, 'params' => $params, 'type' => 'success']);
    }

    /** Message d'erreur déjà traduit (ex. messages de validation). */
    public function flashError(string $text): void
    {
        $this->session->set('flash_success', ['text' => $text, 'type' => 'error']);
    }

    public function redirect(string $path): Response
    {
        return Response::redirect($this->urls->to($path), 303);
    }

    public function adminId(): int
    {
        return (int) ($this->auth->currentAdmin()['id'] ?? 0);
    }

    public function t(string $key): string
    {
        return $this->view->t($key);
    }

    /**
     * Valeurs de formulaire à réafficher : uniquement des chaînes, uniquement les champs attendus.
     *
     * @param list<string> $keys
     * @return array<string,string>
     */
    public function formValues(Request $request, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $request->post($key);
            $values[$key] = is_string($value) ? $value : '';
        }

        return $values;
    }

    public function queryString(Request $request, string $key, int $maxLength = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $maxLength) : '';
    }
}
