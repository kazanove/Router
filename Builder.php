<?php
declare(strict_types=1);

namespace CodeX\Router;

class Builder
{
    private array $methods;

    public function __construct(private(set) readonly Node $node, array|string $methods)
    {
        $this->methods = (array)$methods;
    }

    public function getNode(): Node
    {
        return $this->node;
    }

    public function middleware(string|array|callable $middleware, array $params = []): self
    {
        foreach ($this->methods as $method) {
            $this->node->addMiddlewareToHandler($method, $middleware, $params);
        }
        return $this;
    }

    public function name(string $name): self
    {
        foreach ($this->methods as $method) {
            $this->node->setNameForHandler($method, $name);
        }
        return $this;
    }
}