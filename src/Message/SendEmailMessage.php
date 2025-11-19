<?php

namespace App\Message;

class SendEmailMessage
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $message,
        private ?string $messageId = null
    ) {}

    public function getTo(): string { return $this->to; }
    public function getSubject(): string { return $this->subject; }
    public function getMessage(): string { return $this->message; }
    public function getMessageId(): ?string
    {
        return $this->messageId;
    }
}