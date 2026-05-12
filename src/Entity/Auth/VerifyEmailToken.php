<?php
namespace App\Entity\Auth;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'verify_email_tokens')]
#[ORM\HasLifecycleCallbacks]
class VerifyEmailToken
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "verifyEmailTokens")]
    #[ORM\JoinColumn(name: 'account_id', nullable: false, unique: true)]
    private ?Account $account = null;

    #[ORM\Column(name: 'email_to_verify', length: 255)]
    private ?string $emailToVerify = null;

    #[ORM\Column(name: 'token_hash', length: 255)]
    private ?string $tokenHash = null;

    #[ORM\Column(name: 'is_used', type: 'boolean')]
    private bool $isUsed = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;


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
        $this->expiresAt = $now->modify("+24 hours +5 minutes");
    }
}
