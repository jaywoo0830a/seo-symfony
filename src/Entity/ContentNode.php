<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Repository\ContentNodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContentNodeRepository::class)]
#[ORM\Table(name: 'content_node')]
#[ORM\UniqueConstraint(name: 'uniq_content_node_theme_region', columns: ['theme_id', 'region_id'])]
#[ORM\Index(name: 'idx_content_node_status', columns: ['status'])]
#[ORM\Index(name: 'idx_content_node_last_review_at', columns: ['last_review_at'])]
class ContentNode
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'contentNodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Theme $theme;

    #[ORM\ManyToOne(inversedBy: 'contentNodes')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Region $region = null;

    #[ORM\ManyToOne(inversedBy: 'contentNodes')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Author $author = null;

    #[ORM\Column(length: 20, enumType: ContentStatus::class)]
    private ContentStatus $status = ContentStatus::Draft;

    #[ORM\Column(options: ['default' => 0, 'comment' => 'auto-managed by trigger; verified=true count'])]
    #[Assert\PositiveOrZero]
    private int $dataCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $introText = null;

    #[ORM\Column(length: 20, enumType: BodyTemplate::class, nullable: true)]
    private ?BodyTemplate $bodyTemplate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstPublishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastReviewAt = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
    private ?array $kpiSnapshot = null;

    /** @var Collection<int, DataPoint> */
    #[ORM\OneToMany(targetEntity: DataPoint::class, mappedBy: 'node', cascade: ['persist'], orphanRemoval: true)]
    private Collection $dataPoints;

    public function __construct()
    {
        $this->dataPoints = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getStatus(): ContentStatus
    {
        return $this->status;
    }

    public function setStatus(ContentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDataCount(): int
    {
        return $this->dataCount;
    }

    public function setDataCount(int $dataCount): self
    {
        $this->dataCount = $dataCount;
        return $this;
    }

    public function getIntroText(): ?string
    {
        return $this->introText;
    }

    public function setIntroText(?string $introText): self
    {
        $this->introText = $introText;
        return $this;
    }

    public function getBodyTemplate(): ?BodyTemplate
    {
        return $this->bodyTemplate;
    }

    public function setBodyTemplate(?BodyTemplate $bodyTemplate): self
    {
        $this->bodyTemplate = $bodyTemplate;
        return $this;
    }

    public function getFirstPublishedAt(): ?\DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function setFirstPublishedAt(?\DateTimeImmutable $firstPublishedAt): self
    {
        $this->firstPublishedAt = $firstPublishedAt;
        return $this;
    }

    public function getLastReviewAt(): ?\DateTimeImmutable
    {
        return $this->lastReviewAt;
    }

    public function setLastReviewAt(?\DateTimeImmutable $lastReviewAt): self
    {
        $this->lastReviewAt = $lastReviewAt;
        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getKpiSnapshot(): ?array
    {
        return $this->kpiSnapshot;
    }

    /** @param array<string, mixed>|null $kpiSnapshot */
    public function setKpiSnapshot(?array $kpiSnapshot): self
    {
        $this->kpiSnapshot = $kpiSnapshot;
        return $this;
    }

    /** @return Collection<int, DataPoint> */
    public function getDataPoints(): Collection
    {
        return $this->dataPoints;
    }
}
