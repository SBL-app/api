<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Classe de base pour tous les contrôleurs de l'API
 * Implémente les principes SOLID et DRY
 */
abstract class BaseController extends AbstractController
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Décode le contenu JSON de la requête
     */
    protected function getRequestData(Request $request): array
    {
        $content = $request->getContent();
        if (empty($content)) {
            return [];
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON format');
        }
        
        return $data ?? [];
    }

    /**
     * Valide qu'une entité existe
     */
    protected function validateEntity($entity, string $entityName, $id): JsonResponse|null
    {
        if (!$entity) {
            return $this->json(['error' => "{$entityName} with id {$id} not found"], 404);
        }
        return null;
    }

    /**
     * Retourne une erreur de paramètre manquant
     */
    protected function missingParameterError(string $parameterName): JsonResponse
    {
        return $this->json(['error' => "{$parameterName} is required"], 400);
    }

    /**
     * Retourne une erreur d'entité non trouvée
     */
    protected function notFoundError(string $entityName): JsonResponse
    {
        return $this->json(['error' => "{$entityName} not found"], 404);
    }

    /**
     * Retourne un message de succès pour la suppression
     */
    protected function deleteSuccessResponse(string $entityName): JsonResponse
    {
        return $this->json(['message' => "{$entityName} deleted successfully"]);
    }

    /**
     * Trouve une entité par son ID ou retourne une erreur
     */
    protected function findEntityOrFail(string $repositoryClass, $id, string $entityName)
    {
        $repository = $this->entityManager->getRepository($repositoryClass);
        $entity = $repository->find($id);
        
        if (!$entity) {
            throw new \Exception("{$entityName} with id {$id} not found", 404);
        }
        
        return $entity;
    }

    /**
     * Persiste et flush une entité
     */
    protected function saveEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Supprime et flush une entité
     */
    protected function deleteEntity($entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * Méthode abstraite pour le formatage des données de l'entité
     */
    abstract protected function formatEntityData($entity): array;

    /**
     * Retourne toutes les entités formatées
     */
    protected function getAllEntities(string $repositoryClass): array
    {
        $repository = $this->entityManager->getRepository($repositoryClass);
        $entities = $repository->findAll();
        
        return array_map(function ($entity) {
            return $this->formatEntityData($entity);
        }, $entities);
    }

    /**
     * Retourne une entité par ID formatée
     */
    protected function getEntityById(string $repositoryClass, $id, string $entityName): JsonResponse
    {
        try {
            $entity = $this->findEntityOrFail($repositoryClass, $id, $entityName);
            return $this->json($this->formatEntityData($entity));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
