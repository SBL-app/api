<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordOAuthService
{
    private const DISCORD_API_URL = 'https://discord.com/api/v10';
    private const DISCORD_OAUTH_URL = 'https://discord.com/oauth2/authorize';
    private const DISCORD_TOKEN_URL = 'https://discord.com/api/oauth2/token';

    public function __construct(
        private HttpClientInterface $httpClient,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $botSecret,
        private string $frontendUrl
    ) {}

    public function getAuthorizationUrl(?string $redirectAfter = null): array
    {
        $state = $this->generateState($redirectAfter);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ];

        return [
            'url' => self::DISCORD_OAUTH_URL . '?' . http_build_query($params),
            'state' => $state,
        ];
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = $this->httpClient->request('POST', self::DISCORD_TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to exchange code for token: ' . $response->getContent(false));
        }

        return $response->toArray();
    }

    public function getDiscordUser(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::DISCORD_API_URL . '/users/@me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to get Discord user: ' . $response->getContent(false));
        }

        return $response->toArray();
    }

    public function createOrUpdateUserFromDiscord(array $discordUser): User
    {
        $discordId = $discordUser['id'];
        $user = $this->userRepository->findByDiscordId($discordId);

        if (!$user) {
            $user = new User();
            $user->setDiscordId($discordId);
            $user->setUsername('discord_' . $discordId);
            $user->setPassword(bin2hex(random_bytes(32)));
            $user->setRoles([]);
            $user->setIsActive(true);
        }

        $user->setDiscordUsername($discordUser['username']);
        $user->setDiscordAvatar($discordUser['avatar'] ?? null);
        $user->setLastLogin(new \DateTime());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function validateBotSecret(string $secret): bool
    {
        return hash_equals($this->botSecret, $secret);
    }

    public function getUserByDiscordId(string $discordId): ?User
    {
        return $this->userRepository->findByDiscordId($discordId);
    }

    public function decodeState(string $state): array
    {
        $decoded = base64_decode($state);
        if ($decoded === false) {
            return ['csrf' => '', 'redirect_after' => null];
        }

        $parts = json_decode($decoded, true);
        if (!is_array($parts)) {
            return ['csrf' => '', 'redirect_after' => null];
        }

        return [
            'csrf' => $parts['csrf'] ?? '',
            'redirect_after' => $parts['redirect_after'] ?? null,
        ];
    }

    public function getRedirectUrl(?string $redirectAfter, string $token, array $user): string
    {
        $baseUrl = $redirectAfter ?? $this->frontendUrl;

        if (!$this->isAllowedRedirectUrl($baseUrl)) {
            $baseUrl = $this->frontendUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $userJson = base64_encode(json_encode($user));

        return $baseUrl . $separator . http_build_query([
            'token' => $token,
            'user' => $userJson,
        ]);
    }

    public function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'discord_id' => $user->getDiscordId(),
            'discord_username' => $user->getDiscordUsername(),
            'discord_avatar' => $user->getDiscordAvatar(),
            'roles' => $user->getRoles(),
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $user->isActive(),
        ];
    }

    private function generateState(?string $redirectAfter): string
    {
        $data = [
            'csrf' => bin2hex(random_bytes(16)),
            'redirect_after' => $redirectAfter,
        ];

        return base64_encode(json_encode($data));
    }

    private function isAllowedRedirectUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }

        $frontendParsed = parse_url($this->frontendUrl);
        $frontendHost = $frontendParsed['host'] ?? '';

        $allowedHosts = [
            'localhost',
            '127.0.0.1',
            $frontendHost,
        ];

        return in_array($parsed['host'], $allowedHosts, true);
    }
}
