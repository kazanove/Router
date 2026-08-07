<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Exception\Router;
use CodeX\Router\Middleware\Definition;
use ValueError;

/**
 * Узел дерева маршрутов.
 *
 * Поддерживает:
 * - статические сегменты;
 * - параметры;
 * - необязательные параметры;
 * - catch-all-параметры;
 * - псевдонимы типов: int, float, slug, uuid, catchall.
 */
final class Node
{
    /**
     * Псевдонимы типов параметров.
     */
    private const array TYPE_ALIASES = [
        'int' => '\d+',
        'float' => '\d+(?:\.\d+)?',
        'slug' => '[a-zA-Z0-9_-]+',
        'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        'catchall' => '.*',
    ];

    /**
     * Статические дочерние узлы.
     *
     * @var array<string, Node>
     */
    private array $staticChildren = [];

    /**
     * Все дочерние узлы, включая параметры и catch-all.
     * Используется преимущественно для отладки и обхода дерева.
     *
     * @var array<string, Node>
     */
    private array $allChildren = [];

    private ?Node $parameterChild = null;
    private ?Node $catchAllChild = null;

    public ?string $paramName = null;
    public bool $isParameter = false;
    public bool $isOptional = false;
    public bool $isCatchAll = false;
    public ?string $paramType = null;

    /**
     * Скомпилированное регулярное выражение параметра.
     * Кэшируется один раз в конструкторе.
     */
    private string $pattern;

    /**
     * Обработчики по HTTP-методам.
     *
     * @var array<string, array{
     *     handler: mixed,
     *     middleware: array<int, Definition>,
     *     name: string|null
     * }>
     */
    private array $handlers = [];

    /**
     * Полный путь маршрута, к которому относится узел.
     */
    private ?string $path = null;

    public function __construct(
        public readonly ?string $segment = null
    ) {
        if ($segment === null) {
            return;
        }

        $working = $segment;

        // Поддержка необязательных параметров:
        // {page?}
        // {page:\d+?}
        if (str_ends_with($working, '?}')) {
            $this->isOptional = true;
            $working = substr($working, 0, -2) . '}';
        }

        if (!preg_match('/^\{([a-zA-Z_]\w*)(?::([^}]+))?}$/', $working, $matches)) {
            return;
        }

        $this->isParameter = true;
        $this->paramName = $matches[1];

        $regex = isset($matches[2]) ? trim($matches[2]) : null;

        if ($regex !== null) {
            $regex = self::TYPE_ALIASES[$regex] ?? $regex;
        } else {
            $regex = '[^/]+';
        }

        if ($regex === '.*') {
            $this->isCatchAll = true;
        }

        $this->paramType = $this->detectParamType($regex);

        $this->pattern = '~^' . str_replace('~', '\~', $regex) . '$~';

        try {
            $test = @preg_match($this->pattern, 'test');

            if ($test === false) {
                throw Router::invalidRegex($this->paramName, 'некорректный шаблон');
            }
        } catch (ValueError $e) {
            throw Router::invalidRegex($this->paramName, $e->getMessage());
        }
    }

    private function detectParamType(string $regex): string
    {
        if ($this->isCatchAll) {
            return 'string';
        }

        if (in_array($regex, ['\d+', '[0-9]+'], true)) {
            return 'int';
        }

        if (in_array($regex, ['\d+\.\d+', '[0-9]+\.[0-9]+', '\d+(?:\.\d+)?'], true)) {
            return 'float';
        }

        return 'string';
    }

    public function addChild(string $segment): self
    {
        if (isset($this->allChildren[$segment])) {
            return $this->allChildren[$segment];
        }

        $child = new self($segment);

        if ($child->isCatchAll) {
            if ($this->catchAllChild !== null || $this->parameterChild !== null) {
                throw Router::parameterAlreadyExists(
                    $segment,
                    $this->catchAllChild?->segment ?? $this->parameterChild?->segment ?? 'unknown'
                );
            }

            $this->catchAllChild = $child;
        } elseif ($child->isParameter) {
            if ($this->parameterChild !== null || $this->catchAllChild !== null) {
                throw Router::parameterAlreadyExists(
                    $segment,
                    $this->parameterChild?->segment ?? $this->catchAllChild?->segment ?? 'unknown'
                );
            }

            $this->parameterChild = $child;
        } else {
            $this->staticChildren[$segment] = $child;
        }

        $this->allChildren[$segment] = $child;

        return $child;
    }

    public function getChild(string $segment): ?self
    {
        return $this->staticChildren[$segment] ?? null;
    }

    public function getParameterChild(): ?self
    {
        return $this->parameterChild;
    }

    public function getCatchAllChild(): ?self
    {
        return $this->catchAllChild;
    }

    public function getOptionalParameterChild(): ?self
    {
        if ($this->parameterChild !== null && $this->parameterChild->isOptional) {
            return $this->parameterChild;
        }

        return null;
    }

    /**
     * @return array<string, Node>
     */
    public function getChildren(): array
    {
        return $this->allChildren;
    }

    public function matchParameter(string $value): bool
    {
        if (!$this->isParameter && !$this->isCatchAll) {
            return false;
        }

        try {
            $result = @preg_match($this->pattern, $value);

            if ($result === false) {
                throw Router::regexExecutionError($this->paramName ?? 'unknown', 'ошибка preg_match');
            }
        } catch (ValueError $e) {
            throw Router::regexExecutionError($this->paramName ?? 'unknown', $e->getMessage());
        }

        return $result === 1;
    }

    public function setPath(string $path): void
    {
        if ($this->path === null) {
            $this->path = $path;
        }
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function addHandler(
        string $method,
        mixed $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $method = strtoupper($method);

        if (isset($this->handlers[$method])) {
            throw Router::handlerAlreadyExists($method);
        }

        $this->handlers[$method] = [
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $name,
        ];
    }

    public function getHandler(string $method): ?array
    {
        return $this->handlers[strtoupper($method)] ?? null;
    }

    public function hasHandler(string $method): bool
    {
        return isset($this->handlers[strtoupper($method)]);
    }

    public function hasAnyHandler(): bool
    {
        return $this->handlers !== [];
    }

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return array_keys($this->handlers);
    }

    public function addMiddlewareToHandler(string $method, Definition $middleware): void
    {
        $method = strtoupper($method);

        if (!isset($this->handlers[$method])) {
            throw Router::handlerNotFound($method);
        }

        $this->handlers[$method]['middleware'][] = $middleware;
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