<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\HasLifecycleCallbacks]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', unique: true)]
    private ?int $id = null;

    #[ORM\Column(name: 'username', length: 255, unique: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'first_name', length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'email', length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private ?string $passwordHash = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $lastModified = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->lastModified = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
