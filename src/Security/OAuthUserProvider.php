<?php

namespace App\Security;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

class OAuthUserProvider implements OAuthAwareUserProviderInterface
{
    public function __construct(private EntityManagerInterface $em){}

    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $email = $response->getEmail();
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($user) {
            // Update the user's information from the response if needed
            $user->setName($response->getRealName());
            $user->setProfilePicture($response->getProfilePicture());
            $user->setOauthData($response->getData());
            $user->setVerified(true);
        } else {
            // If the user does not exist, create a new one
            $user = new User();
            $user->setEmail($email);
            $user->setName($response->getRealName());
            $user->setOAuthProvider($response->getResourceOwner()->getName());
            $user->setOAuthId($response->getUsername());
            $user->setPassword(Uuid::v4());
            $user->setProfilePicture($response->getProfilePicture());
            $user->setOauthData($response->getData());
            $user->setVerified(true);
            // Set any other properties from the response as needed
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
