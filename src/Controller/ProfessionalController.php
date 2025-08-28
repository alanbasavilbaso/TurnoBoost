<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Entity\ProfessionalAvailability;
use App\Form\ProfessionalType;
use App\Repository\ProfessionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profesionales')]
#[IsGranted('ROLE_ADMIN')] // Cambiado de ROLE_USER a ROLE_ADMIN
class ProfessionalController extends AbstractController
{
    #[Route('/', name: 'app_professional_index', methods: ['GET'])]
    public function index(ProfessionalRepository $professionalRepository): Response
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            $this->addFlash('error', 'Debe crear una clínica antes de gestionar profesionales.');
            return $this->redirectToRoute('app_my_company');
        }

        $professionals = $professionalRepository->findBy(['clinic' => $clinic], ['name' => 'ASC']);

        return $this->render('professional/index.html.twig', [
            'professionals' => $professionals,
            'clinic' => $clinic,
        ]);
    }

    #[Route('/new', name: 'app_professional_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            $this->addFlash('error', 'Debe crear una clínica antes de agregar profesionales.');
            return $this->redirectToRoute('app_my_company');
        }

        $professional = new Professional();
        $professional->setClinic($clinic);
        $professional->setCreatedAt(new \DateTime());
        $professional->setUpdatedAt(new \DateTime());
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'clinic' => $clinic,
            'is_edit' => false
        ]);
         // Establecer valores por defecto para nuevo profesional
        for ($day = 0; $day <= 6; $day++) {
            if ($day !== 6) { // No domingo
                $form->get("availability_{$day}_enabled")->setData(true);
                $form->get("availability_{$day}_range1_start")->setData('09:00');
                $form->get("availability_{$day}_range1_end")->setData('18:00');
            }
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Procesar horarios de disponibilidad
            $this->processAvailabilityData($form, $professional, $entityManager);
            
            // Procesar servicios seleccionados
            $this->processServices($form, $professional, $entityManager);
            
            $entityManager->persist($professional);
            $entityManager->flush();

            $this->addFlash('success', 'Profesional creado exitosamente.');
            return $this->redirectToRoute('app_professional_index');
        }

        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'clinic' => $clinic,
            'services' => $clinic->getServices(),
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_professional_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Professional $professional, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para editar este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }

        $clinic = $professional->getClinic();
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'clinic' => $clinic,
            'is_edit' => true
        ]);
        
        // Precargar servicios actuales
        $currentServices = $professional->getServices()->toArray();
        $form->get('services')->setData($currentServices);
        
        // Pre-llenar datos de disponibilidad existentes
        $this->populateAvailabilityData($form, $professional);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $professional->setUpdatedAt(new \DateTime());
            
            // Eliminar disponibilidades existentes
            foreach ($professional->getAvailabilities() as $availability) {
                $entityManager->remove($availability);
            }
            
            // Procesar horarios de disponibilidad
            $this->processAvailabilityData($form, $professional, $entityManager);
            
            // Procesar servicios seleccionados
            $this->processServices($form, $professional, $entityManager);
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Profesional actualizado exitosamente.');
            return $this->redirectToRoute('app_professional_index');
        }
    
        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'clinic' => $clinic,
            'services' => $clinic->getServices(), // Agregar esta línea
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}', name: 'app_professional_show', methods: ['GET'])]
    public function show(Professional $professional): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para ver este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }

        return $this->render('professional/show.html.twig', [
            'professional' => $professional,
        ]);
    }

    #[Route('/{id}', name: 'app_professional_delete', methods: ['POST'])]
    public function delete(Request $request, Professional $professional, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para eliminar este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }

        if ($this->isCsrfTokenValid('delete'.$professional->getId(), $request->request->get('_token'))) {
            // Verificar si tiene citas asociadas
            if ($professional->getAppointments()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar el profesional porque tiene citas asociadas.');
                return $this->redirectToRoute('app_professional_index');
            }

            $entityManager->remove($professional);
            $entityManager->flush();
            $this->addFlash('success', 'Profesional eliminado exitosamente.');
        }

        return $this->redirectToRoute('app_professional_index');
    }

    private function canAccess(Professional $professional): bool
    {
        $user = $this->getUser();
        $userClinic = $user->getOwnedClinics()->first();
        
        return $userClinic && $professional->getClinic() === $userClinic;
    }

    private function processAvailabilityData(FormInterface $form, Professional $professional, EntityManagerInterface $entityManager): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $enabled = $form->get("availability_{$day}_enabled")->getData();
            
            if ($enabled) {
                // Procesar hasta 2 rangos por día
                for ($range = 1; $range <= 2; $range++) {
                    $startTimeString = $form->get("availability_{$day}_range{$range}_start")->getData();
                    $endTimeString = $form->get("availability_{$day}_range{$range}_end")->getData();
                    
                    if ($startTimeString && $endTimeString) {
                        // Convertir strings a objetos DateTime
                        $startTime = \DateTime::createFromFormat('H:i', $startTimeString);
                        $endTime = \DateTime::createFromFormat('H:i', $endTimeString);
                        
                        if ($startTime && $endTime) {
                            $availability = new ProfessionalAvailability();
                            $availability->setProfessional($professional);
                            $availability->setWeekday($day);
                            $availability->setStartTime($startTime);
                            $availability->setEndTime($endTime);
                            
                            $entityManager->persist($availability);
                        }
                    }
                }
            }
        }
    }

    private function populateAvailabilityData(FormInterface $form, Professional $professional): void
    {
        $availabilitiesByDay = [];
        
        // Debug: Verificar cuántas disponibilidades tiene el profesional
        $availabilities = $professional->getAvailabilities();
        error_log("Professional ID: " . $professional->getId());
        error_log("Total availabilities: " . count($availabilities));
        
        
        // Agrupar disponibilidades por día
        foreach ($availabilities as $availability) {
            $day = $availability->getWeekday();
            error_log("Found availability for day: " . $day . " from " . $availability->getStartTime()->format('H:i') . " to " . $availability->getEndTime()->format('H:i'));
            
            if (!isset($availabilitiesByDay[$day])) {
                $availabilitiesByDay[$day] = [];
            }
            $availabilitiesByDay[$day][] = $availability;
        }
        
        error_log("Grouped availabilities: " . json_encode(array_keys($availabilitiesByDay)));
        
        // Llenar formulario
        foreach ($availabilitiesByDay as $day => $availabilities) {
            error_log("Setting day {$day} as enabled");
            
            try {
                $form->get("availability_{$day}_enabled")->setData(true);
                // var_dump("availability_{$day}_enabled");exit;
                // Ordenar por hora de inicio
                usort($availabilities, function($a, $b) {
                    return $a->getStartTime() <=> $b->getStartTime();
                });
                
                // Asignar a rangos (máximo 2)
                for ($range = 1; $range <= min(2, count($availabilities)); $range++) {
                    $availability = $availabilities[$range - 1];
                    $startTime = $availability->getStartTime()->format('H:i');
                    $endTime = $availability->getEndTime()->format('H:i');
                    
                    error_log("Setting day {$day} range {$range}: {$startTime} - {$endTime}");
                    
                    // Convertir DateTime a string para el formulario
                    $form->get("availability_{$day}_range{$range}_start")->setData($startTime);
                    $form->get("availability_{$day}_range{$range}_end")->setData($endTime);
                }
            } catch (\Exception $e) {
                error_log("Error setting form data for day {$day}: " . $e->getMessage());
            }
        }
    }
    
    private function processServices(FormInterface $form, Professional $professional, EntityManagerInterface $entityManager): void
    {
        // Obtener servicios seleccionados del formulario
        $selectedServices = $form->get('services')->getData();
        
        // Eliminar todas las asociaciones existentes
        foreach ($professional->getProfessionalServices() as $professionalService) {
            $entityManager->remove($professionalService);
        }
        $professional->getProfessionalServices()->clear();
        
        // Crear nuevas asociaciones
        if ($selectedServices) {
            foreach ($selectedServices as $service) {
                $professionalService = new \App\Entity\ProfessionalService();
                $professionalService->setProfessional($professional);
                $professionalService->setService($service);
                // Usar valores por defecto del servicio
                $professionalService->setCustomDurationMinutes($service->getDefaultDurationMinutes());
                $professionalService->setCustomPrice($service->getPrice());
                
                $entityManager->persist($professionalService);
                $professional->addProfessionalService($professionalService);
            }
        }
    }
    
    // Add this method after the existing edit method
    #[Route('/{id}/editar', name: 'app_professional_edit_redirect', methods: ['GET'])]
    public function editRedirect(Professional $professional): Response
    {
        return $this->redirectToRoute('app_professional_edit', ['id' => $professional->getId()], 301);
    }
}