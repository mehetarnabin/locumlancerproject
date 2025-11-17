<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
            ])
            ->add('gender', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Male' => 'M',
                    'Female' => 'F',
                    'Other' => 'O',
                ],
            ])
            ->add('phone1', TextType::class, [
                'required' => false,
            ])
            ->add('phone2', TextType::class, [
                'required' => false,
            ])
            ->add('address', TextType::class, [
                'required' => false,
                'label' => 'Address',
            ])

            ->add('email', TextType::class, [
                'required' => true,
                'label' => 'Email',
            ])
            
            ->add('nickname', TextType::class, [
                'required' => false,
                'label' => 'Nickname',
                'attr' => [
                    'placeholder' => 'Enter your preferred nickname or display name here',
                ],
            ])

            ->add('profilePictureFilename', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false, // handled manually in controller
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '8M',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpg',
                            'image/jpeg',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
