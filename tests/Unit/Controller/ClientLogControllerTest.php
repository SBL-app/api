<?php

namespace App\Tests\Unit\Controller;

use App\Controller\ClientLogController;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class ClientLogControllerTest extends TestCase
{
    private function controller(LoggerInterface $logger, int $limit = 20): ClientLogController
    {
        $factory = new RateLimiterFactory(
            ['id' => 'client_logs', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'],
            new InMemoryStorage()
        );

        return new ClientLogController($logger, $factory);
    }

    private function request(array|string $body, array $server = []): Request
    {
        return Request::create(
            '/api/client-logs',
            'POST',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.0.1'], $server),
            is_array($body) ? json_encode($body) : $body
        );
    }

    public function testLogsAtErrorLevelWithBoundedContext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Erreur front : boom',
            $this->callback(function (array $ctx): bool {
                return 'Error: boom' === $ctx['stack']
                    && '/seasons' === $ctx['route']
                    && 'vue:render' === $ctx['source']
                    && 'http://t/seasons' === $ctx['url']
                    && 'Mozilla/5.0' === $ctx['user_agent']
                    && !array_key_exists('message', $ctx);
            })
        );

        $response = $this->controller($logger)->collect($this->request(
            ['message' => 'boom', 'stack' => 'Error: boom', 'route' => '/seasons', 'source' => 'vue:render', 'url' => 'http://t/seasons'],
            ['HTTP_USER_AGENT' => 'Mozilla/5.0']
        ));

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testTruncatesEveryFieldToItsLimit(): void
    {
        $captured = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string $msg, array $ctx) use (&$captured): void {
            $captured = [$msg, $ctx];
        });

        $big = str_repeat('é', 10000);
        $this->controller($logger)->collect($this->request([
            'message' => $big, 'stack' => $big, 'url' => $big, 'route' => $big, 'source' => $big,
        ], ['HTTP_USER_AGENT' => $big]));

        [$msg, $ctx] = $captured;
        $this->assertSame(mb_strlen('Erreur front : ') + 1000, mb_strlen($msg));
        $this->assertSame(4000, mb_strlen($ctx['stack']));
        $this->assertSame(500, mb_strlen($ctx['url']));
        $this->assertSame(200, mb_strlen($ctx['route']));
        $this->assertSame(50, mb_strlen($ctx['source']));
        $this->assertSame(300, mb_strlen($ctx['user_agent']));
    }

    public function testIgnoresNonStringOptionalFieldsAndUnknownKeys(): void
    {
        $captured = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string $msg, array $ctx) use (&$captured): void {
            $captured = $ctx;
        });

        $this->controller($logger)->collect($this->request([
            'message' => 'x', 'stack' => ['a'], 'route' => 42, 'level' => 'critical', 'extra' => 'nope',
        ]));

        $this->assertArrayNotHasKey('stack', $captured);
        $this->assertArrayNotHasKey('route', $captured);
        $this->assertArrayNotHasKey('level', $captured);
        $this->assertArrayNotHasKey('extra', $captured);
    }

    /**
     * @dataProvider invalidBodies
     */
    public function testRejectsInvalidPayloadWithoutLogging(array|string $body): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $response = $this->controller($logger)->collect($this->request($body));

        $this->assertSame(400, $response->getStatusCode());
    }

    public static function invalidBodies(): iterable
    {
        yield 'not json' => ['pas du json'];
        yield 'json scalar' => ['"texte"'];
        yield 'empty object' => [[]];
        yield 'missing message' => [['stack' => 's']];
        yield 'message not a string' => [['message' => 123]];
        yield 'blank message' => [['message' => "  \n "]];
    }

    public function testRateLimitIsPerIpAndDoesNotLogWhenExceeded(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');
        $controller = $this->controller($logger, 2);

        $this->assertSame(204, $controller->collect($this->request(['message' => 'a']))->getStatusCode());
        $this->assertSame(204, $controller->collect($this->request(['message' => 'b']))->getStatusCode());
        $this->assertSame(429, $controller->collect($this->request(['message' => 'c']))->getStatusCode());
        // une autre IP n'est pas affectée
        $this->assertSame(204, $controller->collect($this->request(['message' => 'd'], ['REMOTE_ADDR' => '10.0.0.2']))->getStatusCode());
    }
}
