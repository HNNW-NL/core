<?php

namespace App\Entity\Log;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: "auditLogs")]
    #[ORM\JoinColumn(name: 'actor_account_id', nullable: true)]
    private ?Account $actorAccount = null;

    #[ORM\Column(name: 'actor_username', length: 255)]
    private ?string $actorUsername = null;

    #[ORM\Column(name: 'actor_email', length: 255)]
    private ?string $actorEmail = null;

    #[ORM\Column(name: 'action', length: 255)]
    private ?string $action = null;

    #[ORM\Column(name: 'entity_type', length: 255)]
    private ?string $entityType = null;

    #[ORM\Column(name: 'entity_id', length: 255)]
    private ?string $entityId = null;

    #[ORM\Column(name: 'old_values_json', type: "json")]
    private ?array $oldValuesJson = null;

    #[ORM\Column(name: 'new_values_json', type: "json")]
    private ?array $newValuesJson = null;

    #[ORM\Column(name: 'context_json', type: "json", nullable: true)]
    private ?array $contextJson = null;

    #[ORM\Column(name: 'request_ip', length: 50)]
    private ?string $requestIp = null;

    #[ORM\Column(name: 'user_agent', type: "text")]
    private ?string $userAgent = null;


    // Functions

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }


    /// Getters & Setters Functions

    public function getActorAccount(): ?Account
    {
        return $this->actorAccount;
    }

    public function setActorAccount(?Account $actorAccount): static
    {
        $this->actorAccount = $actorAccount;

        return $this;
    }

    public function getActorUsername(): ?string
    {
        return $this->actorUsername;
    }

    public function setActorUsername(?string $actorUsername): static
    {
        $this->actorUsername = $actorUsername;

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): static
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOldValuesJson(): ?array
    {
        return $this->oldValuesJson;
    }

    public function setOldValuesJson(?array $oldValuesJson): static
    {
        $this->oldValuesJson = $oldValuesJson;

        return $this;
    }

    public function getNewValuesJson(): ?array
    {
        return $this->newValuesJson;
    }

    public function setNewValuesJson(?array $newValuesJson): static
    {
        $this->newValuesJson = $newValuesJson;

        return $this;
    }

    public function getContextJson(): ?array
    {
        return $this->contextJson;
    }

    public function setContextJson(?array $contextJson): static
    {
        $this->contextJson = $contextJson;

        return $this;
    }

    public function getRequestIp(): ?string
    {
        return $this->requestIp;
    }

    public function setRequestIp(?string $requestIp): static
    {
        $this->requestIp = $requestIp;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }
}
