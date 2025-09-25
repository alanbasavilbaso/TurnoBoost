<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Appointment;
use App\Entity\Location;
use App\Service\AppointmentService;

class WhatsAppService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private AppointmentService $appointmentService;
    private string $whatsappServiceUrl;
    private string $apiAuthHeader;
    private string $userAgentHeader;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        AppointmentService $appointmentService,
        string $whatsappServiceUrl,
        string $apiAuthHeader,
        string $userAgentHeader
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->appointmentService = $appointmentService;
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
        $domain = $appointmentData['location']['domain'];
        $baseUrl = $_ENV['APP_URL'] ?? 'https://turnoboost.com';
        
        // Generar tokens seguros
        $confirmToken = $this->appointmentService->generateSecureToken($appointmentId, 'confirm');
        $cancelToken = $this->appointmentService->generateSecureToken($appointmentId, 'cancel');
        
        try {
            // Limpiar números de teléfono eliminando el símbolo +
            $locationPhone = $this->cleanPhoneNumber($appointmentData['location']['phone']);
            $patientPhone = $this->cleanPhoneNumber($appointmentData['patient']['phone']);
            
            $response = $this->httpClient->request('POST', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $locationPhone . '/send-template', [
                'headers' => $this->getCommonHeaders(),
                'json' => [
                    'phone' => $patientPhone,
                    'appointmentId' => $appointmentId,
                    'appointmentData' => $appointmentData,
                    'messageType' => $messageType,
                    'confirmUrl' => $baseUrl . '/reservas/' . $domain . '/appointment/' . $appointmentId . '/confirm/' . $confirmToken,
                    'cancelUrl' => $baseUrl . '/reservas/' . $domain . '/appointment/' . $appointmentId . '/cancel/' . $cancelToken
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            
            if ($data['success']) {
                $this->logger->info('WhatsApp template message sent successfully', [
                    'appointment_id' => $appointmentId,
                    'message_type' => $messageType,
                    'message_id' => $data['messageId'] ?? null,
                    'location_phone' => $locationPhone,
                    'patient_phone' => $patientPhone
                ]);
                
                return true;
            } else {
                $this->logger->error('Failed to send WhatsApp template message', [
                    'appointment_id' => $appointmentId,
                    'error' => $data['error'] ?? 'Unknown error',
                    'location_phone' => $locationPhone,
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
     * Verifica el estado de conexión de WhatsApp para una location
     */
    public function getWhatsAppSessionStatus(string $locationPhone): array
    {
        try {
            $cleanPhone = $this->cleanPhoneNumber($locationPhone);
            
            $response = $this->httpClient->request('GET', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $cleanPhone . '/status', [
                'headers' => $this->getCommonHeaders(),
                'timeout' => 30
            ]);
            
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get WhatsApp session status', [
                'location_phone' => $this->cleanPhoneNumber($locationPhone),
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'isConnected' => false,
                    'state' => 'error',
                    'phoneNumber' => $this->cleanPhoneNumber($locationPhone)
                ]
            ];
        }
    }

    /**
     * Obtiene el código QR para conectar WhatsApp
     */
    public function getWhatsAppQRCode(string $locationPhone): array
    {
        try {
            $cleanPhone = $this->cleanPhoneNumber($locationPhone);
            
            $response = $this->httpClient->request('GET', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $cleanPhone . '/qr', [
                'headers' => $this->getCommonHeaders(),
                'timeout' => 30
            ]);
            
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get WhatsApp QR code', [
                'location_phone' => $this->cleanPhoneNumber($locationPhone),
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Error al obtener QR: ' . $e->getMessage(),
                'state' => 'error'
            ];
        }
    }

    /**
     * Envía un mensaje de prueba
     */
    public function sendTestMessage(string $locationPhone, string $toPhone, string $message): array
    {
        try {
            $cleanLocationPhone = $this->cleanPhoneNumber($locationPhone);
            $cleanToPhone = $this->cleanPhoneNumber($toPhone);
            
            $response = $this->httpClient->request('POST', 
                $this->whatsappServiceUrl . '/api/whatsapp/session/' . $cleanLocationPhone . '/send-message', [
                'headers' => $this->getCommonHeaders(),
                'json' => [
                    'to' => $cleanToPhone,
                    'message' => $message
                ],
                'timeout' => 30
            ]);
            
            $data = $response->toArray();
            
            $this->logger->info('WhatsApp test message sent', [
                'location_phone' => $cleanLocationPhone,
                'to_phone' => $cleanToPhone,
                'success' => $data['success'] ?? false
            ]);
            
            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send WhatsApp test message', [
                'location_phone' => $this->cleanPhoneNumber($locationPhone),
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

    /**
     * Valida que el teléfono sea argentino
     */
    public function validateArgentinePhone(string $phone): bool
    {
        $cleanPhone = $this->cleanPhoneNumber($phone);
        return preg_match('/^54[0-9]{10}$/', $cleanPhone) === 1;
    }

    /**
     * Formatea un teléfono argentino
     */
    public function formatArgentinePhone(string $phone): string
    {
        $cleanPhone = $this->cleanPhoneNumber($phone);
        
        if (!$this->validateArgentinePhone($cleanPhone)) {
            throw new \InvalidArgumentException('El teléfono debe ser argentino (54 + 10 dígitos)');
        }
        
        return $cleanPhone;
    }
}