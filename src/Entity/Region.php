<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegionRepository::class)]
#[ORM\Table(name: 'region')]
#[ORM\UniqueConstraint(name: 'uniq_region_parent_slug', columns: ['parent_id', 'slug'])]
#[ORM\Index(name: 'idx_region_admin_code', columns: ['admin_code'])]
class Region
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $slug;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Region $parent = null;

    /** @var Collection<int, Region> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\Column(type: Types::SMALLINT, options: ['comment' => '0=country, 1=sido, 2=sigungu, 3=dong'])]
    private int $depth;

    #[ORM\Column(name: 'admin_code', length: 20, nullable: true)]
    private ?string $adminCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $lat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6, nullable: true)]
    private ?string $lng = null;

    /** @var Collection<int, ContentNode> */
    #[ORM\OneToMany(targetEntity: ContentNode::class, mappedBy: 'region')]
    private Collection $contentNodes;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->contentNodes = new ArrayCollection();
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

    public function getParent(): ?Region
    {
        return $this->parent;
    }

    public function setParent(?Region $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, Region> */
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

    public function getAdminCode(): ?string
    {
        return $this->adminCode;
    }

    public function setAdminCode(?string $adminCode): self
    {
        $this->adminCode = $adminCode;
        return $this;
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function setLat(?string $lat): self
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLng(): ?string
    {
        return $this->lng;
    }

    public function setLng(?string $lng): self
    {
        $this->lng = $lng;
        return $this;
    }

    /** @return Collection<int, ContentNode> */
    public function getContentNodes(): Collection
    {
        return $this->contentNodes;
    }
}
