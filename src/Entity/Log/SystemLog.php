<?php

namespace App\Entity\Log;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'system_logs')]
class SystemLog
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

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

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(?int $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }
}
