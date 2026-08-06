<?php
declare(strict_types=1);

namespace CodeX\Router;

use CodeX\Router\Match\Result;

class Matcher
{
    private Node $root;

    public function __construct(Node $root)
    {
        $this->root = $root;
    }

    public function match(string $method, string $path): Result
    {
        // Использование общего хелпера
        $segments = PathHelper::parse($path);
        $params = [];
        $node = $this->root;

        foreach ($segments as $segment) {
            $next = $node->getChild($segment);

            if ($next === null) {
                $next = $node->getParameterChild();
                if ($next === null) {
                    return new Result(null, [], [], null);
                }

                if (!$next->matchParameter($segment)) {
                    return new Result(null, [], [], null);
                }

                // Автоматический кастинг типов на основе эвристики узла
                $value = $segment;
                $paramType = $next->paramType;
                if ($paramType === 'int') {
                    $value = (int) $value;
                } elseif ($paramType === 'float') {
                    $value = (float) $value;
                }
                $params[$next->paramName] = $value;
            }

            $node = $next;
        }

        $handlerData = $node->getHandler($method);

        if ($handlerData !== null) {
            return new Result(
                $handlerData['handler'],
                $handlerData['middleware'],
                $params,
                $handlerData['name']
            );
        }

        // Если узел найден, но метод не совпал, возвращаем разрешенные методы для 405 ответа
        $allowedMethods = $node->getMethods();
        return new Result(null, [], $params, null, $allowedMethods);
    }
}