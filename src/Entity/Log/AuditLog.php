<?php

namespace App\Entity\Log;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    // Tables

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

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

    #[ORM\Column(name: 'request_ip', length: 50)]
    private ?string $requestIp = null;

    #[ORM\Column(name: 'user_agent', type: "text")]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt;


    // Functions


    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
    }
}
