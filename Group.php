<?php
declare(strict_types=1);

namespace CodeX\Router;

class Group
{
    private Route $router;
    private mixed $middlewareList;
    private ?string $prefix;

    public function __construct(
        Route $router,
        string|array|callable|null $middleware,
        ?string $prefix
    ) {
        $this->router = $router;
        $this->middlewareList = $middleware;
        $this->prefix = $prefix;
    }

    public function addMiddleware(string|array|callable $middleware): self
    {
        if ($this->middlewareList !== null) {
            $current = is_array($this->middlewareList) ? $this->middlewareList : [$this->middlewareList];
            $new = is_array($middleware) ? $middleware : [$middleware];
            $this->middlewareList = array_merge($current, $new);
        } else {
            $this->middlewareList = $middleware;
        }

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $normalized = '/' . trim($prefix, '/');
        if ($normalized === '/') {
            $normalized = '';
        }

        if ($this->prefix !== null) {
            $this->prefix = rtrim($this->prefix, '/') . $normalized;
        } else {
            $this->prefix = $normalized;
        }

        return $this;
    }

    public function group(callable $callback): void
    {
        $collector = $this->router->collector;
        $prefix = $this->prefix ?? '';

        $stackSizeBefore = $collector->getGroupStackSize();
        $collector->enterGroup($prefix, $this->middlewareList);

        // Конструкция try...finally гарантирует, что стек групп будет восстановлен
        // даже в случае выброса исключения внутри пользовательского callback.
        try {
            $callback($this->router);
        } finally {
            $collector->resetGroupStackTo($stackSizeBefore);
        }
    }
}