<?php

namespace App\EventListener;

use App\Entity\Notification;
use App\Entity\NotificationStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
class EmailFailureListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        
        // Solo procesar fallos de SendEmailMessage
        if (!$message instanceof SendEmailMessage) {
            return;
        }

        try {
            $email = $message->getMessage();
            $headers = $email->getHeaders();
            
            // Buscar el header X-Notification-ID
            if (!$headers->has('X-Notification-ID')) {
                return;
            }
            
            $notificationId = (int)$headers->get('X-Notification-ID')->getBody();
            
            $notification = $this->entityManager->getRepository(Notification::class)
                ->find($notificationId);
                
            if (!$notification) {
                $this->logger->warning('Notification not found for failed email', [
                    'notification_id' => $notificationId
                ]);
                return;
            }
            
            // Actualizar la notificación con el error
            $notification->setStatus(NotificationStatusEnum::FAILED);
            $notification->setErrorMessage($event->getThrowable()->getMessage());
            
            $this->entityManager->flush();
            
            $this->logger->info('Updated notification status to FAILED', [
                'notification_id' => $notificationId,
                'error' => $event->getThrowable()->getMessage()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing email failure event', [
                'error' => $e->getMessage()
            ]);
        }
    }
}