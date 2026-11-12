<?php

namespace App\Entity\Common;

use App\Entity\Account\Account;
use App\Enum\ReviewWorkflowStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'taxonomy_suggestions')]
#[ORM\HasLifecycleCallbacks]
class TaxonomySuggestion
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'taxonomySuggestions')]
    #[ORM\JoinColumn(name: 'suggested_by_id', nullable: false)]
    private ?Account $suggestedBy = null;

    #[ORM\Column(name: 'taxonomy_type', length: 60)]
    private ?string $taxonomyType = null;

    #[ORM\Column(name: 'proposed_name', length: 255)]
    private ?string $proposedName = null;

    #[ORM\Column(name: 'reason', type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: 'status', length: 25, enumType: ReviewWorkflowStatus::class)]
    private ?ReviewWorkflowStatus $status = null;

    #[ORM\ManyToOne(inversedBy: 'reviewedTaxonomySuggestions')]
    #[ORM\JoinColumn(name: 'reviewed_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Account $reviewedBy = null;

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

    public function getSuggestedBy(): ?Account
    {
        return $this->suggestedBy;
    }

    public function setSuggestedBy(?Account $suggestedBy): static
    {
        $this->suggestedBy = $suggestedBy;
        return $this;
    }

    public function getTaxonomyType(): ?string
    {
        return $this->taxonomyType;
    }

    public function setTaxonomyType(?string $taxonomyType): static
    {
        $this->taxonomyType = $taxonomyType;
        return $this;
    }

    public function getProposedName(): ?string
    {
        return $this->proposedName;
    }

    public function setProposedName(?string $proposedName): static
    {
        $this->proposedName = $proposedName;
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

    public function getStatus(): ?ReviewWorkflowStatus
    {
        return $this->status;
    }

    public function setStatus(?ReviewWorkflowStatus $status): static
    {
        $this->status = $status;
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
