<?php

namespace App\Service;

class PhoneUtilityService
{
    /**
     * Limpia un número de teléfono eliminando todos los caracteres que no sean números
     */
    public function cleanPhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        
        // Eliminar todos los caracteres que no sean números
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Retornar null si el resultado está vacío
        return empty($cleaned) ? null : $cleaned;
    }
    
    /**
     * Valida que un número de teléfono tenga un formato básico válido
     */
    public function isValidPhoneNumber(?string $phone): bool
    {
        if (!$phone) {
            return false;
        }
        
        $cleaned = $this->cleanPhoneNumber($phone);
        
        // Verificar que tenga al menos 8 dígitos y máximo 15
        return $cleaned && strlen($cleaned) >= 8 && strlen($cleaned) <= 15;
    }
    
    /**
     * Formatea un número de teléfono para mostrar (solo números)
     */
    public function formatForDisplay(?string $phone): ?string
    {
        return $this->cleanPhoneNumber($phone);
    }
}