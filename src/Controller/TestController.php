<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        return $this->render('test/index.html.twig', [
            'message' => '¡Hola! Este es un controller de prueba',
            'timestamp' => new \DateTime(),
        ]);
    }

    #[Route('/test/json', name: 'app_test_json')]
    public function apiTest(): Response
    {
        return $this->json([
            'status' => 'success',
            'message' => 'API de prueba funcionando',
            'data' => [
                'project' => 'TurnoBoost',
                'framework' => 'Symfony',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        ]);
    }
}