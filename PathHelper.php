<?php
declare(strict_types=1);

namespace CodeX\Router;

/**
 * Класс-хелпер для нормализации и разбора URL-путей.
 * Вынесен в отдельный компонент для соблюдения принципа DRY (Don't Repeat Yourself),
 * так как логика парсинга идентична для Collector и Matcher.
 */
final class PathHelper
{
    /**
     * Разбирает строку пути на массив сегментов.
     *
     * @param string $path Исходный путь (например, '/api/users/{id}/')
     * @return array<string> Массив сегментов (например, ['api', 'users', '{id}'])
     */
    public static function parse(string $path): array
    {
        // Гарантируем наличие начального слеша и убираем конечные
        $path = '/' . trim($path, '/');

        // Корневой путь не имеет сегментов
        if ($path === '/') {
            return [];
        }

        $segments = explode('/', $path);
        array_shift($segments); // Удаляем первый пустой элемент, возникающий из-за начального слеша

        return $segments;
    }
}