<?php

namespace App\Form;

use App\Entity\ProfessionalService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ProfessionalServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customDurationMinutes', IntegerType::class, [
                'label' => 'Duración personalizada (minutos)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: 45',
                    'min' => 1
                ],
                'constraints' => [
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'La duración debe ser mayor a 0 minutos'
                    ])
                ],
                'help' => 'Dejar vacío para usar la duración por defecto del servicio'
            ])
            ->add('customPrice', MoneyType::class, [
                'label' => 'Precio personalizado',
                'required' => false,
                'currency' => 'ARS',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ],
                'constraints' => [
                    new PositiveOrZero([
                        'message' => 'El precio debe ser mayor o igual a 0'
                    ])
                ],
                'help' => 'Dejar vacío para usar el precio por defecto del servicio'
            ]);
            
        // Agregar checkboxes para días de la semana
        $days = [
            'availableMonday' => 'Lunes',
            'availableTuesday' => 'Martes',
            'availableWednesday' => 'Miércoles',
            'availableThursday' => 'Jueves',
            'availableFriday' => 'Viernes',
            'availableSaturday' => 'Sábado',
            'availableSunday' => 'Domingo'
        ];
        
        foreach ($days as $field => $label) {
            $builder->add($field, CheckboxType::class, [
                'label' => $label,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfessionalService::class,
            'allow_extra_fields' => true, // Permitir campos extra
        ]);
    }
}