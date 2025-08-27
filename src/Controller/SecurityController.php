<?php

namespace App\Controller;

use App\Entity\Clinic;
use App\Form\ClinicType;
use App\Form\LoginType;
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
    public function index(): Response
    {
        // Verificar que el usuario esté autenticado
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        return $this->render('security/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/mi-empresa', name: 'app_my_company')]
    public function myCompany(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Buscar si el usuario ya tiene una clínica
        $clinic = $entityManager->getRepository(Clinic::class)
            ->findOneBy(['createdBy' => $user]);
        
        // Si no tiene clínica, crear una nueva
        if (!$clinic) {
            $clinic = new Clinic();
            $clinic->setCreatedBy($user);
            $clinic->setEmail($user->getEmail()); // Tomar email del usuario
        }
        
        $form = $this->createForm(ClinicType::class, $clinic);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->persist($clinic);
                $entityManager->flush();
                
                $this->addFlash('success', 
                    $clinic->getId() ? 'Empresa actualizada correctamente.' : 'Empresa creada correctamente.'
                );
                
                return $this->redirectToRoute('app_index');
            } else {
                // Manejar errores de validación como mensajes flash
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('security/my_company.html.twig', [
            'form' => $form->createView(),
            'clinic' => $clinic,
            'is_edit' => $clinic->getId() !== null
        ]);
    }
}