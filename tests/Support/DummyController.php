<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Request;
use App\Core\Response;

final class DummyController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        return Response::html('index');
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        return Response::html('id=' . ($params['id'] ?? 'none'));
    }
}
