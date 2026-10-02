<?php
declare(strict_types=1);

namespace CodeX\Contract\Router;
/**
 * Контракт для middleware, если используется объектно-ориентированный стиль.
 */
interface Middleware
{
    public function handle(mixed $request, callable $next, mixed ...$params): mixed;
}