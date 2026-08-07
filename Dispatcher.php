<?php
declare(strict_types=1);
namespace CodeX\Router;
use CodeX\Exception\Router;
use CodeX\Router\Match\Result;

/**
 * Диспетчер запроса.
 *
 * Выполняет цепочку middleware и конечный обработчик.
 */
final readonly class Dispatcher
{
    private Resolver $resolver;

    public function __construct(?Resolver $resolver = null)
    {
        $this->resolver = $resolver ?? new Resolver();
    }

    public function dispatch(Result $result, mixed $request = null): mixed
    {
        if ($result->isMethodNotAllowed()) {
            throw Router::methodNotAllowed($result->allowedMethods);
        }

        if (!$result->isFound()) {
            throw Router::notFound();
        }

        $core = function (mixed $request) use ($result): mixed {
            $handler = $this->resolver->resolve($result->handler);

            return $handler($request, $result->params);
        };

        $pipeline = $core;

        foreach (array_reverse($result->middleware) as $definition) {
            $middleware = $this->resolver->resolveMiddleware($definition->handler);
            $params = $definition->params;
            $next = $pipeline;

            $pipeline = static function (mixed $request) use ($middleware, $next, $params): mixed {
                return $middleware($request, $next, ...array_values($params));
            };
        }

        return $pipeline($request);
    }
}
