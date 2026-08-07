<?php
declare(strict_types=1);

namespace CodeX\Router\Middleware;
final readonly class Definition
{
    public function __construct(public mixed $handler, public array $params = [])
    {
    }
}