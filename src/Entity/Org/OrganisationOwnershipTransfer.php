<?php

namespace App\Entity\Org;

use App\Entity\Account\Account;
use App\Enum\ReviewWorkflowStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'organisation_ownership_transfers')]
#[ORM\HasLifecycleCallbacks]
class OrganisationOwnershipTransfer
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'ownershipTransfers')]
    #[ORM\JoinColumn(name: 'organisation_id', nullable: false)]
    private ?Organisation $organisation = null;

    #[ORM\ManyToOne(inversedBy: 'outgoingOwnershipTransfers')]
    #[ORM\JoinColumn(name: 'from_account_id', nullable: false)]
    private ?Account $fromAccount = null;

    #[ORM\ManyToOne(inversedBy: 'incomingOwnershipTransfers')]
    #[ORM\JoinColumn(name: 'to_account_id', nullable: false)]
    private ?Account $toAccount = null;

    #[ORM\Column(name: 'status', length: 25, enumType: ReviewWorkflowStatus::class)]
    private ?ReviewWorkflowStatus $status = null;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

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

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): static
    {
        $this->organisation = $organisation;
        return $this;
    }

    public function getFromAccount(): ?Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?Account $fromAccount): static
    {
        $this->fromAccount = $fromAccount;
        return $this;
    }

    public function getToAccount(): ?Account
    {
        return $this->toAccount;
    }

    public function setToAccount(?Account $toAccount): static
    {
        $this->toAccount = $toAccount;
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

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

}
