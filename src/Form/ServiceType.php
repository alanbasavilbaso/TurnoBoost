<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Range;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del Servicio',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Consulta General, Limpieza Dental, etc.'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El nombre del servicio es obligatorio'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Descripción detallada del servicio...'
                ]
            ])
            ->add('defaultDurationMinutes', IntegerType::class, [
                'label' => 'Duración (minutos)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 60,
                    'step' => 1,
                    'placeholder' => '30'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La duración es obligatoria']),
                    new Range([
                        'min' => 0,
                        'max' => 60,
                        'notInRangeMessage' => 'La duración debe estar entre {{ min }} y {{ max }} minutos'
                    ])
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Precio',
                'required' => false,
                'currency' => 'ARS',
                'invalid_message' => 'Por favor ingrese un monto válido.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                    'step' => '0.01'
                ],
                'data_class' => null,
                'constraints' => [
                    new Type([
                        'type' => 'numeric',
                        'message' => 'El precio debe ser un número válido'
                    ]),
                    new Range([
                        'min' => 0,
                        'max' => 999999.99,
                        'notInRangeMessage' => 'El precio debe estar entre {{ min }} y {{ max }}'
                    ])
                ],
                'getter' => function (Service $service): ?float {
                    return $service->getPriceAsFloat();
                },
                'setter' => function (Service &$service, ?float $price): void {
                    $service->setPriceFromFloat($price);
                }
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Servicio Activo',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['is_edit'] ? 'Actualizar Servicio' : 'Crear Servicio',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
            'is_edit' => false
        ]);
    }
}