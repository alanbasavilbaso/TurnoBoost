<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\Appointment;
use App\Entity\Professional;
use App\Entity\Service;
use App\Form\PersonType;
use App\Service\PatientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
#[IsGranted('ROLE_ADMIN')]
class PersonController extends AbstractController
{
    #[Route('/', name: 'app_person_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $company = $this->getUser()->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'No se encontró la empresa asociada.');
            return $this->redirectToRoute('app_index');
        }
    
        // Parámetros de paginación
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20; // Clientes por página
        $offset = ($page - 1) * $limit;
        
        // Obtener el parámetro de búsqueda
        $search = strtolower($request->query->get('search', ''));

        // Query base para contar total de clientes (solo activos)
        $countQueryBuilder = $entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.company = :company')
            ->andWhere('p.deletedAt IS NULL') // Solo pacientes no eliminados
            ->setParameter('company', $company);
            
        if (!empty($search)) {
            $countQueryBuilder->andWhere('LOWER(p.firstName) LIKE :search OR LOWER(p.lastName) LIKE :search OR LOWER(p.email) LIKE :search OR LOWER(p.phone) LIKE :search OR LOWER(p.idDocument) LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $totalPatients = $countQueryBuilder->getQuery()->getSingleScalarResult();
        
        // Query para obtener clientes de la página actual (solo activos)
        $queryBuilder = $entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.company = :company')
            ->andWhere('p.deletedAt IS NULL') // Solo pacientes no eliminados
            ->setParameter('company', $company)
            ->orderBy('p.firstName', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
            
        if (!empty($search)) {
            $queryBuilder->andWhere('LOWER(p.firstName) LIKE :search OR LOWER(p.lastName) LIKE :search OR LOWER(p.email) LIKE :search OR LOWER(p.phone) LIKE :search OR LOWER(p.idDocument) LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $patients = $queryBuilder->getQuery()->getResult();
        
        // Calcular información de paginación
        $totalPages = ceil($totalPatients / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;
    
        return $this->render('person/index.html.twig', [
            'patients' => $patients,
            'company' => $company,
            'search' => $search,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalPatients,
                'itemsPerPage' => $limit,
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $hasPreviousPage,
            ],
        ]);
    }

    #[Route('/new', name: 'app_person_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe estar asociado a una empresa para gestionar clientes.');
            return $this->redirectToRoute('app_index');
        }
        
        $patient = new Patient();
        $patient->setCompany($company);
        
        $form = $this->createForm(PersonType::class, $patient, ['is_edit' => false]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $patient->setUpdatedAt(new \DateTime());
                $entityManager->persist($patient);
                $entityManager->flush();
                
                $this->addFlash('success', 'Cliente creado exitosamente.');
                return $this->redirectToRoute('app_person_index');
            } else {
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('person/form.html.twig', [
            'form' => $form,
            'patient' => $patient,
            'company' => $company,
            'is_edit' => false
        ]);
    }

    #[Route('/new/form', name: 'app_person_new_form', methods: ['GET'])]
    public function newForm(): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new Response('<div class="alert alert-danger">Debe estar asociado a una empresa para gestionar clientes.</div>');
        }
        
        $patient = new Patient();
        $patient->setCompany($company);
        
        $form = $this->createForm(PersonType::class, $patient, ['is_edit' => false]);
        
        return $this->render('person/form.html.twig', [
            'form' => $form,
            'patient' => $patient,
            'company' => $company,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}', name: 'app_person_show', methods: ['GET'])]
    public function show(Request $request, Patient $patient, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($patient)) {
            $this->addFlash('error', 'No tiene permisos para ver este cliente.');
            return $this->redirectToRoute('app_person_index');
        }
    
        // Parámetros de paginación y filtros
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
        $status = $request->query->get('status');
        $company = $patient->getCompany();
    
        // QUERY 1: Obtener appointments con estadísticas y conteo en una sola consulta
        $qb = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.company', 'c')
            ->leftJoin('a.professional', 'p')
            ->leftJoin('a.service', 's')
            ->addSelect('c', 'p', 's')
            ->where('a.patient = :patient')
            ->setParameter('patient', $patient);
    
        // Aplicar filtros
        if ($professionalId) {
            $qb->andWhere('a.professional = :professional')
               ->setParameter('professional', $professionalId);
        }
        if ($serviceId) {
            $qb->andWhere('a.service = :service')
               ->setParameter('service', $serviceId);
        }
        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }
    
        // Obtener todas las citas para estadísticas y conteo
        $allAppointments = $qb->getQuery()->getResult();
        
        // Calcular estadísticas en PHP (más eficiente que múltiples queries)
        $stats = [
            'total' => count($allAppointments),
            'scheduled' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0
        ];
        
        $professionals = [];
        $services = [];
        
        foreach ($allAppointments as $appointment) {
            $statusValue = $appointment->getStatus()->value;
            if (isset($stats[$statusValue])) {
                $stats[$statusValue]++;
            }
            
            // Recopilar profesionales únicos
            if ($appointment->getProfessional() && $appointment->getProfessional()->getCompany() === $company) {
                $professionals[$appointment->getProfessional()->getId()] = $appointment->getProfessional();
            }
            
            // Recopilar servicios únicos
            if ($appointment->getService() && $appointment->getService()->getCompany() === $company) {
                $services[$appointment->getService()->getId()] = $appointment->getService();
            }
        }
    
        // Paginación en PHP
        $totalAppointments = count($allAppointments);
        $totalPages = ceil($totalAppointments / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;
        
        // Ordenar y paginar
        usort($allAppointments, function($a, $b) {
            return $b->getScheduledAt() <=> $a->getScheduledAt();
        });
        
        $appointments = array_slice($allAppointments, $offset, $limit);
        
        // Convertir a arrays ordenados
        $professionals = array_values($professionals);
        usort($professionals, function($a, $b) {
            return $a->getName() <=> $b->getName();
        });
        
        $services = array_values($services);
        usort($services, function($a, $b) {
            return $a->getName() <=> $b->getName();
        });
    
        return $this->render('person/show.html.twig', [
            'patient' => $patient,
            'appointments' => $appointments,
            'professionals' => $professionals,
            'services' => $services,
            'stats' => $stats,
            'filters' => [
                'professional' => $professionalId,
                'service' => $serviceId,
                'status' => $status,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalAppointments,
                'items_per_page' => $limit,
                'has_next' => $hasNextPage,
                'has_previous' => $hasPreviousPage,
                'next_page' => $hasNextPage ? $page + 1 : null,
                'previous_page' => $hasPreviousPage ? $page - 1 : null,
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_person_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Patient $patient, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($patient)) {
            $this->addFlash('error', 'No tiene permisos para editar este cliente.');
            return $this->redirectToRoute('app_person_index');
        }
        
        $form = $this->createForm(PersonType::class, $patient, ['is_edit' => true]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $patient->setUpdatedAt(new \DateTime());
                $entityManager->flush();
                
                $this->addFlash('success', 'Cliente actualizado exitosamente.');
                return $this->redirectToRoute('app_person_index');
            } else {
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('person/form.html.twig', [
            'form' => $form,
            'patient' => $patient,
            'company' => $patient->getCompany(),
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/form', name: 'app_person_edit_form', methods: ['GET'])]
    public function editForm(Patient $patient): Response
    {
        if (!$this->canAccess($patient)) {
            throw $this->createAccessDeniedException('No tiene permisos para editar este cliente.');
        }
        
        $form = $this->createForm(PersonType::class, $patient, ['is_edit' => true]);
        
        return $this->render('person/form.html.twig', [
            'form' => $form,
            'patient' => $patient,
            'company' => $patient->getCompany(),
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/details', name: 'app_person_details', methods: ['GET'])]
    public function getDetails(Patient $patient): JsonResponse
    {
        if (!$this->canAccess($patient)) {
            return new JsonResponse(['error' => 'No autorizado'], 403);
        }
    
        return new JsonResponse([
            'id' => $patient->getId(),
            'id_document' => $patient->getIdDocument(),
            'first_name' => $patient->getFirstName(),
            'last_name' => $patient->getLastName(),
            'full_name' => $patient->getFullName(),
            'birthdate' => $patient->getBirthdate() ? $patient->getBirthdate()->format('Y-m-d') : null,
            'phone' => $patient->getPhone(),
            'email' => $patient->getEmail(),
            'notes' => $patient->getNotes(),
            'appointments_count' => $patient->getAppointments()->count(),
            'created_at' => $patient->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $patient->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/{id}', name: 'app_person_delete', methods: ['POST'])]
    public function delete(Request $request, Patient $patient, EntityManagerInterface $entityManager, PatientService $patientService): Response
    {
        if (!$this->canAccess($patient)) {
            $this->addFlash('error', 'No tiene permisos para ver este cliente.');
            return $this->redirectToRoute('app_person_index');
        }
        
        if ($this->isCsrfTokenValid('delete'.$patient->getId(), $request->request->get('_token'))) {
            // Usar el servicio para hacer borrado lógico
            $patientService->deletePatient($patient);
            $this->addFlash('success', 'Cliente eliminado exitosamente.');
        }
    
        return $this->redirectToRoute('app_person_index');
    }

    private function canAccess(Patient $patient): bool
    {
        $user = $this->getUser();
        $userCompany = $user->getCompany();
        
        return $userCompany && $patient->getCompany() === $userCompany;
    }
}