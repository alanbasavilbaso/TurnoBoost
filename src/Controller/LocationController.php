<?php

namespace App\Controller;

use App\Entity\Location;
use App\Entity\User;
use App\Service\PhoneUtilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\LocationType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\LocationAvailability;
use App\Entity\Professional;
use App\Entity\Company;

#[Route('/location')]
#[IsGranted('ROLE_ADMIN')]
class LocationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhoneUtilityService $phoneUtility
    ) {}

    #[Route('/', name: 'location_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Obtener locations de la empresa del usuario
        $locations = $this->entityManager->getRepository(Location::class)
            ->createQueryBuilder('l')
            ->where('l.company = :company')
            ->setParameter('company', $user->getCompany())
            ->orderBy('l.active', 'DESC')
            ->addOrderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('location/index.html.twig', [
            'locations' => $locations,
        ]);
    }

    #[Route('/{id}/reactivate', name: 'location_reactivate', methods: ['POST'])]
    public function reactivate(Request $request, Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que la location pertenece a la empresa del usuario
        if ($location->getCompany() !== $user->getCompany()) {
            $this->addFlash('error', 'No tienes permisos para reactivar esta ubicación.');
            return $this->redirectToRoute('location_index');
        }
        
        // Verificar si ya existe un local activo para esta empresa
        $activeLocationCount = $this->entityManager->getRepository(Location::class)
            ->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.company = :company')
            ->andWhere('l.active = :active')
            ->andWhere('l.id != :currentLocation')
            ->setParameter('company', $user->getCompany())
            ->setParameter('active', true)
            ->setParameter('currentLocation', $location->getId())
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($activeLocationCount > 0) {
            $this->addFlash('error', 'Has llegado al límite de tu plan actual (Para agregar un nuevo local contactanos)');
            return $this->redirectToRoute('location_index');
        }
    
        if ($this->isCsrfTokenValid('reactivate'.$location->getId(), $request->request->get('_token'))) {
            $location->setActive(true);
            $location->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            $this->addFlash('success', 'Ubicación reactivada exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }
    
        return $this->redirectToRoute('location_index');
    }

    #[Route('/new', name: 'location_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {   
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar si ya existe un local activo para esta empresa
        $activeLocationCount = $this->entityManager->getRepository(Location::class)
            ->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.company = :company')
            ->andWhere('l.active = :active')
            ->setParameter('company', $user->getCompany())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($activeLocationCount > 0) {
            $this->addFlash('error', 'Has llegado al límite de tu plan actual (Para agregar un nuevo local contactanos)');
            return $this->redirectToRoute('location_index');
        }
        
        $location = new Location();
        
        // Asignar automáticamente la empresa del usuario logueado
        $location->setCompany($user->getCompany());
        
        $form = $this->createForm(LocationType::class, $location, ['is_edit' => false]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($location->getPhone()) {
                    $location->setPhone($this->phoneUtility->cleanPhoneNumber($location->getPhone()));
                }
                
                $location->setCreatedAt(new \DateTime());
                $location->setUpdatedAt(new \DateTime());
                $location->setCreatedBy($user);
                
                // Procesar horarios de disponibilidad
                $this->processAvailabilityData($form, $location, $entityManager);
                
                $entityManager->persist($location);
                $entityManager->flush();
                
                $this->addFlash('success', 'Local creado exitosamente.');
                return $this->redirectToRoute('location_index');
            } else {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('location/form.html.twig', [
            'form' => $form,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}/edit', name: 'location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que $location no sea null
        if (!$location) {
            $this->addFlash('error', 'La ubicación no fue encontrada.');
            return $this->redirectToRoute('location_index');
        }
        
        // Verificar que la location pertenece a la empresa del usuario
        if ($location->getCompany() !== $user->getCompany()) {
            $this->addFlash('error', 'No tienes permisos para editar esta ubicación.');
            return $this->redirectToRoute('location_index');
        }

        $form = $this->createForm(LocationType::class, $location, ['is_edit' => true]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($location->getPhone()) {
                    $location->setPhone($this->phoneUtility->cleanPhoneNumber($location->getPhone()));
                }
                
                $location->setUpdatedAt(new \DateTime());
                
                // Procesar horarios de disponibilidad - agregar validación adicional
                if ($location && $location->getId()) {
                    $this->processAvailabilityData($form, $location, $entityManager);
                }
                
                $entityManager->flush();
                
                $this->addFlash('success', 'Ubicación actualizada exitosamente.');
                return $this->redirectToRoute('location_index');
            } else {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
    
        return $this->render('location/form.html.twig', [
            'location' => $location,
            'form' => $form,
            'is_edit' => true
        ]);
    }

    private function processAvailabilityData($form, Location $location, EntityManagerInterface $entityManager): void
    {
        // Validar que $location no sea null
        if (!$location) {
            throw new \InvalidArgumentException('Location object is null');
        }
        
        // Obtener los días que estaban habilitados antes del cambio (solo para edición)
        $previousEnabledDays = [];
        if ($location->getId()) {
            foreach ($location->getAvailabilities() as $availability) {
                $previousEnabledDays[$availability->getWeekDay()] = true;
            }
        }
        
        // Obtener los días que están habilitados ahora
        $currentEnabledDays = [];
        for ($day = 0; $day <= 6; $day++) {
            $enabledField = $form->get("day_{$day}_enabled");
            $enabled = $enabledField->getData();
            if ($enabled) {
                $currentEnabledDays[$day] = true;
            }
        }
        
        // Detectar días que se deshabilitaron
        $disabledDays = [];
        if ($location->getId()) {
            foreach ($previousEnabledDays as $day => $wasEnabled) {
                if (!isset($currentEnabledDays[$day])) {
                    $disabledDays[] = $day;
                }
            }
        }
        
        // Solo limpiar horarios existentes si es una edición (tiene ID)
        if ($location->getId()) {
            // Limpiar horarios existentes
            foreach ($location->getAvailabilities() as $availability) {
                $location->removeAvailability($availability);
                $entityManager->remove($availability);
            }
        }
        
        // Procesar nuevos horarios
        for ($day = 0; $day <= 6; $day++) {
            $enabledField = $form->get("day_{$day}_enabled");
            $startHourField = $form->get("day_{$day}_start_hour");
            $startMinuteField = $form->get("day_{$day}_start_minute");
            $endHourField = $form->get("day_{$day}_end_hour");
            $endMinuteField = $form->get("day_{$day}_end_minute");
            
            // Obtener los valores
            $enabled = $enabledField->getData();
            $startHour = $startHourField->getData();
            $startMinute = $startMinuteField->getData();
            $endHour = $endHourField->getData();
            $endMinute = $endMinuteField->getData();
            
            // Solo procesar si el día está habilitado y tiene al menos las horas
            if ($enabled && $startHour !== null && $startHour !== '' && $endHour !== null && $endHour !== '') {
                
                // Usar 0 como valor por defecto para minutos vacíos
                $startMinute = ($startMinute === '' || $startMinute === null) ? 0 : (int)$startMinute;
                $endMinute = ($endMinute === '' || $endMinute === null) ? 0 : (int)$endMinute;
                
                try {
                    // Crear objetos DateTime para start y end
                    $startTime = new \DateTime();
                    $startTime->setTime((int)$startHour, $startMinute);
                    
                    $endTime = new \DateTime();
                    $endTime->setTime((int)$endHour, $endMinute);
                    
                    // Validar que la hora de fin sea posterior a la de inicio
                    if ($endTime <= $startTime) {
                        continue; // Saltar este horario si es inválido
                    }
                    
                    $availability = new LocationAvailability();
                    $availability->setLocation($location);
                    $availability->setWeekDay($day);
                    $availability->setStartTime($startTime);
                    $availability->setEndTime($endTime);
                    
                    $location->addAvailability($availability);
                    $entityManager->persist($availability);
                    
                } catch (\Exception $e) {
                    // Si hay error al crear el horario, continuar con el siguiente
                    continue;
                }
            }
        }
    
        // Si se deshabilitaron días, actualizar profesionales y mostrar mensaje
        if (!empty($disabledDays)) {
            $this->updateProfessionalsForDisabledDays($location->getCompany(), $disabledDays, $entityManager);
            
            // Agregar mensaje informativo
            $this->addFlash('info', 'Se ha cerrado un día en el horario del local, lo que ha afectado automáticamente los horarios de los profesionales asociados. Si decides revertir este cambio, recuerda actualizar manualmente los horarios individuales de los profesionales.');
        }
    }
    
    /**
     * Actualiza los horarios de todos los profesionales de la empresa cuando se deshabilitan días en el local
     */
    private function updateProfessionalsForDisabledDays(Company $company, array $disabledDays, EntityManagerInterface $entityManager): void
    {
        // Obtener todos los profesionales activos de la empresa
        $professionals = $entityManager->getRepository(Professional::class)
            ->findBy([
                'company' => $company,
                'active' => true
            ]);
        
        foreach ($professionals as $professional) {
            // Eliminar las disponibilidades de los días deshabilitados
            $availabilitiesToRemove = [];
            foreach ($professional->getAvailabilities() as $availability) {
                if (in_array($availability->getWeekday(), $disabledDays)) {
                    $availabilitiesToRemove[] = $availability;
                }
            }
            
            // Remover las disponibilidades
            foreach ($availabilitiesToRemove as $availability) {
                $professional->removeAvailability($availability);
                $entityManager->remove($availability);
            }
        }
    }

    #[Route('/new/form', name: 'location_new_form', methods: ['GET'])]
    public function newForm(Request $request): Response
    {   
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar si ya existe un local activo para esta empresa
        $activeLocationCount = $this->entityManager->getRepository(Location::class)
            ->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.company = :company')
            ->andWhere('l.active = :active')
            ->setParameter('company', $user->getCompany())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($activeLocationCount > 0) {
            return $this->render('location/error.html.twig', [
                'message' => 'Has llegado al límite de tu plan actual (Para agregar un nuevo local contactanos)',
                'title' => 'Límite de Plan Alcanzado'
            ]);
        }
        
        $location = new Location();
        
        // Asignar automáticamente la empresa del usuario logueado
        $location->setCompany($user->getCompany());
        
        $form = $this->createForm(LocationType::class, $location, ['is_edit' => false]);
        
        return $this->render('location/form.html.twig', [
            'form' => $form,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}/form', name: 'location_edit_form', methods: ['GET'])]
    public function editForm(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de edición
        if (!$user->canEdit($user->getCompany())) {
            throw $this->createAccessDeniedException('No tienes permisos para editar esta ubicación.');
        }
    
        // Crear el formulario usando LocationType con is_edit = true
        $form = $this->createForm(LocationType::class, $location, ['is_edit' => true]);
    
        return $this->render('location/form.html.twig', [
            'location' => $location,
            'form' => $form,
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/details', name: 'location_details', methods: ['GET'])]
    public function getDetails(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos
        if (!$user->canEdit($user->getCompany())) {
            return $this->json(['error' => 'No tienes permisos para ver esta ubicación.'], 403);
        }

        return $this->json([
            'id' => $location->getId(),
            'name' => $location->getName(),
            'address' => $location->getAddress(),
            'phone' => $this->phoneUtility->formatForDisplay($location->getPhone()),
            'email' => $location->getEmail(),
            'created_at' => $location->getCreatedAt()?->format('d/m/Y H:i'),
            'updated_at' => $location->getUpdatedAt()?->format('d/m/Y H:i')
        ]);
    }

    #[Route('/{id}/delete', name: 'location_delete', methods: ['POST'])]
    public function delete(Request $request, Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que la location pertenece a la empresa del usuario
        if ($location->getCompany() !== $user->getCompany()) {
            $this->addFlash('error', 'No tienes permisos para eliminar esta ubicación.');
            return $this->redirectToRoute('location_index');
        }

        if ($this->isCsrfTokenValid('delete'.$location->getId(), $request->request->get('_token'))) {
            $location->setActive(false);
            $location->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            $this->addFlash('success', 'Ubicación desactivada exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('location_index');
    }

    private function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest() || 
               $request->headers->get('Content-Type') === 'application/json' ||
               $request->query->get('ajax') === '1';
    }
}