<?php

namespace App\Entity\Account;

use App\Entity\Admin\Admin;
use App\Entity\Auth\ResetPasswordToken;
use App\Entity\Auth\VerifyEmailToken;
use App\Entity\Common\Status;
use App\Entity\Log\AuditLog;
use App\Entity\Org\OrgMember;
use App\Entity\Project\Project;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\HasLifecycleCallbacks]
class Account implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'username', length: 255, unique: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'email', length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private ?string $passwordHash = null;

    #[ORM\ManyToOne(inversedBy: "accounts")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\Column(name: 'email_verified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'last_login_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "actorAccount", targetEntity: AuditLog::class)]
    private Collection $auditLogs;

    #[ORM\OneToMany(mappedBy: "account", targetEntity: OrgMember::class)]
    private Collection $orgMemberships;

    #[ORM\OneToOne(mappedBy: "account", targetEntity: Profile::class)]
    private ?Profile $profile = null;

    #[ORM\OneToMany(mappedBy: "account", targetEntity: Notification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: "senderAccount", targetEntity: Notification::class)]
    private Collection $sentNotifications;

    #[ORM\OneToOne(targetEntity: AccountSetting::class, mappedBy: "account")]
    private ?AccountSetting $setting = null;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: "ownerAccount")]
    private Collection $ownedProjects;

    #[ORM\OneToMany(targetEntity: ResetPasswordToken::class, mappedBy: "account")]
    private Collection $resetPasswordTokens;

    #[ORM\OneToMany(targetEntity: VerifyEmailToken::class, mappedBy: "account")]
    private Collection $verifyEmailTokens;

    #[ORM\OneToOne(targetEntity: Admin::class, mappedBy: "account")]
    private ?Admin $admin = null;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->auditLogs = new ArrayCollection();
        $this->orgMemberships = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->sentNotifications = new ArrayCollection();
        $this->ownedProjects = new ArrayCollection();
        $this->resetPasswordTokens = new ArrayCollection();
        $this->verifyEmailTokens = new ArrayCollection();
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
        $this->lastModified = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
    }

    public function unverifyEmail(): void
    {
        $this->emailVerifiedAt = null;
    }

    public function login(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }


    /// Symfony UserInterface & Security Functions

    /**
     * De unieke identifier voor dit account binnen Symfony Security (meestal e-mail of username).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Geeft de rollen van de gebruiker terug. Elk account krijgt sowieso ROLE_USER.
     * Als er een admin-relatie bestaat, wordt ROLE_ADMIN dynamisch toegevoegd.
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->admin !== null) {
            $roles[] = 'ROLE_ADMIN';
        }

        return array_unique($roles);
    }

    /**
     * Vertelt Symfony waar het gehashte wachtwoord staat voor PasswordAuthenticatedUserInterface.
     */
    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    /**
     * Wordt gebruikt om eventuele gevoelige, tijdelijke plain-text data te wissen na authenticatie.
     */
    public function eraseCredentials(): void
    {
        // Kan leeg blijven tenzij je een tijdelijke $plainPassword property gebruikt
    }


    /// Getters & Setters Functions

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
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

    public function getSetting(): ?AccountSetting
    {
        return $this->setting;
    }

    public function setSetting(?AccountSetting $setting): static
    {
        $this->setting = $setting;

        return $this;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    /** @return Collection<int, AuditLog> */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    /** @return Collection<int, OrgMember> */
    public function getOrgMemberships(): Collection
    {
        return $this->orgMemberships;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    /** @return Collection<int, Notification> */
    public function getSentNotifications(): Collection
    {
        return $this->sentNotifications;
    }

    /** @return Collection<int, Project> */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    /** @return Collection<int, ResetPasswordToken> */
    public function getResetPasswordTokens(): Collection
    {
        return $this->resetPasswordTokens;
    }

    /** @return Collection<int, VerifyEmailToken> */
    public function getVerifyEmailTokens(): Collection
    {
        return $this->verifyEmailTokens;
    }
}
