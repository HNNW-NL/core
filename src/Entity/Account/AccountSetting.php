<?php
namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'account_settings')]
#[ORM\HasLifecycleCallbacks]
class AccountSetting
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: "setting")]
    #[ORM\JoinColumn(name: 'account_id', unique: true, nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(name: 'language', length: 5, nullable: false)]
    private ?string $language = "nl-NL";

    #[ORM\Column(name: 'email_notifications_enabled', type: "boolean", nullable: false)]
    private bool $emailNotificationsEnabled = true;

    #[ORM\Column(name: 'profile_visibility', length: 10, nullable: false)]
    private ?string $profileVisibility = "private";

    #[ORM\Column(name: 'theme', length: 15, nullable: false)]
    private ?string $theme = "dark";

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    /* Insert reverse FKs here */


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
        $this->lastModified = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }
}
