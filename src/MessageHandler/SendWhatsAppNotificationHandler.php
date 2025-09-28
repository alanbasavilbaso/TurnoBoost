<?php

namespace App\MessageHandler;

use App\Message\SendWhatsAppNotification;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Appointment;
use App\Entity\Notification;
use App\Entity\NotificationStatusEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class SendWhatsAppNotificationHandler
{
    private WhatsAppService $whatsappService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        WhatsAppService $whatsappService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->whatsappService = $whatsappService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function __invoke(SendWhatsAppNotification $message): void
    {
        $notification = $this->entityManager->getRepository(Notification::class)
            ->find($message->getNotificationId());
    
        if (!$notification) {
            $this->logger->error('Notification not found for WhatsApp', [
                'notification_id' => $message->getNotificationId()
            ]);
            return;
        }
    
        $appointment = $notification->getAppointment();
    
        if (!$appointment) {
            $this->logger->error('Appointment not found in notification', [
                'notification_id' => $notification->getId()
            ]);
            return;
        }
    
        try {
            // Convertir la entidad Appointment a array para WhatsApp
            $appointmentData = $this->convertAppointmentToWhatsAppArray($appointment);
            
            $success = $this->whatsappService->sendAppointmentNotification(
                $appointmentData, 
                $notification->getType()->value
            );
    
            if ($success) {
                $notification->setStatus(NotificationStatusEnum::SENT);
                $notification->setSentAt(new \DateTime());
            } else {
                $notification->setStatus(NotificationStatusEnum::FAILED);
                $notification->setErrorMessage('WhatsApp service returned false');
            }
    
        } catch (\Exception $e) {
            $notification->setStatus(NotificationStatusEnum::FAILED);
            $notification->setErrorMessage($e->getMessage());
            $this->logger->error('Exception sending WhatsApp notification', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage()
            ]);
        }
    
        $this->entityManager->flush();
    }

    /**
     * Convierte una entidad Appointment al formato de array que espera WhatsAppService
     */
    private function convertAppointmentToWhatsAppArray(Appointment $appointment): array
    {
        $patient = $appointment->getPatient();
        $professional = $appointment->getProfessional();
        $service = $appointment->getService();
        $location = $appointment->getLocation();
        $company = $appointment->getCompany();
        $scheduledAt = $appointment->getScheduledAt();

        return [
            'id' => $appointment->getId(),
            'scheduledAt' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration' => $appointment->getDurationMinutes(),
            'notes' => $appointment->getNotes(),
            'status' => $appointment->getStatus()->value,
            'appointmentData' => [
                'patientName' => $patient->getName(),
                'serviceName' => $service->getName(),
                'professionalName' => $professional->getName(),
                'date' => $scheduledAt->format('Y-m-d'),
                'time' => $scheduledAt->format('H:i'),
                'duration' => $service->getDurationMinutes() . ' minutos',
                'locationName' => $location->getName(),
                'locationAddress' => $location->getAddress()
            ],
            'patient' => [
                'id' => $patient->getId(),
                'firstName' => $patient->getFirstName(),
                'lastName' => $patient->getLastName(),
                'email' => $patient->getEmail(),
                'phone' => $patient->getPhone(),
                'name' => $patient->getName()
            ],
            'professional' => [
                'id' => $professional->getId(),
                'name' => $professional->getName(),
                'email' => $professional->getEmail(),
                'phone' => $professional->getPhone()
            ],
            'service' => [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'duration' => $service->getDurationMinutes(),
                'price' => $service->getPrice()
            ],
            'location' => [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'address' => $location->getAddress()
            ],
            'company' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'domain' => $company->getDomain(),
                'phone' => $company->getPhone()
            ]
        ];
    }
}