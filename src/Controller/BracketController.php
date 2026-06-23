<?php

namespace App\Controller;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Exception\ApiProblemException;
use App\Repository\BracketEntryRepository;
use App\Repository\BracketRepository;
use App\Repository\GameRepository;
use App\Service\BracketGeneratorService;
use App\Service\BracketSeedingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BracketController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Bracket) {
            throw new \InvalidArgumentException('Entity must be an instance of Bracket');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'format' => $entity->getFormat(),
            'has_third_place_match' => $entity->hasThirdPlaceMatch(),
            'qualified_count' => $entity->getQualifiedCount(),
            'division_id' => $entity->getDivision()?->getId(),
            'status' => $entity->getStatus(),
        ];
    }

    private function formatGame(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'round' => $game->getRound(),
            'position' => $game->getBracketPosition(),
            'is_third_place_match' => $game->isThirdPlaceMatch(),
            'team1_id' => $game->getTeam1()?->getId(),
            'team1_name' => $game->getTeam1()?->getName(),
            'team2_id' => $game->getTeam2()?->getId(),
            'team2_name' => $game->getTeam2()?->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()?->getName(),
            'date' => $game->getDate()?->format('Y-m-d H:i:s'),
            'winner_to_game_id' => $game->getWinnerToGame()?->getId(),
            'winner_to_slot' => $game->getWinnerToSlot(),
            'loser_to_game_id' => $game->getLoserToGame()?->getId(),
            'loser_to_slot' => $game->getLoserToSlot(),
        ];
    }

    private function formatTree(Bracket $bracket, GameRepository $gameRepository, BracketEntryRepository $entryRepository): array
    {
        $data = $this->formatEntityData($bracket);

        $entries = $entryRepository->findByBracketOrderedBySeed($bracket);
        $data['seeds'] = array_map(fn (BracketEntry $e) => [
            'seed' => $e->getSeed(),
            'team_id' => $e->getTeam()?->getId(),
            'team_name' => $e->getTeam()?->getName(),
        ], $entries);

        $games = $gameRepository->findByBracket($bracket);
        $rounds = [];
        foreach ($games as $game) {
            $rounds[$game->getRound()][] = $this->formatGame($game);
        }
        ksort($rounds);
        $data['rounds'] = array_map(fn ($round, $matches) => [
            'round' => $round,
            'matches' => $matches,
        ], array_keys($rounds), $rounds);

        return $data;
    }

    #[Route('/brackets', name: 'app_bracket_create', methods: ['POST'])]
    public function createBracket(Request $request): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $data = $this->getRequestData($request);

        if (!isset($data['name'])) {
            return $this->missingParameterError('name');
        }
        if (!isset($data['qualified_count'])) {
            return $this->missingParameterError('qualified_count');
        }
        if ((int) $data['qualified_count'] < 2) {
            throw ApiProblemException::badRequest('qualified_count must be at least 2');
        }

        $bracket = new Bracket();
        $bracket->setName($data['name']);
        $bracket->setQualifiedCount((int) $data['qualified_count']);
        $bracket->setHasThirdPlaceMatch((bool) ($data['has_third_place_match'] ?? false));
        if (isset($data['format'])) {
            $bracket->setFormat($data['format']);
        }
        if (isset($data['division_id'])) {
            $division = $this->findEntityOrFail('App\Entity\Division', $data['division_id'], 'Division');
            $bracket->setDivision($division);
        }

        return $this->securedCreateEntity($bracket, $request);
    }

    #[Route('/brackets/{id}', name: 'app_bracket_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getBracket(int $id, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository));
    }

    #[Route('/divisions/{id}/bracket', name: 'app_division_bracket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getDivisionBracket(int $id, BracketRepository $bracketRepository, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $division = $this->findEntityOrFail('App\Entity\Division', $id, 'Division');
        $bracket = $bracketRepository->findOneByDivision($division);
        if (!$bracket) {
            throw ApiProblemException::notFound('No bracket for this division');
        }

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository));
    }

    #[Route('/brackets/{id}/seed', name: 'app_bracket_seed', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function seedBracket(int $id, BracketSeedingService $seedingService, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');
        $division = $bracket->getDivision();
        if ($division === null) {
            throw ApiProblemException::badRequest('This bracket is not linked to a division; use PUT /entries for manual seeding');
        }

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $existing) {
            $this->entityManager->remove($existing);
        }
        $this->entityManager->flush();

        $teams = $seedingService->computeSeeds($division, $bracket->getQualifiedCount());
        if (count($teams) < 2) {
            throw ApiProblemException::badRequest('Not enough ranked teams in the division to seed');
        }

        $seed = 1;
        foreach ($teams as $team) {
            $entry = new BracketEntry();
            $entry->setBracket($bracket);
            $entry->setSeed($seed);
            $entry->setTeam($team);
            $this->entityManager->persist($entry);
            $seed++;
        }
        $this->entityManager->flush();

        return $this->json(['seeded' => count($teams)]);
    }

    #[Route('/brackets/{id}/entries', name: 'app_bracket_entries', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function setEntries(int $id, Request $request, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');
        $data = $this->getRequestData($request);

        if (!isset($data['entries']) || !is_array($data['entries']) || count($data['entries']) < 2) {
            throw ApiProblemException::badRequest('entries must be an array of at least 2 {seed, team_id}');
        }

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $existing) {
            $this->entityManager->remove($existing);
        }
        $this->entityManager->flush();

        foreach ($data['entries'] as $row) {
            if (!isset($row['seed'], $row['team_id'])) {
                throw ApiProblemException::badRequest('Each entry needs seed and team_id');
            }
            $team = $this->findEntityOrFail('App\Entity\Team', $row['team_id'], 'Team');
            $entry = new BracketEntry();
            $entry->setBracket($bracket);
            $entry->setSeed((int) $row['seed']);
            $entry->setTeam($team);
            $this->entityManager->persist($entry);
        }
        $this->entityManager->flush();

        return $this->json(['entries' => count($data['entries'])]);
    }

    #[Route('/brackets/{id}/generate', name: 'app_bracket_generate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generateBracket(int $id, BracketGeneratorService $generatorService, BracketEntryRepository $entryRepository, GameRepository $gameRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        if (!empty($gameRepository->findByBracket($bracket))) {
            throw ApiProblemException::conflict('Bracket already generated');
        }

        $generatorService->generate($bracket, $entryRepository);

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository), 201);
    }

    #[Route('/brackets/{id}', name: 'app_bracket_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteBracket(int $id, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        foreach ($gameRepository->findByBracket($bracket) as $game) {
            $this->entityManager->remove($game);
        }
        $this->entityManager->flush();

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $entry) {
            $this->entityManager->remove($entry);
        }
        $this->entityManager->flush();

        return $this->securedDeleteEntity($bracket, 'Bracket');
    }
}
