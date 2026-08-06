<?php
declare(strict_types=1);

namespace CodeX\Router;

use Closure;
use CodeX\Exception\Router;

class Collector
{
    /**
     * Property Hooks (PHP 8.4): Предоставляем публичный доступ на чтение к корневому узлу,
     * скрывая внутреннюю реализацию ($rootNode).
     */
    public Node $root {
        get => $this->rootNode;
    }

    private array $groupStack = [];
    private Node $rootNode;

    public function __construct()
    {
        $this->rootNode = new Node();
    }

    public function enterGroup(string $prefix, string|array|callable|null $middleware): void
    {
        $prefix = '/' . trim($prefix, '/');
        if ($prefix === '/') {
            $prefix = '';
        }

        $mwList = [];
        if ($middleware !== null) {
            $mwList = is_array($middleware) ? $middleware : [$middleware];
        }

        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $mwList];
    }

    public function leaveGroup(): void
    {
        if (!empty($this->groupStack)) {
            array_pop($this->groupStack);
        }
    }

    public function getGroupStackSize(): int
    {
        return count($this->groupStack);
    }

    public function resetGroupStackTo(int $size): void
    {
        while (count($this->groupStack) > $size) {
            array_pop($this->groupStack);
        }
    }

    public function addRoute(string $method, string $path, callable|array|string $handler): Builder
    {
        $resolvedHandler = $this->resolveHandler($handler);
        $fullPath = $this->resolvePath($path);
        $groupMiddleware = $this->resolveMiddleware();

        // Использование вынесенного хелпера для парсинга путей
        $segments = PathHelper::parse($fullPath);
        $node = $this->rootNode;

        foreach ($segments as $segment) {
            $node = $node->addChild($segment);
        }

        $node->addHandler($method, $resolvedHandler, $groupMiddleware);

        return new Builder($node, $method);
    }

    /**
     * Безопасное разрешение обработчика.
     * Избегает преждевременного вызова автозагрузчика (autoloader) и фатальных ошибок
     * при проверке массивов вида [Controller::class, 'method'].
     */
    private function resolveHandler(callable|array|string $handler): callable|array
    {
        if ($handler instanceof Closure) {
            return $handler;
        }

        // Поддержка формата [ClassName::class, 'method'] или [$object, 'method']
        if (is_array($handler) && array_key_exists(0, $handler) && array_key_exists(1, $handler) && count($handler) === 2) {
            return $handler;
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                return explode('@', $handler, 2);
            }
            if (str_contains($handler, '::')) {
                return explode('::', $handler, 2);
            }
            // Проверка на глобальную функцию (безопасно, без триггера автозагрузчика классов)
            if (function_exists($handler)) {
                return $handler;
            }
        }

        throw Router::invalidHandler();
    }

    private function resolvePath(string $path): string
    {
        $prefixes = [];
        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $prefixes[] = $group['prefix'];
            }
        }

        $basePath = implode('', $prefixes);
        $path = '/' . trim($path, '/');

        if ($basePath === '') {
            return $path;
        }
        return rtrim($basePath, '/') . $path;
    }

    private function resolveMiddleware(): array
    {
        $allMiddleware = [];
        foreach ($this->groupStack as $group) {
            foreach ($group['middleware'] as $mw) {
                $allMiddleware[] = ['class' => $mw, 'params' => []];
            }
        }
        return $allMiddleware;
    }
}