<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Table(name: 'author')]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $slug;

    #[ORM\Column(name: 'real_name', length: 100)]
    private string $realName;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $credentials = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(name: 'photo_url', length: 500, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(name: 'verified_at', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    /** @var Collection<int, ContentNode> */
    #[ORM\OneToMany(targetEntity: ContentNode::class, mappedBy: 'author')]
    private Collection $contentNodes;

    public function __construct()
    {
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

    public function getRealName(): string
    {
        return $this->realName;
    }

    public function setRealName(string $realName): self
    {
        $this->realName = $realName;
        return $this;
    }

    public function getCredentials(): ?string
    {
        return $this->credentials;
    }

    public function setCredentials(?string $credentials): self
    {
        $this->credentials = $credentials;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    /** @return Collection<int, ContentNode> */
    public function getContentNodes(): Collection
    {
        return $this->contentNodes;
    }
}
