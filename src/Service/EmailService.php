<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\RoleEnum;
use App\Service\BrevoEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private BrevoEmailService $brevoEmailService,
        private Environment $twig,
        private AppointmentService $appointmentService,
        private UrlGeneratorService $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Enviar notificación a la empresa sobre nuevos turnos o cancelaciones
     */
    public function sendCompanyNotification(Appointment $appointment, string $type): void
    {
        $company = $appointment->getCompany();
        
        // Buscar el email del usuario owner con rol admin o super
        $ownerEmail = $this->getCompanyOwnerEmail($company);
        // Verificar si la empresa tiene email configurado y notificaciones habilitadas
        if (!$ownerEmail || !$company->getReceiveEmailNotifications()) {
            return; // No hay email de owner disponible
        }
        
        $patient = $appointment->getPatient();
        $professional = $appointment->getProfessional();
        $service = $appointment->getService();
        $location = $appointment->getLocation();
        
        // Formatear fecha en español
        $scheduledAt = $appointment->getScheduledAt();
        $formattedDate = $this->formatDateInSpanish($scheduledAt);
        
        $subject = match($type) {
            'company_new_booking' => 'Nuevo turno reservado - ' . $service->getName(),
            'company_cancellation' => 'Turno cancelado - ' . $service->getName(),
            default => 'Notificación de turno'
        };
        
        $template = match($type) {
            'company_new_booking' => 'emails/company_new_booking.html.twig',
            'company_cancellation' => 'emails/company_cancellation.html.twig',
            default => 'emails/company_notification.html.twig'
        };
        
        $htmlContent = $this->twig->render($template, [
            'business_name' => $company->getName(),
            'service_name' => $service->getName(),
            'appointment_date' => $scheduledAt->format('d/m/Y'),
            'appointment_date_formatted' => $formattedDate,
            'appointment_time' => $scheduledAt->format('H:i'),
            'professional_name' => $professional->getName(),
            'location_name' => $location->getName(),
            'location_address' => $location->getAddress(),
            'patient_first_name' => $patient->getFirstName(),
            'patient_last_name' => $patient->getLastName(),
            'patient_email' => $patient->getEmail(),
            'patient_phone' => $patient->getPhone(),
            'primary_color' => $company->getPrimaryColor(),
            'domain' => $company->getDomain(),
            'appointment_id' => $appointment->getId(),
            'type' => $type,
        ]);
        
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        
        $this->brevoEmailService->sendEmail(
            $ownerEmail,
            $subject,
            $htmlContent,
            $fromAddress
        );
    }

    public function sendAppointmentConfirmation(Appointment $appointment, ?int $notificationId = null): void
    {
        // Obtener el turno activo de la cadena
        $activeAppointment = $this->appointmentService->findActiveAppointmentFromChain($appointment->getId());
        
        // Si no se encuentra un turno activo, usar el turno original
        if (!$activeAppointment) {
            $activeAppointment = $appointment;
        }
        
        $patient = $activeAppointment->getPatient();
        $professional = $activeAppointment->getProfessional();
        $service = $activeAppointment->getService();
        $location = $activeAppointment->getLocation();
        $company = $activeAppointment->getCompany();
        
        // Formatear fecha en español
        $scheduledAt = $activeAppointment->getScheduledAt();
        $formattedDate = $this->formatDateInSpanish($scheduledAt);
        
        // Verificar si la cita puede ser modificada
        $canBeModified = $this->appointmentService->canAppointmentBeModified($activeAppointment, $company);
        
        // Calcular el número de modificaciones realizadas
        $modificationCount = $activeAppointment->getModificationCount();
        
        // Generar URLs solo si son válidas
        $confirmUrl = $this->urlGenerator->generateConfirmUrl($appointment);
        $cancelUrl = $this->urlGenerator->generateCancelUrl($appointment);

        $htmlContent = $this->twig->render('emails/appointment_confirmation.html.twig', [
            'business_name' => $company->getName(),
            'service_name' => $service->getName(),
            'appointment_date' => $scheduledAt->format('d/m/Y'),
            'appointment_date_formatted' => $formattedDate,
            'appointment_time' => $scheduledAt->format('H:i'),
            'professional_name' => $professional->getName(),
            'location_name' => $location->getName(),
            'location_address' => $location->getAddress(),
            'patient_first_name' => $patient->getFirstName(),
            'patient_last_name' => $patient->getLastName(),
            'patient_email' => $patient->getEmail(),
            'patient_phone' => $patient->getPhone(),
            'phone_number' => $location->getPhone() ?? '+54 11 1234-5678',
            'primary_color' => $company->getPrimaryColor(),
            'domain' => $company->getDomain(),
            // Políticas de la empresa
            'cancellable_bookings' => $company->isCancellableBookings(),
            'editable_bookings' => $company->isEditableBookings(),
            'minimum_edit_hours' => round($company->getMinimumEditTime() / 60),
            'maximum_edits' => $company->getMaximumEdits(),
            'confirm_url' => $confirmUrl,
            'cancel_url' => $cancelUrl,
            'modify_url' => $canBeModified ? $this->generateModifyUrl($appointment) : null,
            
            'reschedule_website_url' => $_ENV['APP_URL']  . $company->getDomain(),
            'can_be_modified' => $canBeModified,
            
            'modification_count' => $modificationCount,
            'appointment_id' => $activeAppointment->getId(),
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        $subject = 'Confirmación de tu cita - ' . $service->getName();
        
        // Usar BrevoEmailService en lugar de MailerInterface
        $this->brevoEmailService->sendEmail(
            $toAddress,
            $subject,
            $htmlContent,
            $fromAddress,
            $notificationId
        );
    }

    public function sendAppointmentNotification(Appointment $appointment, string $type, ?int $notificationId = null): void
    {
        // Manejar notificaciones de empresa
        if ($type === 'company_new_booking' || $type === 'company_cancellation') {
            $this->sendCompanyNotification($appointment, $type);
            return;
        }

        // Mantener el método existente para compatibilidad
        if ($type === 'confirmation') {
            $this->sendAppointmentConfirmation($appointment, $notificationId);
            return;
        }
        
        // Obtener el turno activo de la cadena
        $activeAppointment = $this->appointmentService->findActiveAppointmentFromChain($appointment->getId());
        
        // Si no se encuentra un turno activo, usar el turno original
        if (!$activeAppointment) {
            $activeAppointment = $appointment;
        }
        
        $patient = $activeAppointment->getPatient();
        $professional = $activeAppointment->getProfessional();
        $service = $activeAppointment->getService();
        $location = $activeAppointment->getLocation();
        $company = $activeAppointment->getCompany();
        
        // Verificar si la cita puede ser modificada
        $canBeModified = $this->appointmentService->canAppointmentBeModified($activeAppointment, $company);
        
        // Calcular el número de modificaciones realizadas
        $modificationCount = $activeAppointment->getModificationCount();
        
        $subject = $this->getSubjectForType($type);
        $template = $this->getTemplateForType($type);
        
        $scheduledAt = $activeAppointment->getScheduledAt();
        $formattedDate = $this->formatDateInSpanish($scheduledAt);

        // Generar URLs solo si son válidas
        $confirmUrl = $this->urlGenerator->generateConfirmUrl($appointment);
        $cancelUrl = $this->urlGenerator->generateCancelUrl($appointment);

        $htmlContent = $this->twig->render($template, [
            'business_name' => $company->getName(),
            'service_name' => $service->getName(),
            'appointment_date' => $activeAppointment->getScheduledAt()->format('d/m/Y'),
            'appointment_date_formatted' => $formattedDate,
            'appointment_time' => $activeAppointment->getScheduledAt()->format('H:i'),
            'professional_name' => $professional->getName(),
            'location_name' => $location->getName(),
            'location_address' => $location->getAddress(),
            'patient_first_name' => $patient->getFirstName(),
            'patient_last_name' => $patient->getLastName(),
            'patient_email' => $patient->getEmail(),
            'patient_phone' => $patient->getPhone(),
            'phone_number' => $location->getPhone() ?? '+54 11 1234-5678',
            'primary_color' => $company->getPrimaryColor(),
            'domain' => $company->getDomain(),
            // Políticas de la empresa
            'cancellable_bookings' => $company->isCancellableBookings(),
            'editable_bookings' => $company->isEditableBookings(),
            'minimum_edit_hours' => round($company->getMinimumEditTime() / 60),
            'maximum_edits' => $company->getMaximumEdits(),
            'modification_count' => $modificationCount,
            'type' => $type,
            'appointment_id' => $activeAppointment->getId(),
            'cancel_url' => $cancelUrl,
            'modify_url' => $canBeModified ? $this->generateModifyUrl($appointment) : null,
            'confirm_url' => $confirmUrl,
            'can_be_modified' => $canBeModified,
            'reschedule_website_url' => $_ENV['APP_URL'] . $company->getDomain(),
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        // Usar BrevoEmailService en lugar de MailerInterface
        $this->brevoEmailService->sendEmail(
            $toAddress,
            $subject,
            $htmlContent,
            $fromAddress,
            $notificationId
        );
    }

    private function getSubjectForType(string $type): string
    {
        return match($type) {
            'confirmation' => 'Confirmación de tu turno',
            'reminder' => 'Recordatorio de tu turno',
            'urgent_reminder' => 'Recordatorio urgente de tu turno',
            'cancellation' => 'Cancelación de tu turno',
            'modification' => 'Modificación de tu turno',
            'company_new_booking' => 'Nuevo turno reservado',
            'company_cancellation' => 'Turno cancelado',
            default => 'Notificación de turno'
        };
    }
    
    private function getTemplateForType(string $type): string
    {
        return match($type) {
            'confirmation' => 'emails/appointment_confirmation.html.twig',
            'reminder' => 'emails/appointment_reminder.html.twig',
            'urgent_reminder' => 'emails/appointment_urgent_reminder.html.twig',
            'cancellation' => 'emails/appointment_cancellation.html.twig',
            'modification' => 'emails/appointment_modification.html.twig',
            'company_new_booking' => 'emails/company_new_booking.html.twig',
            'company_cancellation' => 'emails/company_cancellation.html.twig',
            default => 'emails/appointment_confirmation.html.twig'
        };
    }

    /**
     * Formatea una fecha en español con formato completo
     */
    /**
     * Obtener el email del usuario owner con rol admin o super de la empresa
     */
    private function getCompanyOwnerEmail($company): ?string
    {
        $owner = $this->entityManager->getRepository(\App\Entity\User::class)
            ->createQueryBuilder('u')
            ->where('u.company = :company')
            ->andWhere('u.isOwner = :isOwner')
            ->andWhere('u.role IN (:roles)')
            ->setParameter('company', $company)
            ->setParameter('isOwner', true)
            ->setParameter('roles', [RoleEnum::ADMIN])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
            
        return $owner?->getEmail();
    }

    private function formatDateInSpanish(\DateTime $date): string
    {
        $dayNames = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes', 
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        
        $monthNames = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre'
        ];
        
        $dayName = $dayNames[$date->format('l')] ?? $date->format('l');
        $monthName = $monthNames[$date->format('F')] ?? $date->format('F');
        
        return sprintf('%s, %d de %s de %s', 
            $dayName, 
            $date->format('d'), 
            $monthName, 
            $date->format('Y')
        );
    }

    /**
     * Generar URL de modificación
     */
    private function generateModifyUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        // Usar la cita original para generar el token
        $rootAppointment = $appointment->getRootAppointment();
        $token = $this->urlGenerator->generateAppointmentToken($rootAppointment);
        return $_ENV['APP_URL'] . "{$domain}/modify/{$rootAppointment->getId()}/{$token}";
    }
}