<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\UrlGenerator;
use App\Core\View;
use App\Services\StorefrontService;

final class HomeController
{
    public function __construct(private View $view, private StorefrontService $storefront, private UrlGenerator $urls)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return Response::html($this->view->render('public/home', $this->storefront->home() + [
            'seo' => [
                'description' => $this->view->t('seo.home_description'),
                'canonical' => $this->urls->absolute('/'),
                'type' => 'website',
            ],
        ]));
    }
}
