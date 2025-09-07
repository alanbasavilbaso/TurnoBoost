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
            $this->addFlash('error', 'eee Has llegado al límite de tu plan actual (Para agregar un nuevo local contactanos)');
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
                // La empresa ya está asignada arriba
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

    #[Route('/{id}', name: 'location_show', methods: ['GET'])]
    public function show(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que la location pertenece a la empresa del usuario
        if ($location->getCompany() !== $user->getCompany()) {
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

    #[Route('/{id}/form', name: 'location_edit_form', methods: ['GET'])]
    public function editForm(Location $location): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de edición
        if (!$user->canEdit($user->getCompany())) {
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