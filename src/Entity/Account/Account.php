<?php
namespace App\Entity\Account;

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

#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\HasLifecycleCallbacks]
class Account
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

    #[ORM\OneToOne(targetEntity: ResetPasswordToken::class, mappedBy: "account")]
    private Collection $resetPasswordTokens;

    #[ORM\OneToOne(targetEntity: ResetPasswordToken::class, mappedBy: "account")]
    private Collection $verifyEmailTokens;


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

    public function login(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
