<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Router\Middleware\Definition;
use CodeX\Router\Middleware\Normalizer;

/**
 * Группа маршрутов.
 *
 * Поддерживает:
 * - префиксы пути;
 * - middleware;
 * - префиксы имён маршрутов.
 */
final class Group
{
    /**
     * @var array<int, Definition>
     */
    private array $middleware;

    private ?string $prefix;

    private string $namePrefix;

    public function __construct(
        private readonly Route $router,
        mixed $middleware,
        ?string $prefix,
        string $namePrefix = ''
    ) {
        $this->middleware = Normalizer::normalize($middleware);
        $this->prefix = $prefix !== null ? self::normalizePrefix($prefix) : null;
        $this->namePrefix = $namePrefix;
    }

    public function addMiddleware(mixed $middleware): self
    {
        $this->middleware = array_merge(
            $this->middleware,
            Normalizer::normalize($middleware)
        );

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $normalized = self::normalizePrefix($prefix);

        if ($normalized === '') {
            return $this;
        }

        if ($this->prefix === null) {
            $this->prefix = $normalized;
        } else {
            $this->prefix = rtrim($this->prefix, '/') . $normalized;
        }

        return $this;
    }

    public function namePrefix(string $namePrefix): self
    {
        $this->namePrefix .= $namePrefix;

        return $this;
    }

    public function group(callable $callback): void
    {
        $collector = $this->router->collector;
        $stackSizeBefore = $collector->getGroupStackSize();

        $collector->enterGroup(
            $this->prefix ?? '',
            $this->middleware,
            $this->namePrefix
        );

        // try/finally гарантирует корректный откат стека групп
        // даже при возникновении исключения внутри callback.
        try {
            $callback($this->router);
        } finally {
            $collector->resetGroupStackTo($stackSizeBefore);
        }
    }

    private static function normalizePrefix(string $prefix): string
    {
        $normalized = '/' . trim($prefix, '/');

        return $normalized === '/' ? '' : $normalized;
    }
}
