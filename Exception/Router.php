<?php
declare(strict_types=1);

namespace CodeX\Exception;

use NoDiscard;
use RuntimeException;

/**
 * Фабрика исключений маршрутизатора.
 *
 * Все ошибки маршрутизатора представлены одним типом исключения,
 * что упрощает обработку и логирование.
 */
final class Router extends RuntimeException
{
    #[NoDiscard]
    public static function emptyMethods(): self
    {
        return new self('Список HTTP-методов не может быть пустым.');
    }
    #[NoDiscard]
    public static function invalidMethod(string $method): self
    {
        return new self('Недопустимый HTTP-метод: ' . $method . '.');
    }
    #[NoDiscard]
    public static function invalidRegex(string $param, string $error): self
    {
        return new self(sprintf(
            'Некорректное регулярное выражение для параметра "%s": %s',
            $param,
            $error
        ));
    }
    #[NoDiscard]
    public static function regexExecutionError(string $param, string $error): self
    {
        return new self(sprintf(
            'Ошибка выполнения регулярного выражения для параметра "%s": %s',
            $param,
            $error
        ));
    }
    #[NoDiscard]
    public static function parameterAlreadyExists(string $segment, string $existing): self
    {
        return new self(sprintf(
            'Параметр "%s" конфликтует с уже существующим параметром "%s" на том же уровне дерева маршрутов.',
            $segment,
            $existing
        ));
    }
    #[NoDiscard]
    public static function handlerAlreadyExists(string $method): self
    {
        return new self(sprintf('Обработчик для HTTP-метода "%s" уже зарегистрирован.', $method));
    }
    #[NoDiscard]
    public static function handlerNotFound(string $method): self
    {
        return new self(sprintf('Обработчик для HTTP-метода "%s" не найден.', $method));
    }
    #[NoDiscard]
    public static function invalidHandler(): self
    {
        return new self('Недопустимый обработчик маршрута.');
    }
    #[NoDiscard]
    public static function invalidMiddleware(): self
    {
        return new self('Недопустимый middleware.');
    }
    #[NoDiscard]
    public static function invalidRouteName(): self
    {
        return new self('Имя маршрута не может быть пустым.');
    }
    #[NoDiscard]
    public static function routeNotFound(string $name): self
    {
        return new self(sprintf('Маршрут с именем "%s" не найден.', $name));
    }
    #[NoDiscard]
    public static function routeParamMissing(string $param, string $name): self
    {
        return new self(sprintf(
            'Для генерации URL маршрута "%s" отсутствует обязательный параметр "%s".',
            $name,
            $param
        ));
    }
    #[NoDiscard]
    public static function duplicateRouteName(string $name): self
    {
        return new self(sprintf('Имя маршрута "%s" уже зарегистрировано для другого пути.', $name));
    }
    #[NoDiscard]
    public static function unusedRouteParams(array $params): self
    {
        return new self(sprintf(
            'При генерации URL остались неиспользованные параметры: %s.',
            implode(', ', $params)
        ));
    }
    #[NoDiscard]
    public static function catchAllMustBeLast(string $segment): self
    {
        return new self(sprintf(
            'Catch-all-параметр "%s" может находиться только в конце маршрута.',
            $segment
        ));
    }
    #[NoDiscard]
    public static function notFound(): self
    {
        return new self('Маршрут не найден.');
    }
    #[NoDiscard]
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(sprintf(
            'Метод не разрешён. Допустимые методы: %s.',
            implode(', ', $allowed)
        ));
    }
}

