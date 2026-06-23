<?php

namespace App\Repository;

use App\Entity\Bracket;
use App\Entity\Division;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bracket>
 */
class BracketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bracket::class);
    }

    public function findOneByDivision(Division $division): ?Bracket
    {
        return $this->findOneBy(['division' => $division]);
    }
}
