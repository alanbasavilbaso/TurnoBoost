<?php

namespace App\Form;

use App\Entity\Location;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la Ubicación',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el nombre de la ubicación'
                ]
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Dirección',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese la dirección completa',
                    'rows' => 3
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Teléfono',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: +1234567890'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'MiLocal@ejemplo.com'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
            'is_edit' => false
        ]);
    }
}