<?php
declare(strict_types=1);

namespace CodeX\Router;

use Closure;
use CodeX\Exception\Router;
use CodeX\Router\Helper\Path;
use CodeX\Router\Middleware\Definition;
use CodeX\Router\Middleware\Normalizer;

/**
 * Сборщик маршрутов.
 *
 * Отвечает за:
 * - добавление маршрутов;
 * - группы;
 * - префиксы;
 * - middleware групп;
 * - префиксы имён маршрутов;
 * - реестр именованных маршрутов.
 */
final class Collector
{
    /**
     * Property hook PHP 8.4:
     * публичный доступ к корню дерева только для чтения.
     */
    public Node $root {
        get => $this->rootNode;
    }

    private Node $rootNode;

    /**
     * Стек активных групп.
     *
     * @var array<int, array{
     *     prefix: string,
     *     middleware: array<int, Definition>,
     *     namePrefix: string
     * }>
     */
    private array $groupStack = [];

    /**
     * Карта именованных маршрутов.
     *
     * @var array<string, string>
     */
    private array $namedRoutes = [];

    public function __construct()
    {
        $this->rootNode = new Node();
    }

    public function enterGroup(string $prefix, mixed $middleware, string $namePrefix = ''): void
    {
        $prefix = '/' . trim($prefix, '/');

        if ($prefix === '/') {
            $prefix = '';
        }

        $this->groupStack[] = [
            'prefix' => $prefix,
            'middleware' => Normalizer::normalize($middleware),
            'namePrefix' => trim($namePrefix),
        ];
    }

    public function leaveGroup(): void
    {
        if ($this->groupStack !== []) {
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
        $method = strtoupper(trim($method));

        if ($method === '') {
            throw Router::invalidMethod($method);
        }

        $this->assertHandler($handler);

        $fullPath = $this->resolvePath($path);
        $groupMiddleware = $this->resolveMiddleware();
        $segments = Path::parse($fullPath);

        $node = $this->rootNode;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $node = $node->addChild($segment);

            if ($node->isCatchAll && $index !== $lastIndex) {
                throw Router::catchAllMustBeLast($segment);
            }
        }

        $node->setPath($fullPath);
        $node->addHandler($method, $handler, $groupMiddleware);

        return new Builder($node, [$method], $this);
    }

    /**
     * Регистрирует имя маршрута.
     *
     * Возвращает полное имя с учётом активных префиксов групп.
     */
    public function registerRouteName(string $name, Node $node): string
    {
        $name = trim($name);

        if ($name === '') {
            throw Router::invalidRouteName();
        }

        $fullName = $this->resolveName($name);
        $path = $node->getPath() ?? '/';

        if (isset($this->namedRoutes[$fullName]) && $this->namedRoutes[$fullName] !== $path) {
            throw Router::duplicateRouteName($fullName);
        }

        $this->namedRoutes[$fullName] = $path;

        return $fullName;
    }

    public function getNamedRoute(string $name): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw Router::routeNotFound($name);
        }

        return $this->namedRoutes[$name];
    }

    /**
     * @return array<string, string>
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    private function resolvePath(string $path): string
    {
        $basePath = '';

        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $basePath .= $group['prefix'];
            }
        }

        $path = '/' . trim($path, '/');

        if ($basePath === '') {
            $full = $path;
        } else {
            $full = rtrim($basePath, '/') . $path;
        }

        if ($full !== '/') {
            $full = rtrim($full, '/');
        }

        return $full;
    }

    /**
     * @return array<int, Definition>
     */
    private function resolveMiddleware(): array
    {
        $all = [];

        foreach ($this->groupStack as $group) {
            foreach ($group['middleware'] as $middleware) {
                $all[] = $middleware;
            }
        }

        return $all;
    }

    private function resolveName(string $name): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['namePrefix'];
        }

        return $prefix . $name;
    }

    private function assertHandler(callable|array|string $handler): void
    {
        if ($handler instanceof Closure) {
            return;
        }

        if (is_object($handler)) {
            return;
        }

        if (is_array($handler)) {
            if (
                array_key_exists(0, $handler)
                && array_key_exists(1, $handler)
                && count($handler) === 2
                && is_string($handler[1])
                && (is_object($handler[0]) || is_string($handler[0]))
            ) {
                return;
            }

            throw Router::invalidHandler();
        }

        if (is_string($handler)) {
            if (
                function_exists($handler)
                || class_exists($handler)
                || str_contains($handler, '@')
                || str_contains($handler, '::')
            ) {
                return;
            }
        }

        throw Router::invalidHandler();
    }
}