<?php

namespace App\Service;

use App\Entity\Appointment;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class BrevoEmailService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private Environment $twig,
        private AppointmentService $appointmentService,
        private UrlGeneratorService $urlGenerator,
        private LoggerInterface $logger
    ) {}


    /**
     * Extrae un nombre del email o devuelve un nombre por defecto
     */
    private function extractNameFromEmail(string $email): string
    {
        // Extraer la parte antes del @ y capitalizar
        $localPart = explode('@', $email)[0];
        
        // Reemplazar puntos, guiones y números por espacios
        $name = preg_replace('/[._\-0-9]+/', ' ', $localPart);
        
        // Capitalizar cada palabra y limpiar espacios extra
        $name = trim(ucwords(strtolower($name)));
        
        // Si queda vacío o muy corto, usar un nombre por defecto
        if (empty($name) || strlen($name) < 2) {
            $name = 'Usuario';
        }
        
        return $name;
    }


    /**
     * Método genérico para enviar emails via Brevo
     */
    public function sendEmail(string $to, string $subject, string $htmlContent, ?string $from = null, ?int $notificationId = null): void
    {
        $fromAddress = $from ?? $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@turnoboost.com';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'TurnoBoost';
        
        // Extraer nombre del email o usar un nombre por defecto
        $toName = $this->extractNameFromEmail($to);
        
        $emailData = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromAddress
            ],
            'to' => [
                [
                    'email' => $to,
                    'name' => $toName
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent
        ];

        // Agregar BCC si está configurado
        if (!empty($_ENV['MAIL_BCC_DEBUG'])) {
            $emailData['bcc'] = [
                ['email' => $_ENV['MAIL_BCC_DEBUG']]
            ];
        }

        // Agregar headers personalizados
        if ($notificationId !== null) {
            $emailData['headers'] = [
                'X-Notification-ID' => (string)$notificationId
            ];
        }

        $this->sendEmailViaBrevo($emailData, $to, 'custom', $notificationId);
    }

    /**
     * Método privado centralizado para enviar emails via Brevo API
     */
    private function sendEmailViaBrevo(array $emailData, string $toAddress, string $type, ?int $notificationId = null): void
    {
        try {
            $response = $this->httpClient->request('POST', $_ENV['BREVO_API_URL'], [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => $_ENV['BREVO_API_KEY'],
                    'content-type' => 'application/json'
                ],
                'json' => $emailData
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Email sent successfully via Brevo', [
                    'notification_id' => $notificationId,
                    'to' => $toAddress,
                    'type' => $type,
                    'status_code' => $statusCode
                ]);
            } else {
                $responseContent = $response->getContent(false);
                throw new \Exception("Brevo API error: HTTP {$statusCode} - {$responseContent}");
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email via Brevo', [
                'notification_id' => $notificationId,
                'to' => $toAddress,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception('Failed to send email via Brevo: ' . $e->getMessage(), 0, $e);
        }
    }

}