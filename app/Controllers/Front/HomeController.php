<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function __construct(private View $view)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return Response::html($this->view->render('front/home', [
            'title' => $this->view->t('app.name'),
        ]));
    }
}
