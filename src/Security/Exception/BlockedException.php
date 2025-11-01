<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BlockedException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Your account has been blocked.';
    }
}