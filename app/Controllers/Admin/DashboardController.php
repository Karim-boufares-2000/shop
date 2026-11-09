<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

final class DashboardController
{
    public function __construct(private AdminPage $page)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return $this->page->render('admin/dashboard', ['title' => $this->page->t('admin.dashboard.title')]);
    }
}
