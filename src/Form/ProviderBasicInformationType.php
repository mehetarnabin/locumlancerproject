<?php

namespace App\Form;

use App\Entity\Provider;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProviderBasicInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['required' => true])
            ->add('suffix', ChoiceType::class, ['required' => true, 'choices' => ['-Select Suffix-' => '', 'Jr' => 'Jr', 'Sr' => 'Sr', 'II' => 'II', 'III' => 'III']])
            ->add('title', ChoiceType::class, ['required' => true, 'choices' => ['-Select Title-' => '', 'Mr' => 'Mr', 'Mrs' => 'Mrs', 'Dr' => 'Dr', 'Prof' => 'Prof']])
            ->add('npiNumber', null, ['required' => true, 'label' => 'NPI Number'])
            ->add('phoneHome', null, ['label' => 'Home'])
            ->add('phoneWork', null, ['label' => 'Work'])
            ->add('phoneMobile', null, ['required' => true, 'label' => 'Mobile'])
            ->add('country', ChoiceType::class, [
                'choices' => [
                    'United States' => 'US',
                ]
            ])
            ->add('state', ChoiceType::class, [
                'required' => true,
                'choices' => $this->getUSStates(),
                'placeholder' => 'Select a state',
            ])
            ->add('city', null, ['required' => true])
            ->add('zipCode', null, ['label' => 'Zip Code']  )
            ->add('streetAddress', null, ['label' => 'Street Address'])
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
