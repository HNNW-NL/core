<?php

namespace App\Entity\Account;

use App\Enum\ProfileVisibilityPermission;
use App\Enum\VisibilityLevel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'account_settings')]
#[ORM\HasLifecycleCallbacks]
class AccountSetting
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(inversedBy: "setting")]
    #[ORM\JoinColumn(name: 'account_id', unique: true, nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(name: 'language', length: 5, nullable: false)]
    private ?string $language = "nl-NL";

    #[ORM\Column(name: 'email_notifications_enabled', type: "boolean", nullable: false)]
    private bool $emailNotificationsEnabled = true;

    #[ORM\Column(name: 'profile_visibility', length: 10, nullable: false)]
    private ?string $profileVisibility = "private";

    #[ORM\Column(
        name: 'profile_public_mask',
        type: 'bigint',
        options: ['default' => '0']
    )]
    private string $profilePublicMask = '0';

    #[ORM\Column(
        name: 'profile_connections_mask',
        type: 'bigint',
        options: ['default' => '0']
    )]
    private string $profileConnectionsMask = '0';

    #[ORM\Column(name: 'theme', length: 15, nullable: false)]
    private ?string $theme = "dark";

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


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
        $this->lastModified = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
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

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function isEmailNotificationsEnabled(): bool
    {
        return $this->emailNotificationsEnabled;
    }

    public function setEmailNotificationsEnabled(bool $emailNotificationsEnabled): static
    {
        $this->emailNotificationsEnabled = $emailNotificationsEnabled;

        return $this;
    }

    public function getProfileVisibility(): ?string
    {
        return $this->profileVisibility;
    }

    public function setProfileVisibility(?string $profileVisibility): static
    {
        $this->profileVisibility = $profileVisibility;

        return $this;
    }

    public function getSectionVisibility(
        ProfileVisibilityPermission $section
    ): VisibilityLevel {
        $bit = $section->value;

        $isPublic = (((int) $this->profilePublicMask & $bit) !== 0);
        $isConnections = (((int) $this->profileConnectionsMask & $bit) !== 0);

        // Conflicting visibility settings must fail closed.
        if ($isPublic && $isConnections) {
            return VisibilityLevel::PRIVATE;
        }

        if ($isPublic) {
            return VisibilityLevel::PUBLIC;
        }

        if ($isConnections) {
            return VisibilityLevel::CONNECTIONS;
        }

        return VisibilityLevel::PRIVATE;
    }

    public function setSectionVisibility(
        ProfileVisibilityPermission $section,
        VisibilityLevel $level
    ): static {
        $bit = $section->value;

        $public = (int) $this->profilePublicMask;
        $connections = (int) $this->profileConnectionsMask;

        // Remove the section from both masks.
        $public &= ~$bit;
        $connections &= ~$bit;

        // Set the requested visibility.
        switch ($level) {
            case VisibilityLevel::PUBLIC:
                $public |= $bit;
                break;

            case VisibilityLevel::CONNECTIONS:
                $connections |= $bit;
                break;

            case VisibilityLevel::PRIVATE:
                break;
        }

        $this->profilePublicMask = (string) $public;
        $this->profileConnectionsMask = (string) $connections;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;

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
