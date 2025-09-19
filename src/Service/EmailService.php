<?php

namespace App\Service;

use App\Entity\Appointment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private AppointmentService $appointmentService
    ) {}

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
            'confirm_url' => $this->generateConfirmUrl($appointment), // Usar el ID original para los enlaces
            'cancel_url' => $this->generateCancelUrl($appointment),
            'modify_url' => $canBeModified ? $this->generateModifyUrl($appointment) : null,
            
            'reschedule_website_url' => $_ENV['APP_URL']  . $company->getDomain(),
            'can_be_modified' => $canBeModified,
            
            'modification_count' => $modificationCount,
            'appointment_id' => $activeAppointment->getId(),
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        $email = (new Email())
            ->from($fromAddress)
            ->to($toAddress)
            ->subject('Confirmación de tu cita - ' . $service->getName())
            ->html($htmlContent);
        
        // Agregar header X-Notification-ID si se proporciona
        if ($notificationId !== null) {
            $email->getHeaders()->addTextHeader('X-Notification-ID', (string)$notificationId);
        }

        // Agregar BCC si está configurado en variable de entorno
        if (!empty($_ENV['MAIL_BCC_DEBUG'])) {
            $email->bcc($_ENV['MAIL_BCC_DEBUG']);
        }
            
        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Re-lanzar la excepción para que el handler la capture
            throw new \Exception('Failed to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function sendAppointmentNotification(Appointment $appointment, string $type, ?int $notificationId = null): void
    {
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
            // AGREGAR LAS URLs QUE FALTAN:
            'cancel_url' => $this->generateCancelUrl($appointment),
            'modify_url' => $canBeModified ? $this->generateModifyUrl($appointment) : null,

            'confirm_url' => $this->generateConfirmUrl($appointment),
            'can_be_modified' => $canBeModified,
            'reschedule_website_url' => $_ENV['APP_URL'] . $company->getDomain(),
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        $email = (new Email())
            ->from($fromAddress)
            ->to($toAddress)
            ->subject($subject)
            ->html($htmlContent);

        // Agregar header X-Notification-ID si se proporciona
        if ($notificationId !== null) {
            $email->getHeaders()->addTextHeader('X-Notification-ID', (string)$notificationId);
        }
        
        // Agregar BCC si está configurado en variable de entorno
        if (!empty($_ENV['MAIL_BCC_DEBUG'])) {
            $email->bcc($_ENV['MAIL_BCC_DEBUG']);
        }
            
        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Re-lanzar la excepción para que el handler la capture
            throw new \Exception('Failed to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getSubjectForType(string $type): string
    {
        return match($type) {
            'confirmation' => 'Confirmación de tu turno',
            'reminder' => 'Recordatorio de tu turno',
            'urgent_reminder' => 'Recordatorio urgente de tu turno',
            'cancellation' => 'Cancelación de tu turno',
            'modification' => 'Modificación de tu turno',
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
            default => 'emails/appointment_confirmation.html.twig'
        };
    }

    /**
     * Formatea una fecha en español con formato completo
     */
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
     * Generar token de seguridad para una cita
     */
    private function generateAppointmentToken(Appointment $appointment): string
    {
        $data = $appointment->getId() . 
                $appointment->getPatient()->getEmail() . 
                $appointment->getScheduledAt()->format('Y-m-d H:i:s') .
                $appointment->getCompany()->getId();
        
        return hash('sha256', $data . ($_ENV['APP_SECRET'] ?? 'default_secret'));
    }

    /**
     * Generar URL de confirmación
     */
    private function generateConfirmUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        return $_ENV['APP_URL'] . "{$domain}/confirm/{$appointment->getId()}/{$token}";
    }

    /**
     * Generar URL de cancelación
     */
    private function generateCancelUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        return $_ENV['APP_URL'] . "{$domain}/cancel/{$appointment->getId()}/{$token}";
    }

    /**
     * Generar URL de modificación
     */
    private function generateModifyUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        // Usar la cita original para generar el token
        $rootAppointment = $appointment->getRootAppointment();
        $token = $this->generateAppointmentToken($rootAppointment);
        return $_ENV['APP_URL'] . "{$domain}/modify/{$rootAppointment->getId()}/{$token}";
    }
}