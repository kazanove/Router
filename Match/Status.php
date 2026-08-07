<?php
declare(strict_types=1);

namespace CodeX\Router\Match;

/**
 * Статус результата сопоставления маршрута.
 *
 * Ранее статус определялся косвенно по null и массиву разрешённых методов.
 * Явный статус делает API более прозрачным и удобным для диспетчеризации.
 */
enum Status: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
}