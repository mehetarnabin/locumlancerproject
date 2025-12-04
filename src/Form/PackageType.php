<?php

namespace App\Form;

use App\Entity\Package;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class PackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Package Name',
                'attr' => ['placeholder' => 'e.g., Silver Provider Package']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Package Type',
                'choices' => Package::getTypeChoices(),
                'placeholder' => 'Choose a package type'
            ])
            ->add('target', ChoiceType::class, [
                'label' => 'Package For',
                'choices' => Package::getTargetChoices(),
                'placeholder' => 'Choose target user type'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Price',
                'currency' => 'USD',
                'scale' => 2
            ])
            ->add('durationDays', IntegerType::class, [
                'label' => 'Duration (days)',
                'attr' => ['min' => 1]
            ])
            ->add('maxJobPosts', IntegerType::class, [
                'label' => 'Max Job Posts',
                'required' => false,
                'attr' => ['min' => 0]
            ])
            ->add('maxApplications', IntegerType::class, [
                'label' => 'Max Applications',
                'required' => false,
                'attr' => ['min' => 0]
            ])
            ->add('features', CollectionType::class, [
                'label' => 'Features',
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'entry_options' => ['label' => false],
                'attr' => ['class' => 'feature-collection'],
                'required' => false
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Set as default package for this target',
                'required' => false,
                'help' => 'This will be automatically assigned to new users of the selected target type'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Package::class,
        ]);
    }
}