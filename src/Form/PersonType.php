<?php

namespace App\Form;

use App\Entity\Patient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idDocument', TextType::class, [
                'label' => 'Documento de identidad',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'DNI, Pasaporte, etc.'
                ],
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'El documento no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Nombre*',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el nombre'
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
            ->add('lastName', TextType::class, [
                'label' => 'Apellido*',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el apellido'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El apellido es obligatorio']),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'El apellido debe tener al menos {{ limit }} caracteres',
                        'maxMessage' => 'El apellido no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('birthdate', DateType::class, [
                'label' => 'Fecha de nacimiento',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'date'
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el número de teléfono'
                ],
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'El teléfono no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correo electrónico',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese el correo electrónico'
                ],
                'constraints' => [
                    new Email(['message' => 'Ingrese un correo electrónico válido']),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'El correo no puede tener más de {{ limit }} caracteres'
                    ])
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Notas adicionales sobre el cliente'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Patient::class,
            'is_edit' => false
        ]);
    }
}