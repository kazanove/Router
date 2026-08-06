<?php
declare(strict_types=1);

namespace CodeX\Router\Match;

/**
 * DTO (Data Transfer Object), содержащий результат работы маршрутизатора.
 * Использование readonly class (PHP 8.2+) гарантирует неизменяемость объекта после создания.
 */
readonly class Result
{
    public function __construct(
        public mixed $handler,
        public array $middleware,
        public array $params,
        public ?string $name = null,
        public array $allowedMethods = []
    ) {
    }

    /**
     * Проверяет, был ли найден маршрут и обработчик.
     */
    public function isFound(): bool
    {
        return $this->handler !== null;
    }

    /**
     * Проверяет, существует ли маршрут для данного URL, но не для данного HTTP-метода.
     * Используется для формирования ответа 405 Method Not Allowed.
     */
    public function isMethodNotAllowed(): bool
    {
        return $this->handler === null && !empty($this->allowedMethods);
    }

    /**
     * Формирует строку для HTTP-заголовка 'Allow'.
     * Требуется по стандарту RFC 7231 при ответе 405 Method Not Allowed.
     *
     * @return string Например: 'GET, POST, OPTIONS'
     */
    public function getAllowedMethodsHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }
}