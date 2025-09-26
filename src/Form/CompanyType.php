<?php

namespace App\Form;

use App\Entity\Company;
use App\Form\DataTransformer\PhoneTransformer;
use App\Validator\DomainNotExcluded;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la Empresa',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: Clínica Dental San José'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El nombre de la empresa es obligatorio'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Descripción de la empresa, servicios que ofrece, etc.',
                    'maxlength' => 255,
                    'id' => 'description-field'
                ],
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'La descripción no puede exceder {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('domain', TextType::class, [
                'label' => 'Dominio para Reservas Online',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ej: mi-clinica-dental',
                    'pattern' => '[a-z0-9\\-]+',
                    'title' => 'Solo letras minúsculas, números y guiones'
                ],
                'help' => 'Este será usado como URL para las reservas online: turnoboost.com/tu-dominio',
                'constraints' => [
                    new NotBlank(['message' => 'El dominio es obligatorio']),
                    new Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'El dominio debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El dominio no puede exceder {{ limit }} caracteres'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-z0-9-]+$/',
                        'message' => 'El dominio solo puede contener letras minúsculas, números y guiones'
                    ]),
                    new DomainNotExcluded()
                ]
            ])
            ->add('minimumBookingTime', IntegerType::class, [
                'label' => 'Tiempo Mínimo de Reserva (minutos)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '60',
                    'min' => 0,
                    'max' => 10080
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El tiempo mínimo de reserva es obligatorio']),
                    new Range([
                        'min' => 0,
                        'max' => 10080,
                        'notInRangeMessage' => 'El tiempo mínimo debe estar entre {{ min }} y {{ max }} minutos'
                    ])
                ]
            ])
            ->add('maximumFutureTime', IntegerType::class, [
                'label' => 'Tiempo Máximo de Reserva (días)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '90',
                    'min' => 1,
                    'max' => 365
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El tiempo máximo de reserva es obligatorio']),
                    new Range([
                        'min' => 1,
                        'max' => 365,
                        'notInRangeMessage' => 'El tiempo máximo debe estar entre {{ min }} y {{ max }} días'
                    ])
                ]
            ])
            ->add('onlineBookingEnabled', CheckboxType::class, [
                'label' => 'Habilitar Reservas en Línea',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Habilita esta opción para que tus clientes puedan reservar por internet en tu sitio web.'
            ])
            ->add('cancellableBookings', CheckboxType::class, [
                'label' => 'Reservas Cancelables',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Permite que los clientes cancelen sus reservas'
            ])
            ->add('editableBookings', CheckboxType::class, [
                'label' => 'Reservas Editables',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Permite que los clientes editen sus reservas'
            ])
            ->add('minimumEditTime', IntegerType::class, [
                'label' => 'Tiempo Mínimo para Editar/Cancelar (minutos)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '120',
                    'min' => 0,
                    'max' => 10080
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El tiempo mínimo para editar es obligatorio']),
                    new Range([
                        'min' => 0,
                        'max' => 10080,
                        'notInRangeMessage' => 'El tiempo mínimo para editar debe estar entre {{ min }} y {{ max }} minutos'
                    ])
                ]
            ])
            ->add('maximumEdits', IntegerType::class, [
                'label' => 'Máximo de Ediciones Permitidas',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '3',
                    'min' => 0,
                    'max' => 10
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El máximo de ediciones es obligatorio']),
                    new Range([
                        'min' => 0,
                        'max' => 10,
                        'notInRangeMessage' => 'El máximo de ediciones debe estar entre {{ min }} y {{ max }}'
                    ])
                ]
            ])
            ->add('requireContactData', CheckboxType::class, [
                'label' => 'Requerir Datos de Contacto',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Si habilitas esta opción, al generar una nueva reserva desde la agenda, el cliente debe tener asociado como mínimo email o teléfono.'
            ])
            ->add('requireEmail', CheckboxType::class, [
                'label' => 'Requerir Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Los clientes deben proporcionar un email al reservar citas.'
            ])
            ->add('requirePhone', CheckboxType::class, [
                'label' => 'Requerir Teléfono',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Los clientes deben proporcionar un teléfono al reservar citas.'
            ])
            ->add('bookingLimitLevel', ChoiceType::class, [
                'label' => 'Limitar por',
                'choices' => [
                    'Por Empresa' => 'company',
                    'Por Local' => 'location',
                    'Por Profesional' => 'professional'
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Selecciona el nivel donde aplicar el límite de citas pendientes'
            ])
            ->add('maxPendingBookings', IntegerType::class, [
                'label' => 'Cantidad de Turnos Permitidos',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '5',
                    'min' => 1,
                    'max' => 50
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La cantidad máxima de turnos es obligatoria']),
                    new Range([
                        'min' => 1,
                        'max' => 50,
                        'notInRangeMessage' => 'La cantidad máxima debe estar entre {{ min }} y {{ max }} turnos'
                    ])
                ],
                'help' => 'Número máximo de turnos pendientes o a futuro que puede tener un cliente'
            ])
            ->add('onlinePaymentsEnabled', CheckboxType::class, [
                'label' => 'Habilitar Pagos en Línea',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Habilita los pagos en línea a través de Mercado Pago'
            ])
            ->add('primaryColor', ColorType::class, [
                'label' => 'Color Principal del Sitio Web',
                'attr' => [
                    'class' => 'form-control form-control-color',
                    'title' => 'Selecciona el color principal'
                ],
                'help' => 'Te sugerimos que utilices el color que usas para las comunicaciones de tu negocio, como redes sociales y web. Recomendamos que uses colores oscuros para tener mejor legibilidad.',
                'constraints' => [
                    new NotBlank(['message' => 'El color principal es obligatorio']),
                    new Regex([
                        'pattern' => '/^#[0-9A-Fa-f]{6}$/',
                        'message' => 'El color debe estar en formato hexadecimal válido (ej: #1a1a1a)'
                    ])
                ]
            ])
            // Campos de notificaciones
            ->add('emailNotificationsEnabled', CheckboxType::class, [
                'label' => 'Habilitar Notificaciones por Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Envía notificaciones por email cuando se crea, modifica o cancela un turno'
            ])
            ->add('whatsappNotificationsEnabled', CheckboxType::class, [
                'label' => 'Habilitar Notificaciones por WhatsApp',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Envía notificaciones por WhatsApp cuando se crea, modifica o cancela un turno'
            ])
            ->add('reminderEmailEnabled', CheckboxType::class, [
                'label' => 'Recordatorios por Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Envía recordatorios por email'
            ])
            ->add('reminderWhatsappEnabled', CheckboxType::class, [
                'label' => 'Recordatorios por WhatsApp',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Envía recordatorios por WhatsApp'
            ])
            ->add('firstReminderHoursBeforeAppointment', IntegerType::class, [
                'label' => 'Primer Recordatorio (horas antes)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '24',
                    'min' => 1,
                    'max' => 168
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Las horas del primer recordatorio son obligatorias']),
                    new Range([
                        'min' => 1,
                        'max' => 168,
                        'notInRangeMessage' => 'Las horas del primer recordatorio deben estar entre {{ min }} y {{ max }} horas'
                    ])
                ],
                'help' => 'Define cuántas horas antes de la reserva se enviará el primer recordatorio por email al cliente'
            ])
            ->add('secondReminderEnabled', CheckboxType::class, [
                'label' => 'Habilitar Segundo Recordatorio',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Habilita un segundo recordatorio más cercano a la cita'
            ])
            ->add('secondReminderHoursBeforeAppointment', IntegerType::class, [
                'label' => 'Segundo Recordatorio (horas antes)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '2',
                    'min' => 1,
                    'max' => 48
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Las horas del segundo recordatorio son obligatorias']),
                    new Range([
                        'min' => 1,
                        'max' => 48,
                        'notInRangeMessage' => 'Las horas del segundo recordatorio deben estar entre {{ min }} y {{ max }} horas'
                    ])
                ],
                'help' => 'Define cuántas horas antes de la reserva se enviará el segundo recordatorio por WhatsApp al cliente'
            ])
            ->add('phone', TextType::class, [
                'label' => 'Teléfono para WhatsApp',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '1112345678',
                    'pattern' => '^[0-9]{8,12}$',
                    'title' => 'Ingresa solo los dígitos del número (8 a 12 dígitos)'
                ],
                'help' => 'Solo los dígitos del número de teléfono (sin +54). Ejemplo: 1112345678',
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[0-9]{8,12}$/',
                        'message' => 'El número debe tener entre 8 y 12 dígitos'
                    ])
                ]
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo de la Empresa',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, GIF, WebP)',
                        'maxSizeMessage' => 'El archivo no puede ser mayor a 2MB'
                    ])
                ],
                'help' => 'Tamaño máximo: 2MB. Formatos: JPEG, PNG, GIF, WebP'
            ])
            ->add('coverFile', FileType::class, [
                'label' => 'Imagen de Portada',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, GIF, WebP)',
                        'maxSizeMessage' => 'El archivo no puede ser mayor a 5MB'
                    ])
                ],
                'help' => 'Te recomendamos un tamaño mínimo de 820x360px y un peso máximo de 5MB. Formatos: JPEG, PNG, GIF, WebP'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Guardar Configuración',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);

            $builder->get('phone')->addModelTransformer(new PhoneTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}