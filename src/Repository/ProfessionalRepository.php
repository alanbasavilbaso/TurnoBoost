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
     * Encuentra profesionales por clínica
     */
    public function findByClinic($clinic, array $orderBy = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.clinic = :clinic')
            ->setParameter('clinic', $clinic);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('p.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra profesionales activos por clínica
     */
    public function findActiveByClinic($clinic): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.clinic = :clinic')
            ->andWhere('p.active = :active')
            ->setParameter('clinic', $clinic)
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca profesionales por nombre o especialidad
     */
    public function searchByNameOrSpecialty($clinic, string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.clinic = :clinic')
            ->andWhere('p.name LIKE :searchTerm OR p.specialty LIKE :searchTerm')
            ->setParameter('clinic', $clinic)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta profesionales por clínica
     */
    public function countByClinic($clinic): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.clinic = :clinic')
            ->setParameter('clinic', $clinic)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra profesionales con citas en un rango de fechas
     */
    public function findWithAppointmentsInDateRange($clinic, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.appointments', 'a')
            ->andWhere('p.clinic = :clinic')
            ->andWhere('a.appointmentDate BETWEEN :startDate AND :endDate')
            ->setParameter('clinic', $clinic)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}