<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

#[Route('/api/whatsapp')]
class WhatsAppApiController extends AbstractController
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    #[Route('/error-notifications', name: 'whatsapp_error_notifications', methods: ['POST'])]
    public function errorNotifications(Request $request): JsonResponse
    {
        try {
            // Verificar autenticación
            $authHeader = $request->headers->get('Authorization');
            $expectedToken = $_ENV['ERROR_NOTIFICATION_KEY'] ?? null;
            
            if (!$expectedToken) {
                $this->logger->warning('ERROR_NOTIFICATION_KEY no configurado');
                return new JsonResponse(['error' => 'Service not configured'], 503);
            }
            
            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return new JsonResponse(['error' => 'Missing or invalid authorization header'], 401);
            }
            
            $token = substr($authHeader, 7); // Remover "Bearer "
            if ($token !== $expectedToken) {
                return new JsonResponse(['error' => 'Invalid token'], 401);
            }
            
            // Verificar Content-Type
            if ($request->headers->get('Content-Type') !== 'application/json') {
                return new JsonResponse(['error' => 'Invalid content type'], 400);
            }
            
            // Obtener y validar datos
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON'], 400);
            }
            
            // Validar campos requeridos
            $requiredFields = ['timestamp', 'service', 'phoneNumber', 'type', 'error'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return new JsonResponse(['error' => "Missing required field: $field"], 400);
                }
            }
            
            // Enviar email de notificación
            $this->sendErrorNotificationEmail($data);
            
            // Log del error para debugging
            $this->logger->error('WhatsApp Service Error', [
                'type' => $data['type'],
                'phoneNumber' => $data['phoneNumber'],
                'error' => $data['error']['message'] ?? 'Unknown error',
                'appointmentId' => $data['appointmentId'] ?? null
            ]);
            
            return new JsonResponse(['status' => 'received'], 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing WhatsApp error notification', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse(['error' => 'Internal server error'], 500);
        }
    }
    
    private function sendErrorNotificationEmail(array $data): void
    {
        $emailTo = $_ENV['MAIL_WHEN_WHATSAPP_ERROR'] ?? null;
        
        if (!$emailTo) {
            $this->logger->warning('MAIL_WHEN_WHATSAPP_ERROR no configurado');
            return;
        }
        
        $subject = sprintf(
            'Error WhatsApp Service - %s - %s',
            $data['type'],
            $data['phoneNumber']
        );
        
        $body = $this->formatErrorEmailBody($data);
        
        $email = (new Email())
            ->from($_ENV['MAILER_FROM'] ?? 'noreply@turnoboost.com')
            ->to($emailTo)
            ->subject($subject)
            ->text($body);
        
        try {
            $this->mailer->send($email);
            $this->logger->info('Error notification email sent', ['to' => $emailTo]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send error notification email', [
                'error' => $e->getMessage(),
                'to' => $emailTo
            ]);
        }
    }
    
    private function formatErrorEmailBody(array $data): string
    {
        $body = "NOTIFICACIÓN DE ERROR - WHATSAPP SERVICE\n";
        $body .= "==========================================\n\n";
        
        $body .= "INFORMACIÓN GENERAL:\n";
        $body .= "- Timestamp: " . ($data['timestamp'] ?? 'N/A') . "\n";
        $body .= "- Servicio: " . ($data['service'] ?? 'N/A') . "\n";
        $body .= "- Tipo de Error: " . ($data['type'] ?? 'N/A') . "\n";
        $body .= "- Teléfono WhatsApp: " . ($data['phoneNumber'] ?? 'N/A') . "\n";
        
        if (isset($data['appointmentId'])) {
            $body .= "- ID de Cita: " . $data['appointmentId'] . "\n";
        }
        
        if (isset($data['phone'])) {
            $body .= "- Teléfono Destino: " . $data['phone'] . "\n";
        }
        
        if (isset($data['messageType'])) {
            $body .= "- Tipo de Mensaje: " . $data['messageType'] . "\n";
        }
        
        $body .= "\nDETALLES DEL ERROR:\n";
        if (isset($data['error']['message'])) {
            $body .= "- Mensaje: " . $data['error']['message'] . "\n";
        }
        
        if (isset($data['error']['code'])) {
            $body .= "- Código: " . $data['error']['code'] . "\n";
        }
        
        if (isset($data['error']['stack'])) {
            $body .= "- Stack Trace:\n" . $data['error']['stack'] . "\n";
        }
        
        if (isset($data['context'])) {
            $body .= "\nCONTEXTO:\n";
            foreach ($data['context'] as $key => $value) {
                $body .= "- " . ucfirst($key) . ": " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
            }
        }
        
        $body .= "\n==========================================\n";
        $body .= "Este email fue generado automáticamente por TurnoBoost\n";
        $body .= "Timestamp del sistema: " . date('Y-m-d H:i:s') . "\n";
        
        return $body;
    }
}