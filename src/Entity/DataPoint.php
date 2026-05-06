<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\DataPointKind;
use App\Repository\DataPointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DataPointRepository::class)]
#[ORM\Table(name: 'data_point')]
#[ORM\Index(name: 'idx_data_point_node_kind', columns: ['node_id', 'kind'])]
#[ORM\Index(name: 'idx_data_point_node_verified', columns: ['node_id', 'verified'])]
class DataPoint
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dataPoints')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ContentNode $node;

    #[ORM\Column(length: 20, enumType: DataPointKind::class)]
    private DataPointKind $kind;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank, Assert\Length(max: 200)]
    private string $title = '';

    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private mixed $value = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $source = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNode(): ContentNode
    {
        return $this->node;
    }

    public function setNode(ContentNode $node): self
    {
        $this->node = $node;
        return $this;
    }

    public function getKind(): DataPointKind
    {
        return $this->kind;
    }

    public function setKind(DataPointKind $kind): self
    {
        $this->kind = $kind;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
