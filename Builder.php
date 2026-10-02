<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Router\Middleware\Normalizer;

/**
 * Строитель маршрута.
 *
 * Позволяет навешивать middleware и имя маршрута
 * сразу после регистрации маршрута.
 */
final class Builder
{
    /**
     * @var array<int, string>
     */
    private array $methods;

    public function __construct(
        private readonly Node $node,
        array|string $methods,
        private readonly Collector $collector
    ) {
        $this->methods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            (array) $methods
        )));
    }

    public function getNode(): Node
    {
        return $this->node;
    }

    public function middleware(mixed $middleware, array $params = []): self
    {
        $definitions = Normalizer::normalize($middleware, $params);

        foreach ($this->methods as $method) {
            foreach ($definitions as $definition) {
                $this->node->addMiddlewareToHandler($method, $definition);
            }
        }

        return $this;
    }

    public function name(string $name): self
    {
        $fullName = $this->collector->registerRouteName($name, $this->node);

        foreach ($this->methods as $method) {
            $this->node->setNameForHandler($method, $fullName);
        }

        return $this;
    }
}