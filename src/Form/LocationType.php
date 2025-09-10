<?php

namespace App\Form;

use App\Entity\Location;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class LocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del Local',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el nombre del local'
                ]
            ])
            ->add('address', TextType::class, [
                'label' => 'Dirección',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese la dirección'
                ]
            ])
            ->add('phone', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el teléfono'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el email'
                ]
            ]);

        // Generar opciones de horas (00-23)
        $hourChoices = [];
        for ($i = 0; $i <= 23; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $hourChoices[$hour] = $hour;
        }

        // Generar opciones de minutos (00-59)
        $minuteChoices = [];
        for ($i = 0; $i <= 59; $i++) {
            $minute = str_pad($i, 2, '0', STR_PAD_LEFT);
            $minuteChoices[$minute] = $minute;
        }

        // Agregar campos de horarios para cada día de la semana
        $days = [
            0 => 'Domingo',
            1 => 'Lunes', 
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];

        foreach ($days as $dayNumber => $dayName) {
            $builder
                ->add("day_{$dayNumber}_enabled", CheckboxType::class, [
                    'label' => $dayName,
                    'required' => false,
                    'mapped' => false,
                    'attr' => [
                        'class' => 'form-check-input day-toggle',
                        'data-day' => $dayNumber
                    ]
                ])
                ->add("day_{$dayNumber}_start_hour", ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $hourChoices,
                    'placeholder' => 'Hora',
                    'attr' => [
                        'class' => 'form-select form-select-sm time-input',
                        'data-day' => $dayNumber,
                        'data-type' => 'start-hour'
                    ]
                ])
                ->add("day_{$dayNumber}_start_minute", ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $minuteChoices,
                    'placeholder' => 'Min',
                    'attr' => [
                        'class' => 'form-select form-select-sm time-input',
                        'data-day' => $dayNumber,
                        'data-type' => 'start-minute'
                    ]
                ])
                ->add("day_{$dayNumber}_end_hour", ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $hourChoices,
                    'placeholder' => 'Hora',
                    'attr' => [
                        'class' => 'form-select form-select-sm time-input',
                        'data-day' => $dayNumber,
                        'data-type' => 'end-hour'
                    ]
                ])
                ->add("day_{$dayNumber}_end_minute", ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $minuteChoices,
                    'placeholder' => 'Min',
                    'attr' => [
                        'class' => 'form-select form-select-sm time-input',
                        'data-day' => $dayNumber,
                        'data-type' => 'end-minute'
                    ]
                ]);
        }

        // Agregar evento para cargar datos existentes en modo edición
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {
            $location = $event->getData();
            $form = $event->getForm();
            
            if ($options['is_edit'] && $location && $location->getId()) {
                // Cargar datos existentes de availabilities
                foreach ($location->getAvailabilities() as $availability) {
                    $day = $availability->getWeekDay();
                    
                    // Marcar el día como habilitado
                    $form->get("day_{$day}_enabled")->setData(true);
                    
                    // Cargar horas y minutos de inicio
                    if ($availability->getStartTime()) {
                        $form->get("day_{$day}_start_hour")->setData($availability->getStartTime()->format('H'));
                        $form->get("day_{$day}_start_minute")->setData($availability->getStartTime()->format('i'));
                    }
                    
                    // Cargar horas y minutos de fin
                    if ($availability->getEndTime()) {
                        $form->get("day_{$day}_end_hour")->setData($availability->getEndTime()->format('H'));
                        $form->get("day_{$day}_end_minute")->setData($availability->getEndTime()->format('i'));
                    }
                }
            } else {
                // Modo NEW: Establecer valores por defecto
                // Lunes (día 0) habilitado por defecto con horario 09:00 - 20:00
                $form->get('day_0_enabled')->setData(true);
                $form->get('day_0_start_hour')->setData('09');
                $form->get('day_0_start_minute')->setData('00');
                $form->get('day_0_end_hour')->setData('20');
                $form->get('day_0_end_minute')->setData('00');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
            'is_edit' => false
        ]);
    }
}