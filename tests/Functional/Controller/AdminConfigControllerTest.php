<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class AdminConfigControllerTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/admin/config');
        $this->assertResponseStatusCode(401);
    }

    public function testForbiddenForNonAdmin(): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_API']);
        $this->jsonRequest('GET', '/api/admin/config');
        $this->assertResponseStatusCode(403);
        $this->jsonRequest('PUT', '/api/admin/config', ['deadline_day' => 'friday']);
        $this->assertResponseStatusCode(403);
    }

    public function testDefaults(): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $data = $this->jsonRequest('GET', '/api/admin/config');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'matchs_channel_id' => null,
            'classement_channel_id' => null,
            'match_manager_role_id' => null,
            'default_match_day' => 'sunday',
            'default_match_time' => '21:00',
            'deadline_day' => 'thursday',
        ], $data);
    }

    public function testUpdatePersistsAndIsPartial(): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $data = $this->jsonRequest('PUT', '/api/admin/config', [
            'matchs_channel_id' => '123456789012345678',
            'default_match_time' => '20:30',
            'deadline_day' => 'friday',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('123456789012345678', $data['matchs_channel_id']);

        $data = $this->jsonRequest('GET', '/api/admin/config');
        $this->assertSame('20:30', $data['default_match_time']);
        $this->assertSame('friday', $data['deadline_day']);
        $this->assertSame('sunday', $data['default_match_day']);

        // null efface un salon
        $data = $this->jsonRequest('PUT', '/api/admin/config', ['matchs_channel_id' => null]);
        $this->assertNull($data['matchs_channel_id']);
    }

    /** @dataProvider invalidPayloads */
    public function testValidation(array $payload): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $this->jsonRequest('PUT', '/api/admin/config', $payload);
        $this->assertResponseStatusCode(400);
    }

    public static function invalidPayloads(): array
    {
        return [
            'snowflake invalide' => [['matchs_channel_id' => 'abc']],
            'heure invalide' => [['default_match_time' => '25:00']],
            'heure format' => [['default_match_time' => '9h']],
            'jour invalide' => [['deadline_day' => 'lundi']],
            'clé inconnue' => [['foo' => 'bar']],
        ];
    }
}
