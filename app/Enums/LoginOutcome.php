<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginOutcome: string
{
    case Success = 'success';
    case Invalid = 'invalid';     // identifiants faux (ou inconnus : même résultat par conception)
    case Locked = 'locked';       // compte verrouillé temporairement
    case Throttled = 'throttled'; // trop de tentatives
}
