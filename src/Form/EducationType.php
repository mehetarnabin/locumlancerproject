<?php

namespace App\Form;

use App\Entity\Education;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EducationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('school', TextType::class, [
                'label' => 'School/Institution',
                'attr' => ['placeholder' => 'Enter school or institution name']
            ])
            ->add('degree', TextType::class, [
                'label' => 'Degree',
                'attr' => ['placeholder' => 'e.g., Bachelor of Science, Master of Arts, etc.']
            ])
            ->add('fieldOfStudy', TextType::class, [
                'label' => 'Field of Study',
                'required' => false,
                'attr' => ['placeholder' => 'e.g., Computer Science, Nursing, etc.']
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'js-datepicker']
            ])
            ->add('endDate', DateType::class, [
                'label' => 'End Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'js-datepicker']
            ])
            ->add('grade', TextType::class, [
                'label' => 'Grade',
                'required' => false,
                'attr' => ['placeholder' => 'e.g., 3.8 GPA, First Class, etc.']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Describe your education, achievements, or relevant details...',
                    'rows' => 4
                ]
            ])
            ->add('country', TextType::class, [
                'label' => 'Country',
                'required' => false,
                'attr' => ['placeholder' => 'Country']
            ])
            ->add('state', TextType::class, [
                'label' => 'State',
                'required' => false,
                'attr' => ['placeholder' => 'State/Province']
            ])
            ->add('city', TextType::class, [
                'label' => 'City',
                'required' => false,
                'attr' => ['placeholder' => 'City']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Education::class,
        ]);
    }
}