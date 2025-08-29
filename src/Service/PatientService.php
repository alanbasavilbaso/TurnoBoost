<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\Clinic;
use Doctrine\ORM\EntityManagerInterface;

class PatientService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findOrCreatePatient(array $patientData, Clinic $clinic): Patient
    {
        if (isset($patientData['id']) && $patientData['id']) {
            $patient = $this->entityManager->getRepository(Patient::class)->find($patientData['id']);
            
            if ($patient && $patient->getClinic() === $clinic) {
                // Si el paciente existe, simplemente lo devolvemos sin modificar nada
                return $patient;
            }
        }
        
        // Buscar paciente existente por email o teléfono
        if (isset($patientData['email']) || isset($patientData['phone'])) {
            $qb = $this->entityManager->getRepository(Patient::class)->createQueryBuilder('p')
                ->where('p.clinic = :clinic')
                ->setParameter('clinic', $clinic);
            
            if (isset($patientData['email'])) {
                $qb->andWhere('p.email = :email')
                   ->setParameter('email', $patientData['email']);
            }
            
            if (isset($patientData['phone'])) {
                $qb->orWhere('p.phone = :phone')
                   ->setParameter('phone', $patientData['phone']);
            }
            
            $existingPatient = $qb->getQuery()->getOneOrNullResult();
            
            if ($existingPatient) {
                return $existingPatient;
            }
        }
        
        // Crear nuevo paciente solo si no existe
        $patient = new Patient();
        $patient->setClinic($clinic)
                ->setName($patientData['name'])
                ->setEmail($patientData['email'] ?? null)
                ->setPhone($patientData['phone'] ?? null);
                
        $this->entityManager->persist($patient);
        
        return $patient;
    }
    
    public function searchPatients(string $query, Clinic $clinic, int $limit = 10): array
    {
        return $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.clinic = :clinic')
            ->andWhere('(
                p.name LIKE :query OR 
                p.email LIKE :query OR 
                p.phone LIKE :query
            )')
            ->setParameter('clinic', $clinic)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}