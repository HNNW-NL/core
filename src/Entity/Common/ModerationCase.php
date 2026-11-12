<?php

namespace App\Entity\Common;

use App\Entity\Account\Account;
use App\Enum\ReviewWorkflowStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'moderation_cases')]
#[ORM\HasLifecycleCallbacks]
class ModerationCase
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'submittedModerationCases')]
    #[ORM\JoinColumn(name: 'submitted_by_id', nullable: false)]
    private ?Account $submittedBy = null;

    #[ORM\ManyToOne(inversedBy: 'reviewedModerationCases')]
    #[ORM\JoinColumn(name: 'reviewed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Account $reviewedBy = null;

    #[ORM\Column(name: 'content_type', length: 80)]
    private ?string $contentType = null;

    #[ORM\Column(name: 'content_id', type: UlidType::NAME)]
    private ?Ulid $contentId = null;

    #[ORM\Column(name: 'status', length: 25, enumType: ReviewWorkflowStatus::class)]
    private ?ReviewWorkflowStatus $status = null;

    #[ORM\Column(name: 'reason', type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getSubmittedBy(): ?Account
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?Account $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
    }

    public function getReviewedBy(): ?Account
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?Account $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(?string $contentType): static
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getContentId(): ?Ulid
    {
        return $this->contentId;
    }

    public function setContentId(?Ulid $contentId): static
    {
        $this->contentId = $contentId;
        return $this;
    }

    public function getStatus(): ?ReviewWorkflowStatus
    {
        return $this->status;
    }

    public function setStatus(?ReviewWorkflowStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

}
