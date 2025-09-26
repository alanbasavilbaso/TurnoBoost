<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\DeliveryTypeEnum;
use App\Entity\ServiceTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
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
                'invalid_message' => 'Por favor ingrese un precio válido.',
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
            // AGREGAR CAMPO ACTIVE
            // ->add('active', CheckboxType::class, [
            //     'label' => 'Servicio activo',
            //     'required' => false,
            //     'attr' => [
            //         'class' => 'form-check-input'
            //     ],
            //     'help' => 'Los servicios inactivos no aparecerán en las reservas'
            // ])
            // NUEVOS CAMPOS CON BOTONES DE RADIO
            ->add('onlineBookingEnabled', CheckboxType::class, [
                'label' => 'Reserva online disponible',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'onlineBookingEnabled'
                ],
                // 'help' => 'Permitir que los clientes reserven este servicio en línea'
            ])
            ->add('showPriceOnBooking', CheckboxType::class, [
                'label' => 'Mostrar precio en reservas online',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input booking-option',
                    'data-depends-on' => 'onlineBookingEnabled'
                ],
                'help' => 'El precio será visible para los clientes al reservar online'
            ])
            ->add('showDurationOnBooking', CheckboxType::class, [
                'label' => 'Mostrar duración en reservas online',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input booking-option',
                    'data-depends-on' => 'onlineBookingEnabled'
                ],
                'help' => 'La duración será visible para los clientes al reservar online'
            ])
            ->add('reminderNote', TextareaType::class, [
                'label' => 'Aclaraciones para recordatorios',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notas adicionales para recordatorios de citas...'
                ],
                'help' => 'Esta nota se incluirá en los recordatorios de citas'
            ])
            ->add('deliveryType', ChoiceType::class, [
                'label' => 'Modalidad de prestación',
                'choices' => [
                    'Presencial' => DeliveryTypeEnum::IN_PERSON,
                    'Online' => DeliveryTypeEnum::ONLINE,
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => [
                    'class' => 'delivery-type-options'
                ],
                'label_attr' => [
                    'class' => 'form-label fw-bold'
                ]
            ])
            ->add('serviceType', ChoiceType::class, [
                'choices' => [
                    'Regular' => ServiceTypeEnum::REGULAR,
                    // 'Por cupos' => ServiceTypeEnum::QUOTA_BASED,
                    'Recurrente' => ServiceTypeEnum::RECURRING,
                ],
                'label' => 'Tipo de servicio',
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'service-type-options']
            ])
            ->add('frequencyWeeks', ChoiceType::class, [
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                ],
                'label' => 'Frecuencia de repetición',
                // 'help' => 'Cada cuántas semanas se repite el turno',
                'required' => false,
                'expanded' => true,  // Esto hace que sean botones de radio
                'multiple' => false,
                'attr' => [
                    'class' => 'frequency-options'
                ],
                'row_attr' => [
                    'class' => 'frequency-field',
                    'style' => 'display: none;' // Oculto por defecto
                ]
            ])
            ->add('imageFile1', FileType::class, [
                'label' => 'Imagen Principal',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, GIF, WebP)',
                        'maxSizeMessage' => 'El archivo no puede ser mayor a 3MB'
                    ])
                ],
                'help' => ''
            ])
            ->add('imageFile2', FileType::class, [
                'label' => 'Imagen Secundaria',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, GIF, WebP)',
                        'maxSizeMessage' => 'El archivo no puede ser mayor a 3MB'
                    ])
                ],
                'help' => ''
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