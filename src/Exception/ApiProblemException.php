<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiProblemException extends HttpException
{
    private string $type;
    private string $title;
    private array $violations;

    public function __construct(
        int $statusCode,
        string $detail,
        string $type = 'about:blank',
        string $title = '',
        array $violations = [],
        ?\Throwable $previous = null
    ) {
        $this->type = $type;
        $this->title = $title ?: $this->getDefaultTitle($statusCode);
        $this->violations = $violations;

        parent::__construct($statusCode, $detail, $previous);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->getStatusCode(),
            'detail' => $this->getMessage(),
        ];

        if (!empty($this->violations)) {
            $data['violations'] = $this->violations;
        }

        return $data;
    }

    private function getDefaultTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }

    public static function notFound(string $detail): self
    {
        return new self(404, $detail, '/problems/not-found', 'Not Found');
    }

    public static function badRequest(string $detail, array $violations = []): self
    {
        return new self(400, $detail, '/problems/bad-request', 'Bad Request', $violations);
    }

    public static function validationError(string $detail, array $violations = []): self
    {
        return new self(400, $detail, '/problems/validation-error', 'Validation Error', $violations);
    }

    public static function forbidden(string $detail): self
    {
        return new self(403, $detail, '/problems/forbidden', 'Forbidden');
    }

    public static function unauthorized(string $detail): self
    {
        return new self(401, $detail, '/problems/unauthorized', 'Unauthorized');
    }

    public static function conflict(string $detail): self
    {
        return new self(409, $detail, '/problems/conflict', 'Conflict');
    }

    public static function tooManyRequests(string $detail): self
    {
        return new self(429, $detail, '/problems/too-many-requests', 'Too Many Requests');
    }
}
