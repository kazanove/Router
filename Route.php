<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Exception\Router;
use CodeX\Router\Match\Result;

class Route
{
    /**
     * Property Hook (PHP 8.4) для инкапсуляции инстанса коллектора.
     */
    public Collector $collector {
        get => $this->collectorInstance;
    }

    private Collector $collectorInstance;
    private ?Matcher $matcher = null;
    private ?array $namedRoutes = null;

    public function __construct()
    {
        $this->collectorInstance = new Collector();
    }

    public function middleware(string|array|callable $middleware): Group
    {
        return new Group($this, $middleware, null);
    }

    public function prefix(string $prefix): Group
    {
        return new Group($this, null, $prefix);
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

    public function any(string $path, callable|array|string $handler, array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'QUERY']): Builder
    {
        if (empty($methods)) {
            throw Router::emptyMethods();
        }

        $node = null;
        foreach ($methods as $method) {
            $b = $this->collectorInstance->addRoute($method, $path, $handler);
            if ($node === null) {
                $node = $b->getNode();
            }
        }

        return new Builder($node, $methods);
    }

    /**
     * Диспетчеризация запроса. Lazy Initialization: Matcher создается только при первом вызове.
     */
    public function handle(string $method, string $url): Result
    {
        if ($this->matcher === null) {
            $this->matcher = new Matcher($this->collectorInstance->root);
        }
        return $this->matcher->match($method, $url);
    }

    /**
     * Обратная маршрутизация (Reverse Routing): генерация URL по имени маршрута.
     */
    public function url(string $name, array $params = []): string
    {
        if ($this->namedRoutes === null) {
            $this->buildNamedRoutesMap();
        }

        if (!isset($this->namedRoutes[$name])) {
            throw Router::routeNotFound($name);
        }

        $pattern = $this->namedRoutes[$name];

        return preg_replace_callback('/\{([a-zA-Z_]\w*)(?::[^}]*)?}/', static function ($matches) use ($params, $name) {
            $paramName = $matches[1];
            if (!array_key_exists($paramName, $params)) {
                throw Router::routeParamMissing($paramName, $name);
            }
            return rawurlencode((string)$params[$paramName]);
        }, $pattern);
    }

    private function buildNamedRoutesMap(): void
    {
        $this->namedRoutes = [];
        $this->collectNamedRoutes($this->collectorInstance->root, '');
    }

    private function collectNamedRoutes(Node $node, string $currentPath): void
    {
        $pathSegment = $node->paramName ? '{' . $node->paramName . '}' : $node->segment;
        if ($pathSegment !== null) {
            $currentPath .= '/' . $pathSegment;
        }

        // ИСПРАВЛЕНИЕ: Добавлен метод 'QUERY' для корректного маппинга именованных маршрутов.
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'QUERY'] as $method) {
            $handlerData = $node->getHandler($method);
            if ($handlerData !== null && $handlerData['name'] !== null) {
                $this->namedRoutes[$handlerData['name']] = $currentPath !== '' ? $currentPath : '/';
            }
        }

        foreach ($node->children as $child) {
            $this->collectNamedRoutes($child, $currentPath);
        }
    }
}