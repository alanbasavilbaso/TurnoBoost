<?php

namespace App\Repository;

use App\Entity\Service;
use App\Entity\Location;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 *
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Buscar servicios por nombre y local
     */
    public function findByNameAndLocation(string $search, Location $location): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.location = :location')
            ->andWhere('LOWER(s.name) LIKE LOWER(:search) OR LOWER(s.description) LIKE LOWER(:search)')
            ->setParameter('location', $location)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNameAndCompany(string $name, Company $company): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.company = :company')
            ->andWhere('s.name LIKE :name')
            ->setParameter('company', $company)
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByCompany(Company $company): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.company = :company')
            ->andWhere('s.active = :active')
            ->setParameter('company', $company)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}