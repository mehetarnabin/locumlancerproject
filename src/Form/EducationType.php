<?php

namespace App\Form;

use App\Entity\Education;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EducationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('country', CountryType::class, ['label' => 'Country',  'preferred_choices' => [
                'United States' => 'US',
            ]])
            ->add('state', TextType::class, ['label' => 'State', 'required' => true])
            ->add('city', TextType::class, ['label' => 'City', 'required' => true])
            ->add('school', null, ['label' => 'School Name'])
            ->add('degree', null, ['label' => 'Degree Name'])
//            ->add('fieldOfStudy')
            ->add('startDate', null, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'js-datepicker'
                ],
            ])
            ->add('endDate', null, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'js-datepicker'
                ],
            ])
//            ->add('grade')
//            ->add('description')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Education::class,
        ]);
    }
}
