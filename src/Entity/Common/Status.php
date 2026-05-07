<?php
namespace App\Entity\Common;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'statuses', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_status_name_scape", columns: ["name", "scope"])
])]
class Status
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'colour_hex', length: 9)]
    private ?string $colourHex = null;

    #[ORM\Column(name: 'scope', length: 255)]
    private ?string $scope = null;


    // Reverse FKs

    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'status')]
    private Collection $accounts;

    #[ORM\OneToMany(targetEntity: Organisation::class, mappedBy: 'status')]
    private Collection $organisations;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->accounts = new ArrayCollection();
        $this->organisations = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
