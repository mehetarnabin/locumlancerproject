<?php

namespace App\Twig\Runtime;

use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\Profession;
use App\Entity\Speciality;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(private EntityManagerInterface $em, private Security $security)
    {
        // Inject dependencies if needed
    }

    public function doSomething($value)
    {
        // ...
    }

    public function getProfessions()
    {
        return $this->em->getRepository(Profession::class)->findBy([], ['position' => 'ASC']);
    }

    public function getSpecialities($profession=null)
    {
        if(empty($profession)) {
            return $this->em->getRepository(Speciality::class)->findBy([], ['name' => 'ASC']);
        }else{
            return $this->em->getRepository(Speciality::class)->findBy(['profession' => $profession], ['name' => 'ASC']);
        }
    }

    public function getHeaderNotifications($userType)
    {
        if (in_array($userType, [User::TYPE_EMPLOYER, User::TYPE_PROVIDER])) {
            return $this->em->getRepository(Notification::class)->findBy([
                'userType' => $userType,
                'seen' => false,
                'user' => $this->security->getUser()
            ], ['createdAt' => 'DESC'], 5);
        }
        return $this->em->getRepository(Notification::class)->findBy([
            'userType' => $userType,
            'seen' => false
        ], ['createdAt' => 'DESC'], 5);
    }

    public function getUnseenNotificationCount($user)
    {
        return count($this->em->getRepository(Notification::class)->findBy([
            'user' => $user,
            'seen' => false
        ]));
    }


    public function getUnseenMessageCount($user)
    {
        return count($this->em->getRepository(Message::class)->findBy([
            'receiver' => $user,
            'seen' => false
        ]));
    }

    public function getUSStates()
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
