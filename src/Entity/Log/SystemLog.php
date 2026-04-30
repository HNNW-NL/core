<?php

namespace App\Entity\Log;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'system_logs')]
#[ORM\HasLifecycleCallbacks]
class SystemLog
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'code', type: "smallint")]
    private ?int $code = null;

    #[ORM\Column(name: 'level', length: 25)]
    private ?string $level = null;

    #[ORM\Column(name: 'message', type: "text")]
    private ?string $message = null;

    #[ORM\Column(name: 'route', type: "text", nullable: true)]
    private ?string $route = null;

    #[ORM\Column(name: 'method', length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(name: 'user_agent', type: "text", nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'context_json', type: "json")]
    private ?array $contextJson = null;

    #[ORM\Column(name: 'request_ip', length: 50, nullable: true)]
    private ?string $requestIp = null;


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
