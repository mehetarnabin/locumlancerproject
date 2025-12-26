<?php

namespace App\Form\Recruiter;

use App\Entity\Recruiter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

class RecruiterProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, ['label' => 'Company / Agency Name'])
            ->add('speciality', TextType::class, ['label' => 'Speciality (e.g., Locum Agency)'])
            // Add user fields via mapped false or assume Recruiter entity delegates?
            // Recruiter entity has 'user'. We likely want to edit User fields too (email, phone).
            // But Recruiter entity doesn't duplicate phone/email.
            // For now, let's stick to Recruiter fields.
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recruiter::class,
        ]);
    }
}
