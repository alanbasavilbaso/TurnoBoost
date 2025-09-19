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
            $success = $this->whatsappService->sendAppointmentNotification(
                $appointment, 
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
}