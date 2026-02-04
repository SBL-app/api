<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use App\Service\DiscordOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

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
        private RateLimiterFactory $authApiKeyLimiter
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Rate limiting basé sur l'IP
        $limiter = $this->authLoginLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json([
                'error' => 'Too many login attempts. Please try again later.'
            ], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json([
                'error' => 'Username and password are required'
            ], 400);
        }

        $user = $this->userRepository->findByUsernameOrApiKey($data['username']);

        if (!$user || !$user->isActive()) {
            throw new BadCredentialsException('Invalid credentials');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            throw new BadCredentialsException('Invalid credentials');
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

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
            return $this->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        if (!$this->authService->canTokenBeRefreshed($token)) {
            return $this->json(['error' => 'Token is too old to refresh. Please login again.'], 401);
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            return $this->json(['error' => 'Invalid token or user not found'], 401);
        }

        $newToken = $this->authService->createTokenForUser($user);

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
            return $this->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        if (!$this->authService->isTokenValid($token)) {
            return $this->json([
                'valid' => false,
                'error' => 'Invalid or expired token'
            ], 401);
        }

        $user = $this->authService->getUserFromToken($token);

        if (!$user) {
            return $this->json([
                'valid' => false,
                'error' => 'User not found or inactive'
            ], 401);
        }

        try {
            $payload = $this->jwtManager->parse($token);
            return $this->json([
                'valid' => true,
                'user' => $this->authService->formatUserResponse($user),
                'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'valid' => false,
                'error' => 'Token parsing error'
            ], 401);
        }
    }

    #[Route('/login-api-key', name: 'api_login_api_key', methods: ['POST'])]
    public function loginWithApiKey(Request $request): JsonResponse
    {
        // Rate limiting basé sur l'IP
        $limiter = $this->authApiKeyLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json([
                'error' => 'Too many API key authentication attempts. Please try again later.'
            ], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['api_key'])) {
            return $this->json([
                'error' => 'API key is required'
            ], 400);
        }

        $user = $this->authService->validateApiKey($data['api_key']);

        if (!$user) {
            return $this->json(['error' => 'Invalid API key or access not authorized'], 401);
        }

        // Mettre à jour la dernière connexion
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->authService->createTokenForUser($user);

        return $this->json([
            'token' => $token,
            'user' => $this->authService->formatUserResponse($user),
            'expires_in' => 3600,
            'login_method' => 'api_key'
        ]);
    }

    #[Route('/me', name: 'api_user_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $user->isActive()
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        // Invalider tous les tokens émis avant maintenant
        $user->invalidateAllTokens();
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Successfully logged out. All tokens have been invalidated.'
        ]);
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
            return $this->json([
                'error' => 'Discord authorization failed',
                'details' => $request->query->get('error_description')
            ], 400);
        }

        if (!$code) {
            return $this->json(['error' => 'Missing authorization code'], 400);
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

            return new RedirectResponse($redirectUrl);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Discord authentication failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/discord/bot', name: 'api_discord_bot', methods: ['POST'])]
    public function discordBot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['discord_id']) || !isset($data['bot_secret'])) {
            return $this->json([
                'error' => 'discord_id and bot_secret are required'
            ], 400);
        }

        if (!$this->discordOAuthService->validateBotSecret($data['bot_secret'])) {
            return $this->json(['error' => 'Invalid bot secret'], 401);
        }

        $user = $this->discordOAuthService->getUserByDiscordId($data['discord_id']);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        if (!$user->isActive()) {
            return $this->json(['error' => 'User account is inactive'], 403);
        }

        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user' => $this->discordOAuthService->formatUserResponse($user),
            'expires_in' => 3600
        ]);
    }
}
