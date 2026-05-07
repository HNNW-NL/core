<?php
namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "notifications")]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private ?Account $account = null;

    #[ORM\ManyToOne(inversedBy: "sentNotifications")]
    #[ORM\JoinColumn(name: 'sender_account_id', nullable: true)]
    private ?Account $senderAccount = null;

    #[ORM\Column(name: 'type', length: 50)]
    private ?string $type = null;

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'message', type: "text")]
    private ?string $message = null;

    #[ORM\Column(name: 'route', type: "text", nullable: true)]
    private ?string $route = null;

    #[ORM\Column(name: 'is_read', type: "boolean", nullable: false)]
    private bool $isRead = false;

    #[ORM\Column(name: 'data_json', type: "json", nullable: true)]
    private ?array $dataJson = null;

    #[ORM\Column(name: 'read_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


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
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
