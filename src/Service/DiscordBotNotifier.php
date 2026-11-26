<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Demande au bot Discord d'envoyer un message privé (endpoint HTTP du bot protégé par X-Service-Key).
 * Désactivé si DISCORD_BOT_NOTIFY_URL est vide.
 */
class DiscordBotNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'DISCORD_BOT_NOTIFY_URL')] private string $url,
        #[Autowire(env: 'DISCORD_BOT_NOTIFY_KEY')] private string $key,
    ) {}

    public function send(string $discordId, string $title, string $body, string $url = '/'): void
    {
        if ($this->url === '') {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->url, [
                'headers' => ['X-Service-Key' => $this->key],
                'json' => ['discordId' => $discordId, 'title' => $title, 'body' => $body, 'url' => $url],
                'timeout' => 5,
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->warning('Discord notification failed', ['discordId' => $discordId, 'error' => $e->getMessage()]);
        }
    }
}
