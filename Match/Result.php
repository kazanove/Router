<?php
declare(strict_types=1);

namespace CodeX\Router\Match;

/**
 * Результат сопоставления маршрута.
 */
final readonly class Result
{
    public function __construct(public Status $status, public mixed $handler = null, public array $middleware = [], public array $params = [], public ?string $name = null, public array $allowedMethods = [], public ?string $matchedPath = null)
    {
    }

    public static function found(mixed $handler, array $middleware, array $params, ?string $name, ?string $matchedPath): self
    {
        return new self(Status::Found, $handler, $middleware, $params, $name, [], $matchedPath);
    }

    public static function notFound(): self
    {
        return new self(Status::NotFound);
    }

    public static function methodNotAllowed(array $allowedMethods, array $params = [], ?string $matchedPath = null): self
    {
        return new self(Status::MethodNotAllowed, null, [], $params, null, $allowedMethods, $matchedPath);
    }

    public function isFound(): bool
    {
        return $this->status === Status::Found;
    }

    public function isNotFound(): bool
    {
        return $this->status === Status::NotFound;
    }

    public function isMethodNotAllowed(): bool
    {
        return $this->status === Status::MethodNotAllowed;
    }

    /**
     * Возвращает значение для HTTP-заголовка Allow.
     */
    public function getAllowedMethodsHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }
}