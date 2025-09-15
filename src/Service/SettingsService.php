<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Obtiene la configuración de la empresa del usuario
     */
    public function getUserCompany(User $user): Company
    {
        return $user->getCompany();
    }

    /**
     * Guarda la configuración de la empresa
     */
    public function saveCompany(Company $company): void
    {
        $this->entityManager->persist($company);
        $this->entityManager->flush();
    }

    /**
     * Valida si una fecha de cita es válida según la configuración de la empresa
     */
    public function validateAppointmentDate(User $user, \DateTimeInterface $appointmentDate): array
    {
        $company = $this->getUserCompany($user);
        $errors = [];

        if (!$company->isWithinMinimumTime($appointmentDate)) {
            $errors[] = sprintf(
                'La cita debe ser programada con al menos %d minutos de anticipación.',
                $company->getMinimumBookingTime()
            );
        }

        if (!$company->isWithinMaximumTime($appointmentDate)) {
            $errors[] = sprintf(
                'La cita no puede ser programada para más de %d días en el futuro.',
                $company->getMaximumFutureTime()
            );
        }

        return $errors;
    }
}