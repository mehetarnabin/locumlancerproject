<?php

namespace App\Security;

use App\Entity\User;
use App\Security\Exception\BlockedException;
use App\Security\Exception\NotVerifiedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppUserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if($token->getUser()->isVerified() == false){
            if ($request->hasSession()) {
                $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new NotVerifiedException());
            }

            $url = $this->getLoginUrl($request);

            return new RedirectResponse($url);
        }

        if($token->getUser()->isBlocked() == true){
            if ($request->hasSession()) {
                $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, new BlockedException());
            }

            $url = $this->getLoginUrl($request);

            return new RedirectResponse($url);
        }

        if($token->getUser()->getUserType() == User::TYPE_EMPLOYER){
            $nextUrl = $request->query->get('_next');

            if ($nextUrl) {
                return new RedirectResponse($nextUrl);
            }

            return new RedirectResponse($this->urlGenerator->generate('app_employer_dashboard'));
        }

        if($token->getUser()->getUserType() == User::TYPE_ADMIN){
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        $nextUrl = $request->query->get('_next');

        if ($nextUrl) {
            return new RedirectResponse($nextUrl);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if($token->getUser()->getUserType() == User::TYPE_PROVIDER){
            return new RedirectResponse($this->urlGenerator->generate('app_provider_dashboard'));
        }

         return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
