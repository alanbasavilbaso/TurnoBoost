<?php

namespace App\Controller;

use App\Entity\Clinic;
use App\Entity\Service;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
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

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        $date = $request->query->get('date');
        
        if (!$professionalId || !$date) {
            return new JsonResponse(['error' => 'Faltan parámetros requeridos'], 400);
        }

        $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);
        if (!$professional || $professional->getClinic() !== $clinic) {
            throw new NotFoundHttpException('Profesional no encontrado');
        }

        // Aquí implementarías la lógica para obtener horarios disponibles
        // Por ahora, devolvemos horarios de ejemplo
        $timeSlots = [
            'morning' => [
                ['time' => '10:15', 'available' => true],
                ['time' => '10:30', 'available' => true],
                ['time' => '10:45', 'available' => true],
                ['time' => '11:00', 'available' => false],
                ['time' => '11:15', 'available' => true],
                ['time' => '11:30', 'available' => true],
                ['time' => '11:45', 'available' => true]
            ],
            'afternoon' => [
                ['time' => '12:00', 'available' => true],
                ['time' => '12:15', 'available' => true],
                ['time' => '12:30', 'available' => true],
                ['time' => '12:45', 'available' => true],
                ['time' => '13:00', 'available' => true],
                ['time' => '13:15', 'available' => false],
                ['time' => '13:30', 'available' => true],
                ['time' => '13:45', 'available' => true],
                ['time' => '14:00', 'available' => true],
                ['time' => '14:15', 'available' => true],
                ['time' => '14:30', 'available' => true],
                ['time' => '14:45', 'available' => true],
                ['time' => '15:00', 'available' => true],
                ['time' => '15:15', 'available' => true],
                ['time' => '15:30', 'available' => true],
                ['time' => '15:45', 'available' => true],
                ['time' => '16:00', 'available' => true],
                ['time' => '16:15', 'available' => true]
            ]
        ];

        return new JsonResponse([
            'date' => $date,
            'professional' => $professional->getName() . ' ' . $professional->getSpecialty(),
            'timeSlots' => $timeSlots
        ]);
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
}