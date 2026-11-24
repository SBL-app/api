<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiProblemExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class ApiProblemExceptionListenerTest extends TestCase
{
    private function dispatch(\Throwable $e): ExceptionEvent
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/admin/dashboard'),
            HttpKernelInterface::MAIN_REQUEST,
            $e,
        );
        (new ApiProblemExceptionListener(new NullLogger()))->onKernelException($event);

        return $event;
    }

    /** Les erreurs de sécurité doivent être laissées au firewall (401/403), pas converties en 500. */
    #[DataProvider('securityExceptions')]
    public function testSecurityExceptionsAreLeftToFirewall(\Throwable $e): void
    {
        $this->assertFalse($this->dispatch($e)->hasResponse());
    }

    public static function securityExceptions(): iterable
    {
        yield 'authentication' => [new AuthenticationException()];
        yield 'insufficient' => [new InsufficientAuthenticationException()];
        yield 'access denied' => [new AccessDeniedException()];
    }

    public function testUnknownExceptionStill500(): void
    {
        $this->assertSame(500, $this->dispatch(new \RuntimeException('boom'))->getResponse()->getStatusCode());
    }
}
