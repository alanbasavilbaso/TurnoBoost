<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use App\Service\AppointmentService;
use App\Service\UrlGeneratorService;
use App\Entity\WhatsAppApiLog;
use Doctrine\ORM\EntityManagerInterface;

class WhatsAppService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private AppointmentService $appointmentService;
    private UrlGeneratorService $urlGenerator;
    private string $whatsappServiceUrl;
    private string $apiAuthHeader;
    private string $userAgentHeader;
    private EntityManagerInterface $entityManager;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        AppointmentService $appointmentService,
        UrlGeneratorService $urlGenerator,
        EntityManagerInterface $entityManager,
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
        $this->entityManager = $entityManager;
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

        $startTime = microtime(true);
        $apiLog = new WhatsAppApiLog();

        try {
            // Limpiar números de teléfono eliminando el símbolo +
            $companyPhone = $this->cleanPhoneNumber($appointmentData['company']['phone']);
            $patientPhone = $this->cleanPhoneNumber($appointmentData['patient']['phone']);
            
            $confirmUrl = $this->urlGenerator->generateConfirmUrl($appointment);
            $cancelUrl = $this->urlGenerator->generateCancelUrl($appointment);

            $endpoint = $this->whatsappServiceUrl . '/api/whatsapp/session/' . $companyPhone . '/send-template';
            $payload = [
                'phone' => $patientPhone,
                'appointmentId' => $appointmentId,
                'appointmentData' => $appointmentData['appointmentData'],
                'messageType' => $messageType,
            ];

            // Solo agregar URLs si no son null
            if ($confirmUrl !== null) {
                $payload['confirmUrl'] = $confirmUrl;
            }
            
            if ($cancelUrl !== null) {
                $payload['cancelUrl'] = $cancelUrl;
            }

            $apiLog->setEndpoint($endpoint)
                   ->setMethod('POST')
                   ->setRequestPayload($payload)
                   ->setPhoneNumber($patientPhone)
                   ->setAppointmentId($appointmentId)
                   ->setMessageType($messageType);
                   
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => $this->getCommonHeaders(),
                'json' => $payload,
                'timeout' => 30
            ]);

            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            $data = $response->toArray();
            
             // Completar el log
            $apiLog->setHttpStatus($response->getStatusCode())
                   ->setResponseData($data)
                   ->setResponseTimeMs($responseTime);

            if (isset($data['messageId'])) {
                $apiLog->setMessageId($data['messageId']);
            }

            if ($data['success']) {
                $this->logger->info('WhatsApp template message sent successfully', [
                    'appointment_id' => $appointmentId,
                    'message_type' => $messageType,
                    'message_id' => $data['messageId'] ?? null,
                    'location_phone' => $companyPhone,
                    'patient_phone' => $patientPhone
                ]);
                
                $this->entityManager->persist($apiLog);
                $this->entityManager->flush();
                
                return true;
            } else {
                $errorMessage = $data['error'] ?? 'Unknown error';
                $apiLog->setErrorMessage($errorMessage);
                
                $this->logger->error('Failed to send WhatsApp template message', [
                    'appointment_id' => $appointmentId,
                    'error' => $errorMessage,
                    'location_phone' => $companyPhone,
                    'patient_phone' => $patientPhone
                ]);
                
                $this->entityManager->persist($apiLog);
                $this->entityManager->flush();
                
                return false;
            }
        } catch (\Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Intentar obtener más detalles del error si es una excepción HTTP
            $errorMessage = $e->getMessage();
            if ($e instanceof \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface) {
                try {
                    $errorResponse = $e->getResponse()->toArray(false);
                    if (isset($errorResponse['error'])) {
                        $errorMessage = $errorResponse['error'];
                    } elseif (isset($errorResponse['message'])) {
                        $errorMessage = $errorResponse['message'];
                    }
                } catch (\Exception $parseException) {
                    // Si no se puede parsear la respuesta, mantener el mensaje original
                }
            }
            
            $apiLog->setErrorMessage($errorMessage)
                   ->setResponseTimeMs($responseTime);

            $this->logger->error('Exception sending WhatsApp template message', [
                'appointment_id' => $appointmentId,
                'error' => $errorMessage,
                'location_phone' => $this->cleanPhoneNumber($appointmentData['company']['phone'] ?? 'unknown')
            ]);

            $this->entityManager->persist($apiLog);
            $this->entityManager->flush();
            
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