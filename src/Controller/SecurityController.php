<?php

namespace App\Controller;

use App\Entity\Location;
use App\Form\LocationType;
use App\Form\LoginType;
use App\Entity\StatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si el usuario ya está autenticado, redirigir al index
        if ($this->getUser()) {
            return $this->redirectToRoute('app_index');
        }

        // Obtener el error de login si existe
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Último nombre de usuario ingresado por el usuario
        $lastUsername = $authenticationUtils->getLastUsername();

        // Crear el formulario
        $form = $this->createForm(LoginType::class, [
            'email' => $lastUsername
        ]);

        return $this->render('security/login.html.twig', [
            'form' => $form->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Este método puede estar vacío - será interceptado por la clave de logout en security.yaml
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/', name: 'app_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Verificar que el usuario esté autenticado
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Obtener el local del usuario
        $location = $entityManager->getRepository(Location::class)
            ->findOneBy(['createdBy' => $user]);
        
        if (!$location) {
            // Si no tiene locales, mostrar datos vacíos
            return $this->render('security/index.html.twig', [
                'user' => $user,
                'stats' => [
                    'appointments_today' => 0,
                    'total_patients' => 0,
                    'pending_appointments' => 0,
                    'appointments_this_month' => 0
                ]
            ]);
        }
        
        // Obtener todas las estadísticas en una sola query optimizada
        $stats = $this->getDashboardStats($entityManager, $location);
        
        return $this->render('security/index.html.twig', [
            'user' => $user,
            'stats' => $stats
        ]);
    }

    private function getDashboardStats(EntityManagerInterface $entityManager, Location $location): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        $firstDayOfMonth = new \DateTime('first day of this month');
        $firstDayOfNextMonth = new \DateTime('first day of next month');
        
        // Query 1: Citas de hoy
        $appointmentsToday = $entityManager->createQuery('
            SELECT COUNT(a.id) as count
            FROM App\Entity\Appointment a
            WHERE a.location = :location
            AND a.scheduledAt >= :today
            AND a.scheduledAt < :tomorrow
            AND a.status != :cancelled
        ')
        ->setParameters([
            'location' => $location,
            'today' => $today,
            'tomorrow' => $tomorrow,
            'cancelled' => StatusEnum::CANCELLED
        ])
        ->getSingleScalarResult();
        
        // Query 2: Total de pacientes únicos
        $totalPatients = $entityManager->createQuery('
            SELECT COUNT(DISTINCT p.id) as count
            FROM App\Entity\Patient p
            WHERE p.location = :location
        ')
        ->setParameter('location', $location)
        ->getSingleScalarResult();
        
        // Query 3: Citas pendientes (scheduled + confirmed)
        $pendingAppointments = $entityManager->createQuery('
            SELECT COUNT(a.id) as count
            FROM App\Entity\Appointment a
            WHERE a.location = :location
            AND a.scheduledAt >= :now
            AND a.status IN (:pending_statuses)
        ')
        ->setParameters([
            'location' => $location,
            'now' => new \DateTime(),
            'pending_statuses' => [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED]
        ])
        ->getSingleScalarResult();
        
        // Query 4: Citas de este mes
        $appointmentsThisMonth = $entityManager->createQuery('
            SELECT COUNT(a.id) as count
            FROM App\Entity\Appointment a
            WHERE a.location = :location
            AND a.scheduledAt >= :first_day
            AND a.scheduledAt < :first_day_next
            AND a.status != :cancelled
        ')
        ->setParameters([
            'location' => $location,
            'first_day' => $firstDayOfMonth,
            'first_day_next' => $firstDayOfNextMonth,
            'cancelled' => StatusEnum::CANCELLED
        ])
        ->getSingleScalarResult();
        
        return [
            'appointments_today' => (int) $appointmentsToday,
            'total_patients' => (int) $totalPatients,
            'pending_appointments' => (int) $pendingAppointments,
            'appointments_this_month' => (int) $appointmentsThisMonth
        ];
    }
}