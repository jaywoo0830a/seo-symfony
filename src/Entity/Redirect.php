<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RedirectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'redirect')]
#[ORM\Index(name: 'idx_redirect_from_node', columns: ['from_node_id'])]
class Redirect
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ContentNode $fromNode;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ContentNode $toNode;

    #[ORM\Column(options: ['default' => 301])]
    #[Assert\Choice(choices: [301, 302, 308])]
    private int $httpStatus = 301;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromNode(): ContentNode
    {
        return $this->fromNode;
    }

    public function setFromNode(ContentNode $fromNode): self
    {
        $this->fromNode = $fromNode;
        return $this;
    }

    public function getToNode(): ContentNode
    {
        return $this->toNode;
    }

    public function setToNode(ContentNode $toNode): self
    {
        $this->toNode = $toNode;
        return $this;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
