<?php

namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'profile_social_links')]
#[ORM\HasLifecycleCallbacks]
class ProfileSocialLink
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'profileSocialLinks')]
    #[ORM\JoinColumn(name: 'profile_id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\Column(name: 'label', length: 255)]
    private ?string $label = null;

    #[ORM\Column(name: 'url', type: 'text')]
    private ?string $url = null;

    #[ORM\Column(name: 'link_type', length: 40, nullable: true)]
    private ?string $linkType = null;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


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
        $this->lastModified = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }


    // Getters & Setters Functions

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        $this->profile = $profile;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getLinkType(): ?string
    {
        return $this->linkType;
    }

    public function setLinkType(?string $linkType): static
    {
        $this->linkType = $linkType;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }
}
