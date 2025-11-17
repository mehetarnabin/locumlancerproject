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
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'Driver\'s license' => 'Driver\'s license',
                    'Copy of passport' => 'Copy of passport',
                    'Passport photo 2" x 2"' => 'Passport photo 2" x 2"',
                    'USMLE Step 1-3/COMLEX' => 'USMLE Step 1-3/COMLEX',
                    'Specialty and Subspecialty Exam Certification' => 'Specialty and Subspecialty Exam Certification',
                    'Medical school diploma' => 'Medical school diploma',
                    'Postgraduate certificates (eg: Residency and Fellowship completion)' => 'Postgraduate certificates (eg: Residency and Fellowship completion)',
                    'Life support certifications' => 'Life support certifications',
                    'Active and Inactive medical licenses' => 'Active and Inactive medical licenses',
                    'All DEAs' => 'All DEAs',
                    'CSR (Controlled Substance Registration)' => 'CSR (Controlled Substance Registration)',
                    'Copy of undergraduate transcript' => 'Copy of undergraduate transcript',
                    'Vaccine titers: Hepatitis B, Varicella, MMR' => 'Vaccine titers: Hepatitis B, Varicella, MMR',
                    'Negative TB test (TST vs IGRA) in the last 12 months (or if positive a CXR is required)' => 'Negative TB test (TST vs IGRA) in the last 12 months (or if positive a CXR is required)',
                    'Influenza vaccine proof' => 'Influenza vaccine proof',
                    'COVID-19 vaccine proof' => 'COVID-19 vaccine proof',
                    'Mask fit testing' => 'Mask fit testing',
                    'MALPRACTICE (COI = CERTIFICATE OF INSURANCE of the last 10 years)' => 'MALPRACTICE (COI = CERTIFICATE OF INSURANCE of the last 10 years)',
                    'PROCEDURE LOGS last 2 years' => 'PROCEDURE LOGS last 2 years',
                    'Case Logs last 2 years' => 'Case Logs last 2 years',
                    'Application/ Certification: A list of everywhere lived for the last 10 years' => 'Application/ Certification: A list of everywhere lived for the last 10 years',
                    'Miscellaneous' => 'Miscellaneous',
                ],
                'label' => 'Document Name',
                'required' => true,
                'placeholder' => 'Select Document Name',
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
                'required' => $options['is_edit'] ?? true, // true for new upload, false for edit
                'constraints' => [
                    new File([
                        'maxSize' => '8M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
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
