<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class ClientLogControllerTest extends ApiTestCase
{
    private function post(array|string $body, string $ip = '10.0.0.1'): void
    {
        $this->client->request(
            'POST',
            '/api/client-logs',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip],
            is_array($body) ? json_encode($body) : $body
        );
    }

    public function testAcceptsAnErrorWithoutAuthentication(): void
    {
        $this->post(['message' => 'boom', 'stack' => 'Error: boom\n at x', 'route' => '/seasons']);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testRejectsMissingMessage(): void
    {
        $this->post(['stack' => 'sans message']);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRejectsInvalidJson(): void
    {
        $this->post('pas du json');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testOversizedFieldsAreTruncatedNotRejected(): void
    {
        $this->post(['message' => str_repeat('a', 50000), 'stack' => str_repeat('b', 50000)], '10.0.0.2');

        $this->assertResponseStatusCodeSame(204);
    }

    public function testIsRateLimitedPerIp(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $this->post(['message' => "err $i"], '10.0.0.3');
            $this->assertResponseStatusCodeSame(204);
        }

        $this->post(['message' => 'one too many'], '10.0.0.3');
        $this->assertResponseStatusCodeSame(429);
    }
}
