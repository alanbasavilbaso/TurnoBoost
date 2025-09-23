<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ImageUploadService
{
    private HttpClientInterface $httpClient;
    private string $blobToken;

    public function __construct(HttpClientInterface $httpClient, string $blobToken)
    {
        $this->httpClient = $httpClient;
        $this->blobToken = $blobToken;
    }

    public function uploadLocationImage(UploadedFile $file, string $locationId): ?string
    {
        try {
            $filename = 'locations/' . $locationId . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // API REST de Vercel Blob
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            // Log error si quieres
            return null;
        }
    }

    public function uploadServiceImages(array $files, string $serviceId): array
    {
        $urls = [];
        
        foreach ($files as $index => $file) {
            $url = $this->uploadSingleServiceImage($file, $serviceId, $index);
            if ($url) {
                $urls[] = $url;
            }
        }
        
        return $urls;
    }

    private function uploadSingleServiceImage(UploadedFile $file, string $serviceId, int $index): ?string
    {
        try {
            $filename = 'services/' . $serviceId . '_' . $index . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }

    public function deleteImage(string $url): bool
    {
        try {
            // Extraer filename de la URL
            $parsedUrl = parse_url($url);
            $filename = ltrim($parsedUrl['path'], '/');
            
            $response = $this->httpClient->request('DELETE', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }

    public function uploadCompanyLogo(UploadedFile $file, int $companyId): ?string
    {
        try {
            $filename = 'companies/' . $companyId . '/logo_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }

    public function uploadCompanyCover(UploadedFile $file, int $companyId): ?string
    {
        try {
            $filename = 'companies/' . $companyId . '/cover_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }

    public function uploadServiceImage1(UploadedFile $file, int $serviceId): ?string
    {
        try {
            $filename = 'services/' . $serviceId . '/image1_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }

    public function uploadServiceImage2(UploadedFile $file, int $serviceId): ?string
    {
        try {
            $filename = 'services/' . $serviceId . '/image2_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }

    public function uploadProfessionalProfile(UploadedFile $file, int $professionalId): ?string
    {
        try {
            $filename = 'professionals/' . $professionalId . '/profile_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            $response = $this->httpClient->request('PUT', 'https://blob.vercel-storage.com/' . $filename, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->blobToken,
                    'Content-Type' => $file->getMimeType(),
                    'x-content-type' => $file->getMimeType(),
                ],
                'body' => file_get_contents($file->getPathname())
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data['url'] ?? null;
            }

            return null;
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }
}