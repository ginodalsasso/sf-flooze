<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SpaceTypeEnum;
use App\Repository\SpaceRepository;
use App\Trait\SoftDeleteTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpaceRepository::class)]
#[ORM\Table(name: 'space')]
#[ORM\HasLifecycleCallbacks]
class Space
{
    use TimestampTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'spaces')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: SpaceTypeEnum::class)]
    private SpaceTypeEnum $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): SpaceTypeEnum
    {
        return $this->type;
    }

    public function setType(SpaceTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }
}
