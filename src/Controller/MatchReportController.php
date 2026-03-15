<?php

namespace App\Controller;

use App\Entity\MatchReport;
use App\Exception\ApiProblemException;
use App\Repository\GameStatusRepository;
use App\Repository\MatchReportRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class MatchReportController extends BaseController
{
    private const MAX_REPORTS_PER_SEASON = 2;

    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof MatchReport) {
            throw new \InvalidArgumentException('Entity must be an instance of MatchReport');
        }

        return [
            'id' => $entity->getId(),
            'game_id' => $entity->getGame()?->getId(),
            'team_id' => $entity->getTeam()?->getId(),
            'team_name' => $entity->getTeam()?->getName(),
            'requested_by_id' => $entity->getRequestedBy()?->getId(),
            'requested_by_username' => $entity->getRequestedBy()?->getDiscordUsername(),
            'reason' => $entity->getReason(),
            'is_admin_forced' => $entity->isAdminForced(),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * POST /api/games/{id}/report - Un capitaine reporte un match
     */
    #[Route('/games/{id}/report', name: 'app_game_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reportGame(
        int $id,
        Request $request,
        MatchReportRepository $reportRepository,
        GameStatusRepository $gameStatusRepository,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $team = null;
        if ($team1 && $team1->isCaptain($user)) {
            $team = $team1;
        } elseif ($team2 && $team2->isCaptain($user)) {
            $team = $team2;
        }

        if (!$team) {
            throw ApiProblemException::forbidden('You must be a captain of one of the teams in this game');
        }

        $season = $game->getDivision()?->getSeason();
        if ($season && $reportRepository->countByTeamAndSeason($team, $season) >= self::MAX_REPORTS_PER_SEASON) {
            throw ApiProblemException::badRequest('Your team has reached the maximum number of reports for this season (2)');
        }

        $data = $this->getRequestData($request);

        $report = new MatchReport();
        $report->setGame($game);
        $report->setTeam($team);
        $report->setRequestedBy($user);
        $report->setReason($data['reason'] ?? null);
        $report->setIsAdminForced(false);

        $game->setDate(null);
        $reportedStatus = $gameStatusRepository->findOneBy(['name' => 'reported']);
        if ($reportedStatus) {
            $game->setStatus($reportedStatus);
        }

        $this->entityManager->persist($report);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($report), 201);
    }

    /**
     * POST /api/games/{id}/admin-report - Admin force un report pour les 2 equipes
     */
    #[Route('/games/{id}/admin-report', name: 'app_game_admin_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminReportGame(
        int $id,
        Request $request,
        MatchReportRepository $reportRepository,
        GameStatusRepository $gameStatusRepository,
    ): JsonResponse {
        $this->checkUserRole('ROLE_ADMIN');

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        if (!$team1 || !$team2) {
            throw ApiProblemException::badRequest('Game must have two teams assigned');
        }

        $user = $this->getAuthenticatedUser();
        $data = $this->getRequestData($request);
        $reason = $data['reason'] ?? null;

        $report1 = new MatchReport();
        $report1->setGame($game);
        $report1->setTeam($team1);
        $report1->setRequestedBy($user);
        $report1->setReason($reason);
        $report1->setIsAdminForced(true);

        $report2 = new MatchReport();
        $report2->setGame($game);
        $report2->setTeam($team2);
        $report2->setRequestedBy($user);
        $report2->setReason($reason);
        $report2->setIsAdminForced(true);

        $game->setDate(null);
        $reportedStatus = $gameStatusRepository->findOneBy(['name' => 'reported']);
        if ($reportedStatus) {
            $game->setStatus($reportedStatus);
        }

        $this->entityManager->persist($report1);
        $this->entityManager->persist($report2);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json([
            $this->formatEntityData($report1),
            $this->formatEntityData($report2),
        ], 201);
    }

    /**
     * GET /api/games/{id}/reports - Liste les reports d'un match
     */
    #[Route('/games/{id}/reports', name: 'app_game_reports', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGameReports(int $id, MatchReportRepository $reportRepository): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $reports = $reportRepository->findByGame($game);
        $data = array_map(fn($report) => $this->formatEntityData($report), $reports);

        return $this->json($data);
    }

    /**
     * GET /api/teams/{id}/reports?season_id=X - Liste les reports d'une equipe pour une saison
     */
    #[Route('/teams/{id}/reports', name: 'app_team_reports', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getTeamReports(
        int $id,
        Request $request,
        MatchReportRepository $reportRepository,
    ): JsonResponse {
        $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');

        $seasonId = $request->query->get('season_id');
        if (!$seasonId) {
            throw ApiProblemException::badRequest('season_id query parameter is required');
        }

        $season = $this->findEntityOrFail('App\Entity\Season', $seasonId, 'Season');

        $reports = $reportRepository->findByTeamAndSeason($team, $season);
        $count = count($reports);

        return $this->json([
            'reports' => array_map(fn($report) => $this->formatEntityData($report), $reports),
            'count' => $count,
            'remaining' => self::MAX_REPORTS_PER_SEASON - $count,
        ]);
    }
}
