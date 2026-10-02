<?php

declare(strict_types=1);

namespace CodeX\Router\Middleware;

use Closure;
use CodeX\Contract\Router\Middleware;
use CodeX\Exception\Router;

/**
 * Нормализатор middleware.
 *
 * Приводит разные форматы middleware к массиву Definition.
 */
final class Normalizer
{
    /**
     * @return array<int, Definition>
     */
    public static function normalize(mixed $middleware, array $defaultParams = []): array
    {
        if ($middleware === null || $middleware === []) {
            return [];
        }

        if ($middleware instanceof Definition) {
            return [$middleware];
        }

        if ($middleware instanceof Middleware || $middleware instanceof Closure) {
            return [new Definition($middleware, $defaultParams)];
        }

        if (is_string($middleware)) {
            return [new Definition($middleware, $defaultParams)];
        }

        if (is_object($middleware)) {
            return [new Definition($middleware, $defaultParams)];
        }

        if (is_array($middleware)) {
            // Явное описание middleware: ['handler' => ..., 'params' => ...]
            // либо ['class' => ..., 'params' => ...].
            if (isset($middleware['handler']) || isset($middleware['class'])) {
                $handler = $middleware['handler'] ?? $middleware['class'];
                $params = $middleware['params'] ?? $defaultParams;
                return [new Definition($handler, (array) $params)];
            }

            // Callable-массив вида [$object, 'method'] поддерживается явно.
            if (
                array_is_list($middleware)
                && count($middleware) === 2
                && is_object($middleware[0])
                && is_string($middleware[1])
            ) {
                return [new Definition($middleware, $defaultParams)];
            }

            // Список middleware (рекурсивный обход).
            if (array_is_list($middleware)) {
                $result = [];
                foreach ($middleware as $item) {
                    array_push($result, ...self::normalize($item, $defaultParams));
                }
                return $result;
            }
        }

        throw Router::invalidMiddleware();
    }
}