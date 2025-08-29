<?php

namespace App\Controller;

use App\Entity\Clinic;
use App\Entity\Service;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Service\AppointmentService;
use App\Service\TimeSlot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookingController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TimeSlot $timeSlotService;

    public function __construct(EntityManagerInterface $entityManager, TimeSlot $timeSlotService)
    {
        $this->entityManager = $entityManager;
        $this->timeSlotService = $timeSlotService;
    }

    /**
     * Página principal de reservas por dominio
     * URL: localhost/reservas/{domain}
     */
    #[Route('/reservas/{domain}', name: 'booking_index', requirements: ['domain' => '[a-z0-9-]+'])]
    public function index(string $domain): Response
    {
        $clinic = $this->getClinicByDomain($domain);
        
        return $this->render('booking/index.html.twig', [
            'clinic' => $clinic,
            'domain' => $domain
        ]);
    }

    /**
     * API: Obtener servicios activos de la clínica
     */
    #[Route('/reservas/{domain}/api/services', name: 'booking_api_services', methods: ['GET'])]
    public function getServices(string $domain): JsonResponse
    {
        $clinic = $this->getClinicByDomain($domain);
        
        $services = $this->entityManager->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->where('s.clinic = :clinic')
            ->setParameter('clinic', $clinic)
            ->getQuery()
            ->getResult();

        $servicesData = [];
        foreach ($services as $service) {
            $servicesData[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'description' => $service->getDescription(),
                'duration' => $service->getDurationMinutes(),
                'durationFormatted' => $this->formatDuration($service->getDurationMinutes()),
                'type' => $service->getServiceType()->value
            ];
        }

        return new JsonResponse($servicesData);
    }

    /**
     * API: Obtener profesionales que ofrecen un servicio específico
     */
    #[Route('/reservas/{domain}/api/professionals/{serviceId}', name: 'booking_api_professionals', methods: ['GET'])]
    public function getProfessionalsByService(string $domain, int $serviceId): JsonResponse
    {
        $clinic = $this->getClinicByDomain($domain);
        
        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        if (!$service || $service->getClinic() !== $clinic) {
            throw new NotFoundHttpException('Servicio no encontrado');
        }

        $professionalServices = $this->entityManager->getRepository(ProfessionalService::class)
            ->createQueryBuilder('ps')
            ->join('ps.professional', 'p')
            ->where('ps.service = :service')
            ->andWhere('p.clinic = :clinic')
            ->setParameter('service', $service)
            ->setParameter('clinic', $clinic)
            ->getQuery()
            ->getResult();

        $professionalsData = [];
        foreach ($professionalServices as $professionalService) {
            $professional = $professionalService->getProfessional();
            $professionalsData[] = [
                'id' => $professional->getId(),
                'name' => $professional->getName(),
                'fullName' => $professional->getName(), // Solo usar el nombre completo
                'specialization' => $professional->getSpecialty(), // Cambiar getSpecialization() por getSpecialty()
                'phone' => $professional->getPhone(),
                'email' => $professional->getEmail(),
                'price' => $professionalService->getEffectivePrice(),
                'priceFormatted' => number_format($professionalService->getEffectivePrice(), 2, '.', ','),
                'customPrice' => $professionalService->getCustomPrice(),
                'professionalServiceId' => $professionalService->getId()
            ];
        }

        return new JsonResponse($professionalsData);
    }

    /**
     * API: Obtener horarios disponibles para un profesional en una fecha
     */
    #[Route('/reservas/{domain}/api/timeslots', name: 'booking_api_timeslots', methods: ['GET'])]
    public function getTimeSlots(string $domain, Request $request): JsonResponse
    {
        $clinic = $this->getClinicByDomain($domain);
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
        $date = $request->query->get('date');
        
        if (!$professionalId || !$serviceId || !$date) {
            return new JsonResponse(['error' => 'Faltan parámetros requeridos (professional, service, date)'], 400);
        }

        try {
            $dateObj = new \DateTime($date);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Formato de fecha inválido'], 400);
        }

        $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);
        if (!$professional || $professional->getClinic() !== $clinic) {
            return new JsonResponse(['error' => 'Profesional no encontrado'], 404);
        }

        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        if (!$service || $service->getClinic() !== $clinic) {
            return new JsonResponse(['error' => 'Servicio no encontrado'], 404);
        }

        // Generar slots disponibles usando el servicio TimeSlot
        $slots = $this->timeSlotService->generateAvailableSlots($professional, $service, $dateObj);
        
        // Organizar slots por mañana y tarde
        $timeSlots = $this->organizeSlotsByPeriod($slots);

        return new JsonResponse([
            'date' => $date,
            'professional' => $professional->getName() . ' ' . $professional->getSpecialty(),
            'service' => $service->getName(),
            'timeSlots' => $timeSlots
        ]);
    }

    /**
     * Organiza los slots por períodos (mañana y tarde)
     */
    private function organizeSlotsByPeriod(array $slots): array
    {
        $organized = [
            'morning' => [],
            'afternoon' => []
        ];

        foreach ($slots as $slot) {
            $hour = (int)substr($slot['time'], 0, 2);
            
            // Considerar mañana hasta las 12:00 (no inclusive)
            if ($hour < 12) {
                $organized['morning'][] = [
                    'time' => $slot['time'],
                    'available' => $slot['available'],
                    'duration' => $slot['duration'] ?? null,
                    'datetime' => $slot['datetime'] ?? null
                ];
            } else {
                $organized['afternoon'][] = [
                    'time' => $slot['time'],
                    'available' => $slot['available'],
                    'duration' => $slot['duration'] ?? null,
                    'datetime' => $slot['datetime'] ?? null
                ];
            }
        }

        return $organized;
    }

    /**
     * Método auxiliar para obtener clínica por dominio
     */
    private function getClinicByDomain(string $domain): Clinic
    {
        $clinic = $this->entityManager->getRepository(Clinic::class)
            ->findOneBy(['domain' => $domain]);
        
        if (!$clinic) {
            throw new NotFoundHttpException(sprintf('No se encontró una clínica con el dominio "%s"', $domain));
        }
        
        return $clinic;
    }

    /**
     * Formatea la duración en minutos a formato legible
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $remainingMinutes . 'min';
    }

    /**
     * API: Crear nueva cita desde el sistema de reservas público
     */
    #[Route('/reservas/{domain}/api/appointments', name: 'booking_api_create_appointment', methods: ['POST'])]
    public function createAppointment(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        $clinic = $this->getClinicByDomain($domain);
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Datos inválidos'], 400);
        }
        
        try {
            $appointment = $appointmentService->createAppointment($data, $clinic);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'appointment' => $appointmentService->appointmentToArray($appointment)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            error_log('Error creating public appointment: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Ha ocurrido un error interno. Por favor, inténtelo nuevamente.'
            ], 500);
        }
    }
}