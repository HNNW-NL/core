<?php

namespace App\Entity\Account;

use App\Entity\Account\Profile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'profile_certifications')]
#[ORM\HasLifecycleCallbacks]
class ProfileCertification
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'certifications')]
    #[ORM\JoinColumn(name: 'profile_id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'issuer', length: 255)]
    private ?string $issuer = null;

    #[ORM\Column(name: 'issued_on', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedOn = null;

    #[ORM\Column(name: 'expires_on', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresOn = null;

    #[ORM\Column(name: 'credential_id', length: 255, nullable: true)]
    private ?string $credentialId = null;

    #[ORM\Column(name: 'credential_url', type: 'text', nullable: true)]
    private ?string $credentialUrl = null;

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

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(?string $issuer): static
    {
        $this->issuer = $issuer;
        return $this;
    }

    public function getIssuedOn(): ?\DateTimeImmutable
    {
        return $this->issuedOn;
    }

    public function setIssuedOn(?\DateTimeImmutable $issuedOn): static
    {
        $this->issuedOn = $issuedOn;
        return $this;
    }

    public function getExpiresOn(): ?\DateTimeImmutable
    {
        return $this->expiresOn;
    }

    public function setExpiresOn(?\DateTimeImmutable $expiresOn): static
    {
        $this->expiresOn = $expiresOn;
        return $this;
    }

    public function getCredentialId(): ?string
    {
        return $this->credentialId;
    }

    public function setCredentialId(?string $credentialId): static
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getCredentialUrl(): ?string
    {
        return $this->credentialUrl;
    }

    public function setCredentialUrl(?string $credentialUrl): static
    {
        $this->credentialUrl = $credentialUrl;
        return $this;
    }

}
