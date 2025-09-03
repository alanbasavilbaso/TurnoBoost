<?php

namespace App\Controller;

use App\Entity\Patient;
use App\Form\PersonType;
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
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            $this->addFlash('error', 'Debe crear un local antes de gestionar clientes.');
            return $this->redirectToRoute('location_index');
        }

        // Obtener el parámetro de búsqueda
        $search = $request->query->get('search', '');
        
        // Buscar pacientes con filtro por nombre si se proporciona
        $queryBuilder = $entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.location = :location')
            ->setParameter('location', $location)
            ->orderBy('p.name', 'ASC');
            
        if (!empty($search)) {
            $queryBuilder->andWhere('p.name LIKE :search OR p.email LIKE :search OR p.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $patients = $queryBuilder->getQuery()->getResult();

        return $this->render('person/index.html.twig', [
            'patients' => $patients,
            'location' => $location,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_person_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            $this->addFlash('error', 'Debe crear un local antes de gestionar clientes.');
            return $this->redirectToRoute('location_index');
        }
        
        $patient = new Patient();
        $patient->setLocation($location);
        
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
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/new/form', name: 'app_person_new_form', methods: ['GET'])]
    public function newForm(): Response
    {
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            return new Response('<div class="alert alert-danger">Debe crear un local antes de gestionar clientes.</div>');
        }
        
        $patient = new Patient();
        $patient->setLocation($location);
        
        $form = $this->createForm(PersonType::class, $patient, ['is_edit' => false]);
        
        return $this->render('person/form.html.twig', [
            'form' => $form,
            'patient' => $patient,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}', name: 'app_person_show', methods: ['GET'])]
    public function show(Patient $patient): Response
    {
        if (!$this->canAccess($patient)) {
            $this->addFlash('error', 'No tiene permisos para ver este cliente.');
            return $this->redirectToRoute('app_person_index');
        }

        return $this->render('person/show.html.twig', [
            'patient' => $patient,
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
            'location' => $patient->getLocation(),
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
            'location' => $patient->getLocation(),
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
            'name' => $patient->getName(),
            'phone' => $patient->getPhone(),
            'email' => $patient->getEmail(),
            'notes' => $patient->getNotes(),
            'appointments_count' => $patient->getAppointments()->count(),
            'created_at' => $patient->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $patient->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/{id}', name: 'app_person_delete', methods: ['POST'])]
    public function delete(Request $request, Patient $patient, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($patient)) {
            $this->addFlash('error', 'No tiene permisos para eliminar este cliente.');
            return $this->redirectToRoute('app_person_index');
        }

        if ($this->isCsrfTokenValid('delete'.$patient->getId(), $request->request->get('_token'))) {
            // Verificar si tiene citas asociadas
            if ($patient->getAppointments()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar el cliente porque tiene citas asociadas.');
                return $this->redirectToRoute('app_person_index');
            }

            $entityManager->remove($patient);
            $entityManager->flush();
            $this->addFlash('success', 'Cliente eliminado exitosamente.');
        }

        return $this->redirectToRoute('app_person_index');
    }

    private function canAccess(Patient $patient): bool
    {
        $user = $this->getUser();
        $userLocation = $user->getOwnedLocations()->first();
        
        return $userLocation && $patient->getLocation() === $userLocation;
    }
}