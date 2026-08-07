<?php
declare(strict_types=1);

namespace CodeX\Router\Helper;
/**
 * Вспомогательный класс для работы с путями.
 *
 * Устраняет дублирование логики разбора пути между Collector и Matcher.
 */
final class Path
{
    /**
     * Разбирает путь на сегменты.
     *
     * Пример:
     * '/users/{id}/' => ['users', '{id}']
     *
     * @return array<int, string>
     */
    public static function parse(string $path): array
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return [];
        }

        $segments = explode('/', $path);
        array_shift($segments);

        return $segments;
    }

    /**
     * Извлекает только путь из URL.
     *
     * Это исправляет проблему, когда строка запроса
     * вида "/users?page=2" ошибочно воспринималась как путь.
     */
    public static function fromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }
}