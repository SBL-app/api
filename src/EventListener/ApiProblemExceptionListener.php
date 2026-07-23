<?php

namespace App\EventListener;

use App\Exception\ApiProblemException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class ApiProblemExceptionListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ApiProblemException) {
            $data = $exception->toArray();
            $statusCode = $exception->getStatusCode();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $data = [
                'type' => 'about:blank',
                'title' => $this->getHttpTitle($exception->getStatusCode()),
                'status' => $exception->getStatusCode(),
                'detail' => $exception->getMessage(),
            ];
            $statusCode = $exception->getStatusCode();
        } else {
            $this->logger->error('Unhandled API exception', [
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $data = [
                'type' => '/problems/internal-error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'An internal error occurred.',
            ];
            $statusCode = 500;
        }

        $response = new JsonResponse($data, $statusCode);
        $response->headers->set('Content-Type', 'application/problem+json');

        $event->setResponse($response);
    }

    private function getHttpTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
