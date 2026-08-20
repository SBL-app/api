<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Garde le contrat de la sonde de supervision.
 *
 * Uptime Kuma et le test de fumée du déploiement continu dépendent de la forme
 * exacte de cette réponse : tout changement de clé ou de code HTTP doit casser
 * ici avant de casser la production.
 */
class HealthControllerTest extends ApiTestCase
{
    public function testHealthIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testHealthExposesTheDocumentedContract(): void
    {
        $this->client->request('GET', '/api/health');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        foreach (['status', 'version', 'commit', 'timestamp', 'checks'] as $key) {
            $this->assertArrayHasKey($key, $data, "La clé « $key » fait partie du contrat.");
        }

        $this->assertContains($data['status'], ['ok', 'degraded', 'error']);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('migrations', $data['checks']);
    }

    public function testDatabaseCheckReportsConnectivityAndLatency(): void
    {
        $this->client->request('GET', '/api/health');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('ok', $data['checks']['database']['status']);
        $this->assertArrayHasKey('latency_ms', $data['checks']['database']);
        $this->assertIsNumeric($data['checks']['database']['latency_ms']);

        // Le DSN ne doit jamais transiter par cet endpoint public.
        $this->assertStringNotContainsStringIgnoringCase(
            'sqlite',
            $this->client->getResponse()->getContent()
        );
    }

    public function testMigrationStateIsReported(): void
    {
        $this->client->request('GET', '/api/health');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $migrations = $data['checks']['migrations'];
        $this->assertContains($migrations['status'], ['ok', 'pending', 'error']);

        if ('error' !== $migrations['status']) {
            $this->assertIsInt($migrations['available']);
            $this->assertIsInt($migrations['executed']);
            $this->assertIsInt($migrations['pending']);
        }
    }

    /**
     * Une base joignable vaut disponibilité, même si le schéma a dérivé : un
     * 503 sur des migrations en attente ferait osciller la supervision pendant
     * un déploiement. La dégradation se lit dans `status`, pas dans le code.
     */
    public function testPendingMigrationsDegradeWithoutFailing(): void
    {
        $this->client->request('GET', '/api/health');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        if ('ok' !== $data['checks']['migrations']['status']) {
            $this->assertSame('degraded', $data['status']);
        } else {
            $this->assertSame('ok', $data['status']);
        }
    }

    public function testVersionFallsBackWhenNotInjectedAtBuild(): void
    {
        $this->client->request('GET', '/api/health');
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // APP_VERSION / APP_COMMIT ne sont injectés qu'au build de l'image.
        $this->assertSame('dev', $data['version']);
        $this->assertSame('unknown', $data['commit']);
    }
}
