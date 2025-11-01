<?php

namespace App\Message;

class SendEmailMessage
{
    public function __construct(
        private string $to,
        private string $subject,
        private string $message
    ) {}

    public function getTo(): string { return $this->to; }
    public function getSubject(): string { return $this->subject; }
    public function getMessage(): string { return $this->message; }
}