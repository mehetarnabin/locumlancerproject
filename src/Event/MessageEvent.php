<?php

namespace App\Event;

use App\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

final class MessageEvent extends Event
{
    public const MESSAGE_CREATED = 'message.created';
    public const MESSAGE_SENT = 'message.sent';
    public const MESSAGE_READ = 'message.read';
    public const MESSAGE_REPLIED = 'message.replied';
    public const MESSAGE_DELETED = 'message.deleted';

    public function __construct(
        private readonly Message $message,
        private readonly string $eventType = self::MESSAGE_CREATED
    ) {}

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function isMessageCreated(): bool
    {
        return $this->eventType === self::MESSAGE_CREATED;
    }

    public function isMessageSent(): bool
    {
        return $this->eventType === self::MESSAGE_SENT;
    }

    public function isMessageReplied(): bool
    {
        return $this->eventType === self::MESSAGE_REPLIED;
    }
}