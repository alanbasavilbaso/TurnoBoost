<?php

namespace App\Repository;

use App\Entity\Professional;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Professional>
 *
 * @method Professional|null find($id, $lockMode = null, $lockVersion = null)
 * @method Professional|null findOneBy(array $criteria, array $orderBy = null)
 * @method Professional[]    findAll()
 * @method Professional[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfessionalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Professional::class);
    }

    /**
     * Encuentra profesionales por locales
     */
    public function findByLocation($location, array $orderBy = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.location = :location')
            ->setParameter('location', $location);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('p.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra profesionales activos por locales
     */
    public function findActiveByLocation($location): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.location = :location')
            ->andWhere('p.active = :active')
            ->setParameter('location', $location)
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca profesionales por nombre o especialidad
     */
    public function searchByNameOrSpecialty($location, string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.location = :location')
            ->andWhere('p.name LIKE :searchTerm OR p.specialty LIKE :searchTerm')
            ->setParameter('location', $location)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta profesionales por locales
     */
    public function countByLocation($location): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra profesionales con citas en un rango de fechas
     */
    public function findWithAppointmentsInDateRange($location, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.appointments', 'a')
            ->andWhere('p.location = :location')
            ->andWhere('a.appointmentDate BETWEEN :startDate AND :endDate')
            ->setParameter('location', $location)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}