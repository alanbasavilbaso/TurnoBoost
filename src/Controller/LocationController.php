<?php

namespace App\Controller;

use App\Entity\Location;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\LocationType;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/location')]
#[IsGranted('ROLE_ADMIN')]
class LocationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'location_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Obtener solo los locales que el usuario ha creado
        $locations = $user->getOwnedLocations();

        return $this->render('location/index.html.twig', [
            'locations' => $locations,
        ]);
    }

    #[Route('/new', name: 'location_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {   
        $user = $this->getUser();
        $location = new Location();
        $form = $this->createForm(LocationType::class, $location, ['is_edit' => false]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $location->setCreatedAt(new \DateTime());
                $location->setUpdatedAt(new \DateTime());
                $location->setCreatedBy($user);
                $entityManager->persist($location);
                $entityManager->flush();
                
                $this->addFlash('success', 'Local creado exitosamente.');
                return $this->redirectToRoute('location_index');
            } else {
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        // Agregar el return statement que faltaba
        return $this->render('location/form.html.twig', [
            'form' => $form,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/new/form', name: 'location_new_form', methods: ['GET'])]
    public function newForm(): Response
    {   
        $location = new Location();
        $location->setDomain($location->getRandomDomain());
        $form = $this->createForm(LocationType::class, $location, ['is_edit' => false]);
        
        return $this->render('location/form.html.twig', [
            'form' => $form,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}', name: 'location_show', methods: ['GET'])]
    public function show(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que el usuario puede ver esta ubicación
        if (!$user->canEdit($location)) {
            $this->addFlash('error', 'No tienes permisos para ver esta ubicación.');
            return $this->redirectToRoute('location_index');
        }

        return $this->render('location/show.html.twig', [
            'location' => $location,
        ]);
    }

    #[Route('/{id}/edit', name: 'location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Location $location, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de edición
        if (!$user->canEdit($location)) {
            $this->addFlash('error', 'No tienes permisos para editar esta ubicación.');
            return $this->redirectToRoute('location_index');
        }

        $form = $this->createForm(LocationType::class, $location, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $location->setUpdatedAt(new \DateTime());
                $entityManager->flush();
                
                $this->addFlash('success', 'Servicio actualizado exitosamente.');
                return $this->redirectToRoute('app_service_index');
            } else {
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('location/form.html.twig', [
            'location' => $location,
        ]);
    }

    #[Route('/{id}/form', name: 'location_edit_form', methods: ['GET'])]
    public function editForm(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de edición
        if (!$user->canEdit($location)) {
            throw $this->createAccessDeniedException('No tienes permisos para editar esta ubicación.');
        }

        // Crear el formulario usando LocationType
        $form = $this->createForm(LocationType::class, $location);

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
        if (!$user->canEdit($location)) {
            return $this->json(['error' => 'No tienes permisos para ver esta ubicación.'], 403);
        }

        return $this->json([
            'id' => $location->getId(),
            'name' => $location->getName(),
            'address' => $location->getAddress(),
            'phone' => $location->getPhone(),
            'email' => $location->getEmail(),
            'domain' => $location->getDomain(),
            'created_at' => $location->getCreatedAt()?->format('d/m/Y H:i'),
            'updated_at' => $location->getUpdatedAt()?->format('d/m/Y H:i')
        ]);
    }

    #[Route('/{id}/delete', name: 'location_delete', methods: ['POST'])]
    public function delete(Request $request, Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de eliminación
        if (!$user->canEdit($location)) {
            $this->addFlash('error', 'No tienes permisos para eliminar esta ubicación.');
            return $this->redirectToRoute('location_index');
        }

        // Verificar token CSRF para seguridad
        if ($this->isCsrfTokenValid('delete'.$location->getId(), $request->request->get('_token'))) {
            // Verificar que no tenga usuarios, profesionales, pacientes o servicios asociados
            if ($location->getUsers()->count() > 0 || 
                $location->getProfessionals()->count() > 0 || 
                $location->getPatients()->count() > 0 || 
                $location->getServices()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar la ubicación porque tiene datos asociados.');
                return $this->redirectToRoute('location_show', ['id' => $location->getId()]);
            }

            $this->entityManager->remove($location);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Ubicación eliminada exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('location_index');
    }
}