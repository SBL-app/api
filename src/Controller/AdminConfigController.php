<?php

namespace App\Controller;

use App\Entity\BotSetting;
use App\Exception\ApiProblemException;
use App\Repository\BotSettingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/config')]
class AdminConfigController extends BaseController
{
    private const DEFAULTS = [
        'matchs_channel_id' => null,
        'classement_channel_id' => null,
        'match_manager_role_id' => null,
        'default_match_day' => 'sunday',
        'default_match_time' => '21:00',
        'deadline_day' => 'thursday',
    ];
    private const SNOWFLAKES = ['matchs_channel_id', 'classement_channel_id', 'match_manager_role_id'];
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected function formatEntityData($entity): array
    {
        throw new \LogicException('Not supported: this controller exposes settings only.');
    }

    /** GET /api/admin/config - Configuration du bot (ROLE_ADMIN). */
    #[Route('', name: 'api_admin_config_get', methods: ['GET'])]
    public function get(BotSettingRepository $repo): JsonResponse
    {
        $this->requireAdmin();

        return $this->json($this->read($repo));
    }

    /** PUT /api/admin/config - Mise à jour partielle (ROLE_ADMIN). */
    #[Route('', name: 'api_admin_config_put', methods: ['PUT'])]
    public function put(Request $request, BotSettingRepository $repo): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->getRequestData($request);

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                throw ApiProblemException::badRequest("Unknown setting: $key");
            }
            if (!$this->isValid($key, $value)) {
                throw ApiProblemException::badRequest("Invalid value for $key");
            }
        }

        $em = $this->entityManager;
        foreach ($data as $key => $value) {
            $setting = $repo->find($key) ?? new BotSetting($key);
            $setting->setValue($value);
            $em->persist($setting);
        }
        $em->flush();

        return $this->json($this->read($repo));
    }

    private function requireAdmin(): void
    {
        // 401 explicite : une AccessDeniedException serait convertie en 500 par le listener.
        if (null === $this->getUser()) {
            throw ApiProblemException::unauthorized('Authentication required');
        }
        $this->checkUserRole('ROLE_ADMIN');
    }

    private function isValid(string $key, mixed $value): bool
    {
        if (in_array($key, self::SNOWFLAKES, true)) {
            return null === $value || (is_string($value) && 1 === preg_match('/^\d{17,20}$/', $value));
        }
        if (!is_string($value)) {
            return false;
        }
        if ('default_match_time' === $key) {
            return 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
        }

        return in_array($value, self::DAYS, true);
    }

    private function read(BotSettingRepository $repo): array
    {
        $result = self::DEFAULTS;
        foreach ($repo->findAll() as $setting) {
            if (array_key_exists($setting->getKey(), $result)) {
                $result[$setting->getKey()] = $setting->getValue();
            }
        }

        return $result;
    }
}
