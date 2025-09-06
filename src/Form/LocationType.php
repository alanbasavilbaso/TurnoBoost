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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

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
                // Eliminé las constraints
            ])
            ->add('domain', TextType::class, [
                'label' => 'Dominio de Reservas',
                'help' => 'URL única para que tus clientes reserven turnos online. Solo letras minúsculas, números y guiones. Ejemplo: www.turnoboost.com/mi-centro-belleza',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'mi-centro-belleza',
                    'pattern' => '[a-z0-9\-]+',
                    'title' => 'Solo letras minúsculas, números y guiones'
                ],
                'help_attr' => [
                    'class' => 'form-text text-muted'
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
                // Eliminé las constraints
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'MiLocal@ejemplo.com'
                ]
                // Eliminé las constraints
            ]);
            // Eliminé el botón submit automático
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
            'is_edit' => false
        ]);
    }
}