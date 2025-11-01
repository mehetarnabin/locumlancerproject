<?php

namespace App\Event;

use App\Entity\Message;

class MessageEvent
{
    public const MESSAGE_CREATED = 'message.created';

    public function __construct(private Message $message){}

    public function getMessage(): Message
    {
        return $this->message;
    }
}