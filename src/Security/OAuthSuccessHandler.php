<?php

namespace App\Security;

use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use HWI\Bundle\OAuthBundle\Security\Core\Exception\AccountNotLinkedException;
use HWI\Bundle\OAuthBundle\Security\Http\Authenticator\OAuthAuthenticator;
//use HWI\Bundle\OAuthBundle\Security\Http\OAuthSuccessHandlerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
//use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class OAuthSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;
    private Security $security;

    public function __construct(RouterInterface $router, Security $security)
    {
        $this->router = $router;
        $this->security = $security;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        if (!$token instanceof OAuthToken) {
            throw new \RuntimeException('Expected an OAuthToken');
        }

        $user = $this->security->getUser();

        if (!$user) {
            throw new AccountNotLinkedException('User not found.');
        }

        return new RedirectResponse($this->router->generate('app_onboard'));
    }
}
