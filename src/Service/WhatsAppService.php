<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Appointment;
use App\Entity\Location;
use App\Service\AppointmentService;
use App\Service\UrlGeneratorService;

class WhatsAppService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private AppointmentService $appointmentService;
    private UrlGeneratorService $urlGenerator;
    private string $whatsappServiceUrl;
    private string $apiAuthHeader;
    private string $userAgentHeader;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        AppointmentService $appointmentService,
        UrlGeneratorService $urlGenerator,
        string $whatsappServiceUrl,
        string $apiAuthHeader,
        string $userAgentHeader
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->appointmentService = $appointmentService;
        $this->urlGenerator = $urlGenerator;
        $this->whatsappServiceUrl = $whatsappServiceUrl;
        $this->apiAuthHeader = $apiAuthHeader;
        $this->userAgentHeader = $userAgentHeader;
    }

    /**
     * Limpia el número de teléfono eliminando el símbolo + y espacios
     */
    private function cleanPhoneNumber(string $phone): string
    {
        return str_replace(['+', ' ', '-', '(', ')'], '', trim($phone));
    }

    /**
     * Obtiene los headers comunes para todas las peticiones
     */
    private function getCommonHeaders(): array
    {
        return [
            'X-API-Auth' => $this->apiAuthHeader,
            'User-Agent' => $this->userAgentHeader,
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Envía notificación de cita usando template
     */
    public function sendAppointmentNotification(array $appointmentData, string $messageType = 'reminder'): bool
    {
        $appointmentId = $appointmentData['id'];
        
        $appointment = $this->appointmentService->findActiveAppointmentFromChain($appointmentId);
        if (!$appointment) {
            throw new \InvalidArgumentException("Appointment with ID {$appointmentId} not found");
        }

        try {
            // Limpiar números de teléfono eliminando el símbolo +
            $companyPhone = $this->cleanPhoneNumber($appointmentData['company']['phone']);
            $patientPhone = $this->cleanPhoneNumber($appointmentData['patient']['phone']);
            
            $confirmUrl = $this->urlGenerator->generateConfirmUrl($appointment);
            $cancelUrl = $this->urlGenerator->generateCancelUrl($appointment);

            $response = $this->httpClient->request('POST', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $companyPhone . '/send-template', [
                'headers' => $this->getCommonHeaders(),
                'json' => [
                    'phone' => $patientPhone,
                    'appointmentId' => $appointmentId,
                    'appointmentData' => $appointmentData['appointmentData'],
                    'messageType' => $messageType,
                    'confirmUrl' => $confirmUrl,
                    'cancelUrl' => $cancelUrl
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            
            if ($data['success']) {
                $this->logger->info('WhatsApp template message sent successfully', [
                    'appointment_id' => $appointmentId,
                    'message_type' => $messageType,
                    'message_id' => $data['messageId'] ?? null,
                    'location_phone' => $companyPhone,
                    'patient_phone' => $patientPhone
                ]);
                
                return true;
            } else {
                $this->logger->error('Failed to send WhatsApp template message', [
                    'appointment_id' => $appointmentId,
                    'error' => $data['error'] ?? 'Unknown error',
                    'location_phone' => $companyPhone,
                    'patient_phone' => $patientPhone
                ]);
                
                return false;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Exception sending WhatsApp template message', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
                'location_phone' => $this->cleanPhoneNumber($appointmentData['location']['phone'] ?? 'unknown')
            ]);
            
            return false;
        }
    }

    /**
     * Envía un mensaje de prueba
     */
    public function sendTestMessage(string $companyPhone, string $toPhone, string $message): array
    {
        try {
            $cleanCompanyPhone = $this->cleanPhoneNumber($companyPhone);
            $cleanToPhone = $this->cleanPhoneNumber($toPhone);
            
            $response = $this->httpClient->request('POST', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $cleanCompanyPhone . '/send-message', [
                'headers' => $this->getCommonHeaders(),
                'json' => [
                    'to' => $cleanToPhone,
                    'message' => $message
                ],
                'timeout' => 30
            ]);
            
            $data = $response->toArray();
            
            $this->logger->info('WhatsApp test message sent', [
                'location_phone' => $cleanCompanyPhone,
                'to_phone' => $cleanToPhone,
                'success' => $data['success'] ?? false
            ]);
            
            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send WhatsApp test message', [
                'location_phone' => $this->cleanPhoneNumber($companyPhone),
                'to_phone' => $this->cleanPhoneNumber($toPhone),
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica la salud general del servicio
     */
    public function checkServiceHealth(): array
    {
        try {
            $response = $this->httpClient->request('GET', 
                $this->whatsappServiceUrl . '/api/health', [
                'headers' => $this->getCommonHeaders()
            ]);
            return $response->toArray();
        } catch (\Exception $e) {
            return ['status' => 'error', 'connected' => false];
        }
    }

    /**
     * Obtiene el estado del QR para una empresa
     */
    public function getQRStatus(string $phone): array
    {
        try {
            $cleanPhone = $this->cleanPhoneNumber($phone);
            
            $response = $this->httpClient->request('GET', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $cleanPhone . '/qr', [
                'headers' => $this->getCommonHeaders(),
                'timeout' => 30
            ]);
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get QR status', [
                'phone' => $cleanPhone,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Error al obtener estado del QR: ' . $e->getMessage(),
                'needsQR' => false,
                'qrCode' => null
            ];
        }
    }
}