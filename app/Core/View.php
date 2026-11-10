<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rendu des gabarits PHP. Les gabarits sont exécutés avec $this = View :
 * ils utilisent $this->e() pour échapper TOUTE donnée affichée.
 */
final class View
{
    /** @var array<string,callable> */
    private array $providers = [];
    /** @var array<string,mixed> */
    private array $resolved = [];

    public function __construct(
        private string $viewsPath,
        private UrlGenerator $urls,
        private Translator $translator,
        private Csrf $csrf,
        private Request $request,
    ) {
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->capture($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->capture($layout, array_merge($data, ['content' => $content]));
    }

    /** Nom/description dans la langue courante, avec repli sur le français. @param array<string,mixed> $row */
    public function localized(array $row, string $field): string
    {
        if ($this->translator->locale() === 'ar') {
            $arabic = $row[$field . '_ar'] ?? null;
            if (is_string($arabic) && $arabic !== '') {
                return $arabic;
            }
        }

        return (string) ($row[$field . '_fr'] ?? '');
    }

    /** Texte brut -> HTML sûr (échappé, retours à la ligne conservés). */
    public function text(string $value): string
    {
        return nl2br($this->e($value), false);
    }

    /** Pourcentage de réduction entre deux montants normalisés (entiers, sans float monétaire). */
    public function discountPercent(string $price, string $promo): int
    {
        $full = \App\Support\Money::toCents($price);

        return $full > 0 ? (int) round((1 - \App\Support\Money::toCents($promo) / $full) * 100) : 0;
    }

    /** @param array<string,scalar> $query */
    public function absoluteUrl(string $path = '/', array $query = []): string
    {
        return $this->urls->absolute($path, $query);
    }

    public function icon(string $name): string
    {
        return $this->partial('partials/icon', ['name' => $name]);
    }

    /** Donnée partagée avec les gabarits, calculée à la demande (une seule fois par requête). */
    public function share(string $key, callable $provider): void
    {
        $this->providers[$key] = $provider;
    }

    public function shared(string $key): mixed
    {
        if (!array_key_exists($key, $this->resolved)) {
            $provider = $this->providers[$key] ?? null;
            $this->resolved[$key] = $provider === null ? null : $provider();
        }

        return $this->resolved[$key];
    }

    /** @param array<string,mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture($template, $data);
    }

    /** Échappement HTML (contexte texte et attributs entre guillemets). */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string,scalar> $query */
    public function url(string $path = '/', array $query = []): string
    {
        return $this->urls->to($path, $query);
    }

    /** URL publique d'une image produit (identifiant de 32 caractères hexadécimaux). */
    public function mediaUrl(string $fileId, bool $thumbnail = false): string
    {
        return $this->urls->to('media/products/' . $fileId . ($thumbnail ? '_t' : '') . '.jpg');
    }

    /** Montant normalisé -> "1 500,50" (sans devise). */
    public function money(string $amount): string
    {
        return \App\Support\Money::format($amount);
    }

    public function asset(string $path): string
    {
        return $this->urls->asset($path);
    }

    /** @param array<string,scalar> $params */
    public function t(string $key, array $params = []): string
    {
        return $this->translator->get($key, $params);
    }

    public function locale(): string
    {
        return $this->translator->locale();
    }

    public function direction(): string
    {
        return $this->translator->direction();
    }

    public function currentPath(): string
    {
        return $this->request->path();
    }

    /** HTML de confiance : le jeton est hexadécimal, l'attribut est échappé par précaution. */
    public function csrfField(): string
    {
        return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . $this->e($this->csrf->token()) . '">';
    }

    /** @param array<string,mixed> $data */
    private function capture(string $template, array $data): string
    {
        if (preg_match('#^[a-zA-Z0-9_/-]+$#', $template) !== 1 || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Nom de gabarit invalide.');
        }
        $file = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Gabarit introuvable : {$template}");
        }

        ob_start();
        try {
            (function () use ($file, $data): void {
                extract($data, EXTR_SKIP);
                require $file;
            })->call($this);

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
