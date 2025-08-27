<?php

namespace App\Form;

use App\Entity\Professional;
use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfessionalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $clinic = $options['clinic'] ?? null;
        
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre completo',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el nombre completo del profesional'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El nombre es obligatorio']),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'El nombre debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El nombre no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('specialty', TextType::class, [
                'label' => 'Especialidad',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Odontólogo, Fisioterapeuta, etc.'
                ],
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'La especialidad no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'profesional@ejemplo.com'
                ],
                'constraints' => [
                    new Email(['message' => 'Ingrese un email válido'])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+54 11 1234-5678'
                ],
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'El teléfono no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Activo',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ],
                'data' => true // Por defecto activo
            ]);
            
        // Agregar campos de horarios para cada día de la semana
        $days = [
            0 => 'Lunes',
            1 => 'Martes', 
            2 => 'Miércoles',
            3 => 'Jueves',
            4 => 'Viernes',
            5 => 'Sábado',
            6 => 'Domingo'
        ];
        
        foreach ($days as $dayNumber => $dayName) {
            // Domingo (6) desactivado por defecto
            $defaultEnabled = $dayNumber !== 6;
            
            $builder
                ->add("availability_{$dayNumber}_enabled", CheckboxType::class, [
                    'label' => "Trabajar {$dayName}",
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-check-input availability-toggle',
                        'data-day' => $dayNumber
                    ],
                    'label_attr' => [
                        'class' => 'form-check-label'
                    ],
                    'data' => $defaultEnabled
                ]);
                
            // Permitir hasta 2 rangos horarios por día con inputs de texto
            for ($range = 1; $range <= 2; $range++) {
                $builder
                    ->add("availability_{$dayNumber}_range{$range}_start", TextType::class, [
                        'label' => "Rango {$range} - Inicio",
                        'required' => false,
                        'mapped' => false,
                        'attr' => [
                            'class' => 'form-control time-input',
                            'data-day' => $dayNumber,
                            'data-range' => $range,
                            'placeholder' => 'HH:MM',
                            'maxlength' => '5',
                            'pattern' => '^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$',
                            'title' => 'Formato 24 horas (ej: 09:00)'
                        ],
                        'data' => $range === 1 ? '09:00' : '',
                        'constraints' => [
                            new Regex([
                                'pattern' => '/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
                                'message' => 'El formato debe ser HH:MM (00:00 - 23:59)'
                            ])
                        ]
                    ])
                    ->add("availability_{$dayNumber}_range{$range}_end", TextType::class, [
                        'label' => "Rango {$range} - Fin",
                        'required' => false,
                        'mapped' => false,
                        'attr' => [
                            'class' => 'form-control time-input',
                            'data-day' => $dayNumber,
                            'data-range' => $range,
                            'placeholder' => 'HH:MM',
                            'maxlength' => '5',
                            'pattern' => '^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$',
                            'title' => 'Formato 24 horas (ej: 18:00)'
                        ],
                        'data' => $range === 1 ? '18:00' : '',
                        'constraints' => [
                            new Regex([
                                'pattern' => '/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
                                'message' => 'El formato debe ser HH:MM (00:00 - 23:59)'
                            ])
                        ]
                    ]);
            }
        }
        
        // Agregar campo de servicios si se proporciona la clínica
        if ($clinic) {
            $builder->add('services', EntityType::class, [
                'class' => Service::class,
                'query_builder' => function ($repository) use ($clinic) {
                    return $repository->createQueryBuilder('s')
                        ->andWhere('s.clinic = :clinic')
                        ->andWhere('s.active = :active')
                        ->setParameter('clinic', $clinic)
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');
                },
                'choice_label' => function (Service $service) {
                    $price = $service->getPrice() ? '$' . number_format($service->getPrice(), 0, ',', '.') : 'Sin precio';
                    $duration = $service->getDefaultDurationMinutes() . ' min';
                    return $service->getName() . ' (' . $duration . ' - ' . $price . ')';
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'mapped' => false,
                'label' => 'Servicios que ofrece',
                'attr' => [
                    'class' => 'services-selection'
                ],
                'help' => 'Seleccione los servicios que este profesional puede ofrecer'
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Professional::class,
            'clinic' => null,
        ]);
        
        $resolver->setAllowedTypes('clinic', ['null', 'App\\Entity\\Clinic']);
    }
}