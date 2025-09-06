<?php

namespace App\MessageHandler;

use App\Message\SendWhatsAppNotification;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Appointment;
use App\Entity\Notification;
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
        $appointmentRepository = $this->entityManager->getRepository(Appointment::class);
        $notificationRepository = $this->entityManager->getRepository(Notification::class);
        
        $appointment = $appointmentRepository->find($message->getAppointmentId());
        
        if (!$appointment) {
            $this->logger->error('Appointment not found for WhatsApp notification', [
                'appointment_id' => $message->getAppointmentId()
            ]);
            return;
        }

        // Buscar la notificación correspondiente
        $notification = $notificationRepository->findOneBy([
            'appointment' => $appointment,
            'type' => $message->getMessageType(),
            'status' => 'PENDING'
        ]);

        if (!$notification) {
            $this->logger->error('Notification not found for WhatsApp', [
                'appointment_id' => $message->getAppointmentId(),
                'message_type' => $message->getMessageType()
            ]);
            return;
        }

        try {
            $success = $this->whatsappService->sendAppointmentNotification(
                $appointment, 
                $message->getMessageType()
            );

            if ($success) {
                $notification->setStatus('SENT');
                $notification->setSentAt(new \DateTime());
                $this->logger->info('WhatsApp notification sent successfully');
            } else {
                $notification->setStatus('FAILED');
                $this->logger->error('Failed to send WhatsApp notification');
            }

        } catch (\Exception $e) {
            $notification->setStatus('FAILED');
            $this->logger->error('Exception sending WhatsApp notification', [
                'error' => $e->getMessage()
            ]);
        }

        $this->entityManager->flush();
    }
}