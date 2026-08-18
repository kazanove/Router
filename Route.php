<?php

declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Exception\Router;
use CodeX\Router\Helper\Path;
use CodeX\Router\Match\Result;

/**
 * Главная точка входа маршрутизатора.
 */
final class Route
{
    /**
     * Property hook PHP 8.4:
     * публичный доступ к коллектору только для чтения.
     */
    public Collector $collector {
        get => $this->collectorInstance;
    }

    private Collector $collectorInstance;
    private ?Matcher $matcher = null;
    private Dispatcher $dispatcher;

    public function __construct(?object $container = null)
    {
        $this->collectorInstance = new Collector();
        $this->dispatcher = new Dispatcher(new Resolver($container));
    }

    // ============================================
    // НОВЫЙ МЕТОД: group()
    // ============================================

    /**
     * Создаёт группу маршрутов без префикса и middleware.
     *
     * Позволяет группировать маршруты без дополнительных параметров:
     *
     * route()->group(function (Route $router) {
     *     $router->get('/dashboard', [DashboardController::class, 'index']);
     * });
     */
    public function group(callable $callback): void
    {
        $group = new Group($this, null, null);
        $group->group($callback);
    }

    // ============================================
    // СУЩЕСТВУЮЩИЕ МЕТОДЫ
    // ============================================

    public function middleware(mixed $middleware): Group
    {
        return new Group($this, $middleware, null);
    }

    public function prefix(string $prefix): Group
    {
        return new Group($this, null, $prefix);
    }

    public function namePrefix(string $namePrefix): Group
    {
        return new Group($this, null, null, $namePrefix);
    }

    public function get(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('DELETE', $path, $handler);
    }

    public function patch(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('PATCH', $path, $handler);
    }

    public function options(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('OPTIONS', $path, $handler);
    }

    public function query(string $path, callable|array|string $handler): Builder
    {
        return $this->collectorInstance->addRoute('QUERY', $path, $handler);
    }

    public function any(
        string $path,
        callable|array|string $handler,
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'QUERY']
    ): Builder {
        $methods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $methods
        )));

        if ($methods === []) {
            throw Router::emptyMethods();
        }

        $node = null;

        foreach ($methods as $method) {
            $builder = $this->collectorInstance->addRoute($method, $path, $handler);

            if ($node === null) {
                $node = $builder->getNode();
            }
        }

        return new Builder($node, $methods, $this->collectorInstance);
    }

    /**
     * Сопоставляет запрос с маршрутом.
     */
    public function handle(string $method, string $url): Result
    {
        if ($this->matcher === null) {
            $this->matcher = new Matcher($this->collectorInstance->root);
        }

        return $this->matcher->match($method, $url);
    }

    /**
     * Сопоставляет запрос и сразу выполняет middleware и обработчик.
     */
    public function dispatch(string $method, string $url, mixed $request = null): mixed
    {
        $result = $this->handle($method, $url);

        return $this->dispatcher->dispatch($result, $request);
    }

    /**
     * Генерация URL по имени маршрута.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function url(
        string $name,
        array $params = [],
        array $query = [],
        ?string $fragment = null,
        bool $strict = false
    ): string {
        $pattern = $this->collectorInstance->getNamedRoute($name);
        $segments = Path::parse($pattern);

        $parts = [];
        $used = [];

        foreach ($segments as $segment) {
            $info = $this->parseParameterSegment($segment);

            if ($info === null) {
                $parts[] = $segment;
                continue;
            }

            $paramName = $info['name'];

            if (array_key_exists($paramName, $params)) {
                $value = $params[$paramName];

                if ($value === null) {
                    if ($info['optional']) {
                        continue;
                    }

                    throw Router::routeParamMissing($paramName, $name);
                }

                $used[] = $paramName;

                if ($info['catchall']) {
                    $encoded = implode('/', array_map(
                        'rawurlencode',
                        explode('/', (string) $value)
                    ));
                } else {
                    $encoded = rawurlencode((string) $value);
                }

                $parts[] = $encoded;

                continue;
            }

            if (!$info['optional']) {
                throw Router::routeParamMissing($paramName, $name);
            }
        }

        $path = '/' . implode('/', $parts);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $unused = array_diff_key($params, array_flip($used));

        if ($strict && $unused !== []) {
            throw Router::unusedRouteParams(array_keys($unused));
        }

        if ($unused !== []) {
            $query = array_merge($query, $unused);
        }

        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        if ($fragment !== null && $fragment !== '') {
            $path .= '#' . rawurlencode($fragment);
        }

        return $path;
    }

    /**
     * @return array<string, string>
     */
    public function getNamedRoutes(): array
    {
        return $this->collectorInstance->getNamedRoutes();
    }

    /**
     * Разбирает сегмент маршрута для генерации URL.
     *
     * @return array{name: string, optional: bool, catchall: bool}|null
     */
    private function parseParameterSegment(string $segment): ?array
    {
        $optional = false;

        if (str_ends_with($segment, '?}')) {
            $optional = true;
            $segment = substr($segment, 0, -2) . '}';
        }

        if (!preg_match('/^\{([a-zA-Z_]\w*)(?::([^}]+))?}$/', $segment, $matches)) {
            return null;
        }

        $regex = isset($matches[2]) ? trim($matches[2]) : null;
        $catchAll = in_array($regex, ['.*', 'catchall'], true);

        return [
            'name' => $matches[1],
            'optional' => $optional,
            'catchall' => $catchAll,
        ];
    }
}