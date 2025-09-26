<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

class PatientService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findOrCreatePatient(array $patientData, Company $company): Patient
    {
        if (isset($patientData['id']) && $patientData['id']) {
            $patient = $this->entityManager->getRepository(Patient::class)->find($patientData['id']);
            
            if ($patient && $patient->getCompany() === $company && !$patient->isDeleted()) {
                // Si el paciente existe y no está eliminado, simplemente lo devolvemos sin modificar nada
                return $patient;
            }
        }
        
        // Buscar paciente existente por email o teléfono (solo pacientes no eliminados)
        if (isset($patientData['email']) || isset($patientData['phone'])) {
            $qb = $this->entityManager->getRepository(Patient::class)->createQueryBuilder('p')
                ->where('p.company = :company')
                ->andWhere('p.deletedAt IS NULL') // Solo pacientes no eliminados
                ->setParameter('company', $company);
            
            if (isset($patientData['email']) && isset($patientData['phone'])) {
                // Si tenemos ambos, buscar por ambos campos
                $qb->andWhere('p.email = :email AND p.phone = :phone')
                   ->setParameter('email', $patientData['email'])
                   ->setParameter('phone', $patientData['phone']);
            } elseif (isset($patientData['email'])) {
                // Solo email
                $qb->andWhere('p.email = :email')
                   ->setParameter('email', $patientData['email']);
            } elseif (isset($patientData['phone'])) {
                // Solo teléfono
                $qb->andWhere('p.phone = :phone')
                   ->setParameter('phone', $patientData['phone']);
            }
            
            $existingPatient = $qb->getQuery()->getOneOrNullResult();
                    
            if ($existingPatient) {
                return $existingPatient;
            }
        }

        // Crear nuevo paciente solo si no existe
        $patient = new Patient();
        $patient->setCompany($company)
                ->setFirstName($patientData['first_name'] ?? null)
                ->setLastName($patientData['last_name'] ?? null)
                ->setEmail($patientData['email'] ?? null)
                ->setPhone($patientData['phone'] ?? null);
                
        $this->entityManager->persist($patient);
        
        return $patient;
    }

    /**
     * Soft delete a patient
     */
    public function deletePatient(Patient $patient): void
    {
        $patient->delete();
        $this->entityManager->flush();
    }

    /**
     * Restore a soft deleted patient
     */
    public function restorePatient(Patient $patient): void
    {
        $patient->restore();
        $this->entityManager->flush();
    }

    /**
     * Get all active (non-deleted) patients for a company
     */
    public function getActivePatients(Company $company): array
    {
        return $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.company = :company')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('company', $company)
            ->orderBy('p.firstName', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}