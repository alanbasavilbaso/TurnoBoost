<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DomainNotExcluded extends Constraint
{
    public string $message = 'El dominio "{{ value }}" no está disponible porque está reservado por el sistema.';
}