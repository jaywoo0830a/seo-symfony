<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
#[ORM\Table(name: 'theme')]
#[ORM\UniqueConstraint(name: 'uniq_theme_parent_slug', columns: ['parent_id', 'slug'])]
class Theme
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank, Assert\Length(max: 50)]
    private string $slug = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Theme $parent = null;

    /** @var Collection<int, Theme> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\Column(type: Types::SMALLINT, options: ['comment' => '0=root, 1=primary, 2=sub'])]
    #[Assert\Range(min: 0, max: 2)]
    private int $depth = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ContentNode> */
    #[ORM\OneToMany(targetEntity: ContentNode::class, mappedBy: 'theme')]
    private Collection $contentNodes;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->contentNodes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getParent(): ?Theme
    {
        return $this->parent;
    }

    public function setParent(?Theme $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, Theme> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): self
    {
        $this->depth = $depth;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ContentNode> */
    public function getContentNodes(): Collection
    {
        return $this->contentNodes;
    }
}
