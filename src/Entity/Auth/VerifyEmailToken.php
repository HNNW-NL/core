<?php

namespace App\Entity\Auth;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'verify_email_tokens')]
#[ORM\HasLifecycleCallbacks]
class VerifyEmailToken
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: "verifyEmailTokens")]
    #[ORM\JoinColumn(name: 'account_id', nullable: false, unique: true)]
    private ?Account $account = null;

    #[ORM\Column(name: 'email_to_verify', length: 255)]
    private ?string $emailToVerify = null;

    #[ORM\Column(name: 'token_hash', length: 255)]
    private ?string $tokenHash = null;

    #[ORM\Column(name: 'is_used', type: 'boolean')]
    private bool $isUsed = false;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;


    // Reverse FKs

    /* Insert reverse FKs here */


    // Functions

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
        $this->expiresAt = $this->id->getDateTime()->modify('+24 hours +5 minutes');
    }

    public function markAsUsed(): void
    {
        $this->isUsed = true;
    }

    public function markAsUnused(): void
    {
        $this->isUsed = false;
    }

    public function refreshExpiration(): void
    {
        $this->expiresAt = (new \DateTimeImmutable())->modify("+24 hours +5 minutes");
    }


    /// Getters & Setters Functions

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getEmailToVerify(): ?string
    {
        return $this->emailToVerify;
    }

    public function setEmailToVerify(?string $emailToVerify): static
    {
        $this->emailToVerify = $emailToVerify;

        return $this;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(?string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
