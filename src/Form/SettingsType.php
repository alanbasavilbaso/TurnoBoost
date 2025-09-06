<?php

namespace App\Form;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minimumBookingTime', IntegerType::class, [
                'label' => 'Tiempo mínimo de reserva (minutos)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '60',
                    'min' => 1
                ],
                'help' => 'Horas mínimas requeridas para que un cliente reserve. No se podrá reservar con menos anticipación.'
            ])
            ->add('maximumFutureTime', IntegerType::class, [
                'label' => 'Tiempo máximo futuro (meses)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '13',
                    'min' => 1
                ],
                'help' => 'Tiempo máximo que una persona puede reservar a futuro. No se permiten citas para más de este tiempo.'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar Configuración',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
        ]);
    }
}