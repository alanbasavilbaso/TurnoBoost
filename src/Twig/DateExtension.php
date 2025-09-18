<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_spanish', [$this, 'formatDateInSpanish']),
        ];
    }

    /**
     * Formatea una fecha en español con formato completo
     */
    public function formatDateInSpanish(\DateTime $date): string
    {
        $dayNames = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes', 
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        
        $monthNames = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre'
        ];
        
        $dayName = $dayNames[$date->format('l')] ?? $date->format('l');
        $monthName = $monthNames[$date->format('F')] ?? $date->format('F');
        
        return sprintf('%s, %d de %s de %s', 
            $dayName, 
            $date->format('d'), 
            $monthName, 
            $date->format('Y')
        );
    }
}