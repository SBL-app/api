<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiProblemException;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use App\Service\DiscordOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository,
        private AuthenticationService $authService,
        private DiscordOAuthService $discordOAuthService,
        private RateLimiterFactory $authLoginLimiter,
        private RateLimiterFactory $authApiKeyLimiter,
        private LoggerInterface $logger
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Rate limiting basé sur l'IP
        $limiter = $this->authLoginLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->logger->warning('Login rate limit exceeded', ['ip' => $request->getClientIp()]);
            throw ApiProblemException::tooManyRequests('Too many login attempts. Please try again later.');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            throw ApiProblemException::validationError('Username and password are required', [
                ['field' => 'username', 'message' => 'This value should not be blank.'],
                ['field' => 'password', 'message' => 'This value should not be blank.'],
            ]);
        }

        $user = $this->userRepository->findByUsernameOrApiKey($data['username']);

        if (!$user || !$user->isActive()) {
            $this->logger->warning('Login failed: invalid credentials', ['username' => $data['username']]);
            throw ApiProblemException::unauthorized('Invalid credentials');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            $this->logger->warning('Login failed: invalid password', ['username' => $data['username']]);
            throw ApiProblemException::unauthorized('Invalid credentials');
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        $this->logger->info('Login successful', ['user_id' => $user->getId(), 'username' => $user->getUsername()]);

        return $this->json([
            'token' => $token,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600 // 1 heure
        ]);
    }

    #[Route('/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            $this->logger->warning('Token refresh failed: missing authorization header');
            throw ApiProblemException::unauthorized('Missing or invalid Authorization header');
        }

        if (!$this->authService->canTokenBeRefreshed($token)) {
            $this->logger->warning('Token refresh failed: token too old to refresh');
            throw ApiProblemException::unauthorized('Token is too old to refresh. Please login again.');
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            throw ApiProblemException::unauthorized('Invalid token or user not found');
        }

        $newToken = $this->authService->createTokenForUser($user);

        $this->logger->info('Token refreshed', ['user_id' => $user->getId()]);

        return $this->json([
            'token' => $newToken,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600
        ]);
    }

    #[Route('/verify', name: 'api_verify_token', methods: ['POST'])]
    public function verifyToken(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            throw ApiProblemException::unauthorized('Missing or invalid Authorization header');
        }

        if (!$this->authService->isTokenValid($token)) {
            throw ApiProblemException::unauthorized('Invalid or expired token');
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            throw ApiProblemException::unauthorized('User not found or inactive');
        }

        try {
            $payload = $this->jwtManager->parse($token);
            return $this->json([
                'valid' => true,
                'user' => $this->authService->formatUserResponse($user),
                'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Token verification failed: parsing error', ['error' => $e->getMessage()]);
            throw ApiProblemException::unauthorized('Token parsing error');
        }
    }

    #[Route('/login-api-key', name: 'api_login_api_key', methods: ['POST'])]
    public function loginWithApiKey(Request $request): JsonResponse
    {
        // Rate limiting basé sur l'IP
        $limiter = $this->authApiKeyLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->logger->warning('API key login rate limit exceeded', ['ip' => $request->getClientIp()]);
            throw ApiProblemException::tooManyRequests('Too many API key authentication attempts. Please try again later.');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['api_key'])) {
            throw ApiProblemException::validationError('API key is required', [
                ['field' => 'api_key', 'message' => 'This value should not be blank.'],
            ]);
        }

        $user = $this->authService->validateApiKey($data['api_key']);

        if (!$user) {
            $this->logger->warning('API key login failed: invalid key', ['ip' => $request->getClientIp()]);
            throw ApiProblemException::unauthorized('Invalid API key or access not authorized');
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->authService->createTokenForUser($user);

        $this->logger->info('API key login successful', ['user_id' => $user->getId()]);

        return $this->json([
            'token' => $token,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600,
            'login_method' => 'api_key'
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized('User not authenticated');
        }

        // Invalider tous les tokens émis avant maintenant
        $user->invalidateAllTokens();
        $this->entityManager->flush();

        $this->logger->info('User logged out', ['user_id' => $user->getId()]);

        return new JsonResponse(null, 204);
    }

    #[Route('/discord', name: 'api_discord_auth', methods: ['GET'])]
    public function discordAuth(Request $request): RedirectResponse
    {
        $redirectAfter = $request->query->get('redirect_after');
        $authData = $this->discordOAuthService->getAuthorizationUrl($redirectAfter);

        return new RedirectResponse($authData['url']);
    }

    #[Route('/discord/callback', name: 'api_discord_callback', methods: ['GET'])]
    public function discordCallback(Request $request): RedirectResponse|JsonResponse
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error) {
            $this->logger->warning('Discord OAuth error', ['error' => $error, 'description' => $request->query->get('error_description')]);
            throw ApiProblemException::badRequest('Discord authorization failed: ' . $request->query->get('error_description'));
        }

        if (!$code) {
            throw ApiProblemException::badRequest('Missing authorization code');
        }

        try {
            $stateData = $this->discordOAuthService->decodeState($state ?? '');
            $tokens = $this->discordOAuthService->exchangeCodeForToken($code);
            $discordUser = $this->discordOAuthService->getDiscordUser($tokens['access_token']);
            $user = $this->discordOAuthService->createOrUpdateUserFromDiscord($discordUser);

            $jwtToken = $this->jwtManager->create($user);
            $userResponse = $this->discordOAuthService->formatUserResponse($user);

            $redirectUrl = $this->discordOAuthService->getRedirectUrl(
                $stateData['redirect_after'],
                $jwtToken,
                $userResponse
            );

            $this->logger->info('Discord login successful', ['user_id' => $user->getId(), 'discord_id' => $user->getDiscordId()]);

            return new RedirectResponse($redirectUrl);
        } catch (\Exception $e) {
            $this->logger->error('Discord authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new ApiProblemException(500, 'Discord authentication failed: ' . $e->getMessage(), '/problems/internal-error', 'Internal Server Error');
        }
    }

    #[Route('/discord/bot', name: 'api_discord_bot', methods: ['POST'])]
    public function discordBot(Request $request): JsonResponse
    {
        // Lire le secret depuis le header
        $botSecret = $request->headers->get('X-Bot-Secret');

        if (!$botSecret) {
            throw ApiProblemException::unauthorized('Missing X-Bot-Secret header');
        }

        if (!$this->discordOAuthService->validateBotSecret($botSecret)) {
            $this->logger->warning('Discord bot auth failed: invalid secret');
            throw ApiProblemException::unauthorized('Invalid bot secret');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['discord_id'])) {
            throw ApiProblemException::validationError('discord_id is required', [
                ['field' => 'discord_id', 'message' => 'This value should not be blank.'],
            ]);
        }

        $user = $this->discordOAuthService->getUserByDiscordId($data['discord_id']);

        if (!$user) {
            $this->logger->warning('Discord bot auth failed: user not found', ['discord_id' => $data['discord_id']]);
            throw ApiProblemException::notFound('User not found');
        }

        if (!$user->isActive()) {
            throw ApiProblemException::forbidden('User account is inactive');
        }

        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        $this->logger->info('Discord bot auth successful', ['user_id' => $user->getId(), 'discord_id' => $data['discord_id']]);

        return $this->json([
            'token' => $token,
            'user' => $this->discordOAuthService->formatUserResponse($user),
            'expires_in' => 3600
        ]);
    }
}
