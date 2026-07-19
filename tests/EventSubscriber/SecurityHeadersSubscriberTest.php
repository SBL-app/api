<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSubscribesToResponseEvent(): void
    {
        self::assertArrayHasKey(KernelEvents::RESPONSE, SecurityHeadersSubscriber::getSubscribedEvents());
    }

    public function testAddsSecurityHeadersOnMainRequest(): void
    {
        $response = $this->dispatch(HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertNotNull($response->headers->get('Permissions-Policy'));
        self::assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testIgnoresSubRequests(): void
    {
        $response = $this->dispatch(HttpKernelInterface::SUB_REQUEST);
        self::assertNull($response->headers->get('X-Frame-Options'));
    }

    private function dispatch(int $requestType): Response
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = new Response('{}');
        $event = new ResponseEvent($kernel, new Request(), $requestType, $response);

        (new SecurityHeadersSubscriber())->onKernelResponse($event);

        return $event->getResponse();
    }
}
