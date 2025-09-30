<?php

namespace App\Service;

use App\Entity\Appointment;

class UrlGeneratorService
{
    /**
     * Generar token seguro para una cita
     */
    public function generateAppointmentToken(Appointment $appointment): string
    {
        $data = $appointment->getId() . 
                $appointment->getPatient()->getEmail() . 
                $appointment->getScheduledAt()->format('Y-m-d H:i:s') .
                $appointment->getCompany()->getId();
        
        return hash('sha256', $data . ($_ENV['APP_SECRET'] ?? 'default_secret'));
    }

    /**
     * Generar URL de confirmación
     * Retorna null si el turno ya está confirmado
     */
    public function generateConfirmUrl(Appointment $appointment): ?string
    {
        // Si ya está confirmado (tiene confirmed_at), no generar URL
        if ($appointment->getConfirmedAt() !== null) {
            return null;
        }
        
        // Si está cancelado (tiene cancelled_at), no generar URL
        if ($appointment->getCancelledAt() !== null) {
            return null;
        }
        
        $domain = $appointment->getCompany()->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        $baseUrl = $_ENV['APP_URL'] ?? 'https://turnoboost.com';
        return $baseUrl . "/{$domain}/confirm/{$appointment->getId()}/{$token}";
    }

    /**
     * Generar URL de cancelación
     * Retorna null si no se puede cancelar según las políticas de la empresa
     */
    public function generateCancelUrl(Appointment $appointment): ?string
    {
        $company = $appointment->getCompany();
        
        // Verificar si la empresa permite cancelaciones
        if (!$company->isCancellableBookings()) {
            return null;
        }
        
        // Si ya está cancelado (tiene cancelled_at), no generar URL
        if ($appointment->getCancelledAt() !== null) {
            return null;
        }
        
        // Verificar tiempo mínimo para cancelar
        if (!$company->canCancelAppointment($appointment->getScheduledAt())) {
            return null;
        }
        
        $domain = $company->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        $baseUrl = $_ENV['APP_URL'] ?? 'https://turnoboost.com';
        return $baseUrl . "/{$domain}/cancel/{$appointment->getId()}/{$token}";
    }
}