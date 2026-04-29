<?php
namespace App\Entity\Common;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'statuses')]
class Status
{
    // Tables

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


    #[ORM\OneToMany(mappedBy: 'status', targetEntity: Account::class)]
    private Collection $accounts;


    // Functions


    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->accounts = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
