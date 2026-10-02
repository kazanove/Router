<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Router\Helper\Path;
use CodeX\Router\Match\Result;

/**
 * Сопоставитель запросов.
 *
 * Выполняет обход дерева маршрутов и возвращает Result.
 */
final readonly class Matcher
{
    public function __construct(
        private Node $root
    ) {
    }

    public function match(string $method, string $url): Result
    {
        $method = strtoupper($method);
        $path = Path::fromUrl($url);
        $segments = Path::parse($path);

        $params = [];
        $node = $this->root;

        foreach ($segments as $i => $segment) {

            $next = $node->getChild($segment);

            if ($next !== null) {
                $node = $next;
                continue;
            }

            // Затем обычный параметр.
            $parameterChild = $node->getParameterChild();

            if (
                $parameterChild !== null
                && !$parameterChild->isCatchAll
                && $parameterChild->matchParameter($segment)
            ) {
                $params[$parameterChild->paramName] = $this->castParameter($parameterChild, $segment);
                $node = $parameterChild;
                continue;
            }

            // Затем catch-all-параметр, который забирает остаток пути.
            $catchAllChild = $node->getCatchAllChild();

            if ($catchAllChild !== null) {
                $remaining = implode('/', array_slice($segments, $i));

                if (($remaining !== '' || $catchAllChild->isOptional) && $catchAllChild->matchParameter($remaining)) {
                    $params[$catchAllChild->paramName] = rawurldecode($remaining);
                    $node = $catchAllChild;
                    break;
                }
            }

            return Result::notFound();
        }

        // Если после обхода остался необязательный catch-all,
        // который может совпасть с пустой строкой.
        $catchAllChild = $node->getCatchAllChild();

        if ($catchAllChild !== null && $catchAllChild->isOptional && $catchAllChild->matchParameter('')) {
            if (
                !$this->hasMethod($node, $method)
                && $this->hasMethod($catchAllChild, $method)
            ) {
                $params[$catchAllChild->paramName] = '';
                $node = $catchAllChild;
            } elseif (!$node->hasAnyHandler() && $catchAllChild->hasAnyHandler()) {
                $params[$catchAllChild->paramName] = '';
                $node = $catchAllChild;
            }
        }

        // Если остался необязательный параметр, который может совпасть
        // с отсутствующим сегментом.
        $optionalChild = $node->getOptionalParameterChild();

        if ($optionalChild !== null) {
            if (
                !$this->hasMethod($node, $method)
                && $this->hasMethod($optionalChild, $method)
            ) {
                $node = $optionalChild;
            } elseif (!$node->hasAnyHandler() && $optionalChild->hasAnyHandler()) {
                $node = $optionalChild;
            }
        }

        $handlerData = $node->getHandler($method);

        // Поддержка HEAD: если нет отдельного HEAD-обработчика,
        // используется GET-обработчик.
        if ($handlerData === null && $method === 'HEAD') {
            $handlerData = $node->getHandler('GET');
        }

        if ($handlerData !== null) {
            return Result::found(
                $handlerData['handler'],
                $handlerData['middleware'],
                $params,
                $handlerData['name'],
                $node->getPath()
            );
        }

        $allowedMethods = $node->getMethods();

        if (in_array('GET', $allowedMethods, true) && !in_array('HEAD', $allowedMethods, true)) {
            $allowedMethods[] = 'HEAD';
        }

        if ($allowedMethods !== []) {
            return Result::methodNotAllowed($allowedMethods, $params, $node->getPath());
        }

        return Result::notFound();
    }

    private function hasMethod(Node $node, string $method): bool
    {
        return $node->hasHandler($method)
            || ($method === 'HEAD' && $node->hasHandler('GET'));
    }

    private function castParameter(Node $node, string $rawValue): string|int|float
    {
        $value = rawurldecode($rawValue);

        return match ($node->paramType) {
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}