<?php

namespace App\Form;

use App\Entity\Provider;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProviderReleaseAuthorizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['required' => true])
            ->add('releaseAuthorizationDate', null, ['required' => true, 'label' => 'Date'])
            ->add('ssn', null, ['required' => true, 'label' => 'SSN'])
            ->add('dob', null, ['required' => true, 'label' => 'Date of Birth'])
            ->add('countryOfBirth', CountryType::class, [
                'preferred_choices' => [
                    'United States' => 'US',
                ]
            ])
            ->add('stateOfBirth', TextType::class, ['required' => true])
            ->add('cityOfBirth', TextType::class, ['required' => true])
            ->add('releaseAndAuthorizationAccepted', CheckboxType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Provider::class,
        ]);
    }
}
