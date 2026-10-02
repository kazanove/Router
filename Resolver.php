<?php

declare(strict_types=1);

namespace CodeX\Router;

use Closure;
use CodeX\Contract\Router\Middleware;
use CodeX\Exception\Router;
use ReflectionClass;
use ReflectionMethod;

/**
 * Резолвер callable-обработчиков и middleware.
 *
 * ИСПРАВЛЕНО: если контейнер передан, всегда использует его для создания
 * экземпляров. Если контейнер не передан, пытается создать через рефлексию
 * только классы без обязательных параметров конструктора.
 */
final readonly class Resolver
{
    public function __construct(
        private ?object $container = null
    ) {
    }

    public function resolve(mixed $handler): callable
    {
        if ($handler instanceof Closure) {
            return $handler;
        }

        if (is_object($handler)) {
            if (is_callable($handler)) {
                return $handler;
            }
            throw Router::invalidHandler();
        }

        if (is_array($handler)) {
            if (
                !array_key_exists(0, $handler)
                || !array_key_exists(1, $handler)
                || count($handler) !== 2
            ) {
                throw Router::invalidHandler();
            }

            [$target, $method] = $handler;

            if (!is_string($method)) {
                throw Router::invalidHandler();
            }

            if (is_object($target)) {
                if (!method_exists($target, $method)) {
                    throw Router::invalidHandler();
                }
                return [$target, $method];
            }

            if (is_string($target)) {
                if (method_exists($target, $method)) {
                    $reflection = new ReflectionMethod($target, $method);
                    if ($reflection->isStatic()) {
                        return [$target, $method];
                    }
                }

                $instance = $this->instantiate($target);
                if (!method_exists($instance, $method)) {
                    throw Router::invalidHandler();
                }
                return [$instance, $method];
            }

            throw Router::invalidHandler();
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
                return $this->resolve([$class, $method]);
            }
            if (str_contains($handler, '::')) {
                [$class, $method] = explode('::', $handler, 2);
                return $this->resolve([$class, $method]);
            }
            if (function_exists($handler)) {
                return $handler;
            }
            if (class_exists($handler)) {
                $instance = $this->instantiate($handler);
                if (is_callable($instance)) {
                    return $instance;
                }
            }
        }

        throw Router::invalidHandler();
    }

    public function resolveMiddleware(mixed $handler): callable
    {
        if ($handler instanceof Middleware) {
            return $this->wrapMiddleware($handler);
        }

        if (is_object($handler)) {
            if (method_exists($handler, 'handle')) {
                return [$handler, 'handle'];
            }
            if (is_callable($handler)) {
                return $handler;
            }
            throw Router::invalidMiddleware();
        }

        if (is_string($handler) && class_exists($handler)) {
            $instance = $this->instantiate($handler);
            if ($instance instanceof Middleware) {
                return $this->wrapMiddleware($instance);
            }
            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }
            if (is_callable($instance)) {
                return $instance;
            }
        }

        return $this->resolve($handler);
    }

    private function wrapMiddleware(Middleware $middleware): callable
    {
        return static function (mixed $request, callable $next, mixed ...$params) use ($middleware): mixed {
            return $middleware->handle($request, $next, ...$params);
        };
    }

    /**
     * ИСПРАВЛЕНО: использует контейнер для автоматического внедрения зависимостей.
     * Если контейнер недоступен, проверяет возможность создания через рефлексию.
     */
    private function instantiate(string $class): object
    {
        if ($this->container !== null && method_exists($this->container, 'make')) {
            return $this->container->make($class);
        }

        if (!class_exists($class)) {
            throw Router::invalidHandler();
        }

        // Проверяем, можно ли создать экземпляр без аргументов
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw Router::invalidHandler();
        }
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw Router::invalidHandler();
        }

        return new $class();
    }
}