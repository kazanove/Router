<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Exception\Router;
use ValueError;

class Node
{
    public array $children = [];
    public ?string $paramName = null;
    public bool $isParameter = false;
    public ?string $paramType = null;
    private ?string $paramRegex = null;
    private ?string $nameValue = null;
    private array $handlers = [];

    /**
     * Конструктор с использованием Asymmetric Visibility (PHP 8.4).
     * Свойство $segment доступно для чтения извне (public),
     * но защищено от изменения извне (private(set)).
     */
    public function __construct( private(set) readonly ?string $segment = null)
    {
        // Проверка сегмента на наличие параметра (например, {id:\d+})
        if ($segment !== null && preg_match('/^{([a-zA-Z_]\w*)(?::([^}]+))?}$/', $segment, $matches)) {
            $this->isParameter = true;
            $this->paramName = $matches[1];
            $this->paramRegex = $matches[2] ?? '[^/]+';

            // Fail-Fast валидация: проверяем корректность регулярного выражения сразу при создании узла
            $testPattern = '~^' . str_replace('~', '\~', $this->paramRegex) . '$~';

            try {
                preg_match($testPattern, 'test');
            } catch (ValueError $e) {
                throw Router::invalidRegex($this->paramName, $e->getMessage());
            }

            $this->paramType = $this->detectParamType($this->paramRegex);
        }
    }

    /**
     * Эвристика для определения типа параметра на основе его регулярного выражения.
     * Позволяет Matcher'у автоматически приводить типы (кастинг).
     */
    private function detectParamType(string $regex): string
    {
        if ($regex === '\d+' || $regex === '[0-9]+') {
            return 'int';
        }
        if ($regex === '\d+\.\d+' || $regex === '[0-9]+\.[0-9]+') {
            return 'float';
        }
        return 'string';
    }

    public function getName(): ?string
    {
        return $this->nameValue;
    }

    public function setName(string $name): void
    {
        $this->nameValue = $name;
    }

    public function matchParameter(string $value): bool
    {
        if (!$this->isParameter) {
            return false;
        }

        $pattern = '~^' . str_replace('~', '\~', $this->paramRegex) . '$~';

        try {
            $result = preg_match($pattern, $value);
        } catch (ValueError $e) {
            throw Router::regexExecutionError($this->paramName, $e->getMessage());
        }

        return $result === 1;
    }

    public function addChild(string $segment): self
    {
        if (isset($this->children[$segment])) {
            return $this->children[$segment];
        }

        $child = new self($segment);

        // Защита от двусмысленности: на одном уровне дерева не может быть двух разных параметров
        if ($child->isParameter) {
            foreach ($this->children as $existing) {
                if ($existing->isParameter) {
                    throw Router::parameterAlreadyExists($segment, $existing->segment ?? 'unknown');
                }
            }
        }

        $this->children[$segment] = $child;
        return $child;
    }

    public function getChild(string $segment): ?self
    {
        return $this->children[$segment] ?? null;
    }

    /**
     * Использование нативной функции array_find (PHP 8.4) для поиска параметризованного потомка.
     */
    public function getParameterChild(): ?self
    {
        return array_find($this->children, static fn($child) => $child->isParameter);
    }

    public function addHandler(string $method, callable|array $handler, array $middleware = [], ?string $name = null): void
    {
        $method = strtoupper($method);
        if (isset($this->handlers[$method])) {
            throw Router::handlerAlreadyExists($method);
        }

        $normalizedMiddleware = [];
        foreach ($middleware as $mw) {
            if (is_array($mw) && isset($mw['class'])) {
                $normalizedMiddleware[] = $mw;
            } else {
                $normalizedMiddleware[] = ['class' => $mw, 'params' => []];
            }
        }

        $this->handlers[$method] = [
            'handler' => $handler,
            'middleware' => $normalizedMiddleware,
            'name' => $name,
        ];
    }

    public function getHandler(string $method): ?array
    {
        return $this->handlers[strtoupper($method)] ?? null;
    }

    public function getMethods(): array
    {
        return array_keys($this->handlers);
    }

    public function addMiddlewareToHandler(string $method, string|array|callable $middleware, array $params = []): void
    {
        $method = strtoupper($method);
        if (!isset($this->handlers[$method])) {
            throw Router::handlerNotFound($method);
        }

        $items = is_array($middleware) ? $middleware : [$middleware];
        foreach ($items as $item) {
            $this->handlers[$method]['middleware'][] = ['class' => $item, 'params' => $params];
        }
    }

    public function setNameForHandler(string $method, string $name): void
    {
        $method = strtoupper($method);
        if (!isset($this->handlers[$method])) {
            throw Router::handlerNotFound($method);
        }
        $this->handlers[$method]['name'] = $name;
    }
}