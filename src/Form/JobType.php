<?php

namespace App\Form;

use App\Entity\Employer;
use App\Entity\Job;
use App\Entity\Recruiter;
use App\Entity\User;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description', CKEditorType::class, ['required' => true])
            ->add('highlight', CKEditorType::class)
            ->add('country', HiddenType::class, ['data' => 'US'])
            ->add('state', ChoiceType::class, [
                'required' => true,
                'choices' => $this->getUSStates(),
                'placeholder' => 'Select a state',
            ])
            ->add('city', null, ['required' => true])
            ->add('streetAddress', null, ['required' => true])
            ->add('profession', null, ['required' => true])
            ->add('speciality', null, ['required' => true])
            ->add('schedule', null, ['required' => true])
            ->add('yearOfExperience')
            ->add('expirationDate', null, [
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime())->format('Y-m-d'), // today's date
                    'class' => 'js-datepicker'
                ],
            ])
            ->add('startDate', null, [
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'min' => (new \DateTime())->format('Y-m-d'), // today's date
                    'class' => 'js-datepicker'
                ],
            ])
            ->add('need', ChoiceType::class, [
                'required' => true,
                'label' => 'Need',
                'expanded' => true,
                'choices' => ['Urgent' => 'Urgent', 'Routine' => 'Routine', 'Long term' => 'Long term'],
            ])
            ->add('workType', ChoiceType::class, [
                'required' => true,
                'label' => 'Work Type',
                'expanded' => true,
                'choices' => ['Locums' => 'Locums', 'Part time' => 'Part time', 'Full time' => 'Full time'],
            ])
            ->add('payRate', ChoiceType::class, [
                'required' => true,
                'label' => 'Pay Rate',
                'expanded' => true,
                'choices' => ['Hourly' => 'Hourly', 'Daily' => 'Daily'],
            ])
            ->add('payRateHourly', null, ['label' => 'Pay Rate Hourly'])
            ->add('payRateDaily', null, ['label' => 'Pay Rate Daily'])
            ->add('postingType', ChoiceType::class, [
                'mapped' => false,
                'required' => false, // Optional, default to direct
                'choices' => [
                    'Direct Posting' => 'direct',
                    'Recruiter Mediated' => 'recruiter',
                ],
                'data' => 'direct', // Default
            ])
            ->add('recruiter', EntityType::class, [
                'mapped' => false,
                'class' => Recruiter::class,
                'choice_label' => function (Recruiter $recruiter) {
                    return $recruiter->getName(); // Assuming Recruiter has getName()
                },
                'required' => false,
                'placeholder' => 'Select a Recruiter',
            ])
            ->add('commissionRate', null, [
                'mapped' => false,
                'required' => false,
                'label' => 'Commission Rate (%)',
                'attr' => [
                    'placeholder' => 'e.g., 20',
                    'min' => '0',
                    'max' => '100',
                    'step' => '0.01'
                ],
            ]);

        if ($options['is_recruiter']) {
            $builder->add('employer', EntityType::class, [
                'class' => Employer::class,
                'choice_label' => 'companyName',
                'required' => true,
                'placeholder' => 'Select Client (Employer)',
                'label' => 'Client / Employer',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Job::class,
            'is_recruiter' => false,
        ]);
        $resolver->setAllowedTypes('is_recruiter', 'bool');
    }

    private function getUSStates(): array
    {
        return [
            'Alabama' => 'AL',
            'Alaska' => 'AK',
            'Arizona' => 'AZ',
            'Arkansas' => 'AR',
            'California' => 'CA',
            'Colorado' => 'CO',
            'Connecticut' => 'CT',
            'Delaware' => 'DE',
            'Florida' => 'FL',
            'Georgia' => 'GA',
            'Hawaii' => 'HI',
            'Idaho' => 'ID',
            'Illinois' => 'IL',
            'Indiana' => 'IN',
            'Iowa' => 'IA',
            'Kansas' => 'KS',
            'Kentucky' => 'KY',
            'Louisiana' => 'LA',
            'Maine' => 'ME',
            'Maryland' => 'MD',
            'Massachusetts' => 'MA',
            'Michigan' => 'MI',
            'Minnesota' => 'MN',
            'Mississippi' => 'MS',
            'Missouri' => 'MO',
            'Montana' => 'MT',
            'Nebraska' => 'NE',
            'Nevada' => 'NV',
            'New Hampshire' => 'NH',
            'New Jersey' => 'NJ',
            'New Mexico' => 'NM',
            'New York' => 'NY',
            'North Carolina' => 'NC',
            'North Dakota' => 'ND',
            'Ohio' => 'OH',
            'Oklahoma' => 'OK',
            'Oregon' => 'OR',
            'Pennsylvania' => 'PA',
            'Rhode Island' => 'RI',
            'South Carolina' => 'SC',
            'South Dakota' => 'SD',
            'Tennessee' => 'TN',
            'Texas' => 'TX',
            'Utah' => 'UT',
            'Vermont' => 'VT',
            'Virginia' => 'VA',
            'Washington' => 'WA',
            'West Virginia' => 'WV',
            'Wisconsin' => 'WI',
            'Wyoming' => 'WY',
        ];
    }
}
