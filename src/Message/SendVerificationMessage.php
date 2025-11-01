<?php

namespace App\Message;

final class SendVerificationMessage
{
    public function __construct(
        public string $userId
    ){}
}
