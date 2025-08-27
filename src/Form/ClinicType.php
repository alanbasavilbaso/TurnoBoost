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
                ]
                // Eliminé las constraints
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Dirección',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ingrese la dirección completa',
                    'rows' => 3
                ]
                // Eliminé las constraints
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
                    'placeholder' => 'clinica@ejemplo.com'
                ]
                // Eliminé las constraints
            ]);
            // Eliminé el botón submit automático
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Clinic::class,
            'validation_groups' => false, // Deshabilita la validación automática
        ]);
        // Eliminé la opción submit_label ya que no se usa
    }
}