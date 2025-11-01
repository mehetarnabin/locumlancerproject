<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class NotVerifiedException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Your account not verified. Please check your email and verify your account.';
    }
}