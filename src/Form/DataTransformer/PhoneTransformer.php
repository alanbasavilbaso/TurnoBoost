<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PhoneTransformer implements DataTransformerInterface
{
    /**
     * Transforma el valor almacenado (+54XXXXXXXXXX) al formato de visualización (XX XXXX XXXX)
     */
    public function transform($value): string
    {
        if (null === $value) {
            return '';
        }

        // Si el teléfono incluye +54, lo removemos para mostrar
        if (str_starts_with($value, '+54')) {
            $number = substr($value, 3); // Remover +54
            
            // Formatear para mostrar: 11 1234 5678
            if (strlen($number) >= 10) {
                $areaCode = substr($number, 0, strlen($number) === 10 ? 2 : (strlen($number) === 11 ? 3 : 4));
                $phoneNumber = substr($number, strlen($areaCode));
                
                if (strlen($phoneNumber) >= 6) {
                    $firstPart = substr($phoneNumber, 0, 4);
                    $secondPart = substr($phoneNumber, 4);
                    return "{$areaCode} {$firstPart} {$secondPart}";
                }
                
                return "{$areaCode} {$phoneNumber}";
            }
            
            return $number;
        }

        return $value;
    }

    /**
     * Transforma el valor del formulario (XX XXXX XXXX) al formato de almacenamiento (+54XXXXXXXXXX)
     */
    public function reverseTransform($value): ?string
    {
        if (!$value) {
            return null;
        }

        // Limpiar el valor (remover espacios y caracteres no numéricos)
        $cleanValue = preg_replace('/\D/', '', $value);
        
        if (empty($cleanValue)) {
            return null;
        }

        // Agregar el prefijo +54
        return '+54' . $cleanValue;
    }
}