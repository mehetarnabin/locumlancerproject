<?php

namespace App\Form;

use App\Entity\Provider;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProviderWorkPreferenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('desiredPayRate', ChoiceType::class, [
                'label' => 'Desired Pay Rate',
                'required' => true,
                'expanded' => true,
                'choices' => ['Hourly' => 'Hourly', 'Daily' => 'Daily'],
            ])
            ->add('payRateHourly', null, ['label' => 'Pay Rate Hourly'])
            ->add('payRateDaily', null, ['label' => 'Pay Rate Daily'])
            ->add('desiredHour', ChoiceType::class, [
                'label' => 'Desired Hour',
                'required' => true,
                'expanded' => true,
                'choices' => ['Full Time' => 'Full Time', 'Part Time' => 'Part Time'],
            ])
            ->add('desiredStates', ChoiceType::class, [
                'label' => 'Desired States',
                'choices' => $this->getUSStates(),
                'placeholder' => 'Select a state',
                'multiple' => true
            ])
            ->add('availabilityToStart', null, ['label' => 'Availability to Start'])
            ->add('willingToTravel', null, ['label' => 'Willing to Travel'])
            ->add('preferredPatientVolume', null, ['label' => 'Preferred Patient Volume'])
            ->add('profession', null, ['required' => true])
            ->add('specialities', null, ['required' => true])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Provider::class,
        ]);
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
