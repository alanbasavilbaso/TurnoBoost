<?php

namespace App\Form;

use App\Entity\Clinic;
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

class ClinicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre de la Clínica',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el nombre de la clínica'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'El nombre de la clínica es obligatorio'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'El nombre debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El nombre no puede exceder {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Dirección',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese la dirección completa',
                    'rows' => 3
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La dirección es obligatoria'
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 500,
                        'minMessage' => 'La dirección debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'La dirección no puede exceder {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Teléfono',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej: +1234567890'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'El teléfono es obligatorio'
                    ]),
                    new Length([
                        'min' => 8,
                        'max' => 20,
                        'minMessage' => 'El teléfono debe tener al menos {{ limit }} dígitos',
                        'maxMessage' => 'El teléfono no puede exceder {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'clinica@ejemplo.com'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'El email es obligatorio'
                    ]),
                    new Email([
                        'message' => 'Por favor ingrese un email válido'
                    ])
                ]
            ]);
            // Eliminé el botón submit automático
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Clinic::class,
        ]);
        // Eliminé la opción submit_label ya que no se usa
    }
}