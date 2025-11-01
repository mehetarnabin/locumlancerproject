<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['required' => true])
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'CV' => 'CV',
                    'Work Preference' => 'Work Preference',
                    'Insurance' => 'Insurance',
                    'Health Assessment' => 'Health Assessment',
                    'Risk Assessment' => 'Risk Assessment',
                    'References' => 'References',
                    'Documents' => 'Documents',
                ],
                'label' => 'Document Category',
            ])
            ->add('issueDate', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('expirationDate', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('fileName', FileType::class, [
                'label' => 'Choose File (PDF or DOCX)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '8M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid PDF or DOCX document',
                    ])
                ],
                'help' => 'Max upload size 8MB. Only PDF and DOCX allowed.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}
