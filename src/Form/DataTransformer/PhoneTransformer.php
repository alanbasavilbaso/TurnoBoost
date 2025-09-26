<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class PhoneTransformer implements DataTransformerInterface
{
    /**
     * No transforma el valor - mantiene el formato +54XXXXXXXXXX
     */
    public function transform($value): string
    {
        return $value ?? '';
    }

    /**
     * No transforma el valor - mantiene el formato +54XXXXXXXXXX
     */
    public function reverseTransform($value): ?string
    {
        return $value ?: null;
    }
}