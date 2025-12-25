<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'b_notification')]
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    const JOB_POSTED = 'job_posted';
    const JOB_APPLIED = 'job_applied';
    const JOB_EXPIRING = 'job_expiring';
    const PROVIDER_IN_REVIEW = 'provider_in_review';
    const PROVIDER_SHORTLIST = 'provider_shortlist';
    const PROVIDER_OFFERED = 'provider_offered';
    const PROVIDER_HIRED = 'provider_hired';
    const INVOICE_PAID = 'invoice_paid';
    const INVOICE_CREATED = 'invoice_created';
    const INVOICE_OVERDUE = 'invoice_overdue';
    const CASHBACK_CREATED = 'cashback_created';
    const REVIEW_REQUEST = 'review_request';
    const DOCUMENT_REQUESTED = 'document_requested';
    const DOCUMENT_PROVIDED = 'document_provided';
    const ONE_FILE_REQUESTED = 'one_file_requested';
    const ONE_FILE_PROVIDED = 'one_file_provided';
    const CONTRACT_SENT = 'contract_sent';
    const CONTRACT_SIGNED_SENT = 'contract_signed_sent';
    const PROVIDER_REVIEWED = 'provider_reviewed';
    const EMPLOYER_REVIEWED = 'employer_reviewed';
    const BOOKMARK_CREATED = 'bookmark_created';
    const DOCUMENT_EXPIRING = 'document_expiring';
    const JOB_MATCHING = 'job_matching';
    const MESSAGE_RECEIVED = 'message_received';
    const INTERVIEW_SCHEDULED = 'interview_scheduled';

    public static function getAllProviderNotificationTypes()
    {
        return [
            self::BOOKMARK_CREATED => 'Bookmark',
            self::CONTRACT_SENT => 'Contract',
            self::CASHBACK_CREATED => 'Cashback',
            self::DOCUMENT_EXPIRING => 'Document Expiry',
            self::DOCUMENT_REQUESTED => 'Document Requests',
            self::JOB_MATCHING => 'Job Matching',
            self::MESSAGE_RECEIVED => 'Message',
            self::REVIEW_REQUEST => 'Review Request',
            self::PROVIDER_IN_REVIEW => 'Under Review',
            self::PROVIDER_SHORTLIST => 'Shortlist',
            self::PROVIDER_OFFERED => 'Offered',
            self::PROVIDER_HIRED => 'Hired',
            self::PROVIDER_REVIEWED => 'Reviewed',
            self::ONE_FILE_REQUESTED => 'One File Requests',
            self::INTERVIEW_SCHEDULED => 'Interview Schedules',
        ];
    }

    public static function getAllEmployerNotificationTypes()
    {
        return [
            self::JOB_APPLIED => 'Job Applied',
            self::PROVIDER_IN_REVIEW => 'Provider In Review',
            self::PROVIDER_SHORTLIST => 'Provider Shortlist',
            self::PROVIDER_OFFERED => 'Provider Offered',
            self::PROVIDER_HIRED => 'Provider Hired',
            self::REVIEW_REQUEST => 'Review Request',
            self::EMPLOYER_REVIEWED => 'Employer Reviewed',
            self::MESSAGE_RECEIVED => 'Message',
            self::ONE_FILE_PROVIDED => 'One File Provided',
            self::DOCUMENT_PROVIDED => 'Document Provided',
            self::INVOICE_CREATED => 'Invoice Created',
            self::INVOICE_PAID => 'Invoice Paid',
            self::INVOICE_OVERDUE => 'Invoice Overdue',
            self::JOB_POSTED => 'Job Posted',
            self::INTERVIEW_SCHEDULED => 'Interview Scheduled',
        ];
    }

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $notificationType = null;

    #[ORM\Column(length: 255)]
    private ?string $userType = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column]
    private ?bool $seen = false;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private $extraData = [];

    use TimestampableEntity;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getNotificationType(): ?string
    {
        return $this->notificationType;
    }

    public function setNotificationType(string $notificationType): static
    {
        $this->notificationType = $notificationType;

        return $this;
    }

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function setUserType(?string $userType): void
    {
        $this->userType = $userType;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function isSeen(): ?bool
    {
        return $this->seen;
    }

    public function setSeen(bool $seen): static
    {
        $this->seen = $seen;

        return $this;
    }

    public function getExtraData(): array
    {
        return $this->extraData;
    }

    public function setExtraData(array $extraData): void
    {
        $this->extraData = $extraData;
    }
}
