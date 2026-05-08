<?php

declare(strict_types=1);

namespace App\Util;

use Doctrine\ORM\QueryBuilder;

/**
 * Counts via COUNT(<root>.id), so the QueryBuilder must select a single
 * root entity with an id property (true for all admin lists today).
 */
final class Paginator
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /** @var list<object> */
    public readonly array $items;
    public readonly int $total;
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $totalPages;

    public function __construct(QueryBuilder $qb, int $page, int $perPage = self::DEFAULT_PER_PAGE)
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);

        $alias = $qb->getRootAliases()[0];

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select(sprintf('COUNT(%s.id)', $alias))
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $this->total = $total;
        $this->totalPages = $totalPages;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->items = $items;
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function hasPrev(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }

    public function prevPage(): int
    {
        return max(1, $this->page - 1);
    }

    public function nextPage(): int
    {
        return min($this->totalPages, $this->page + 1);
    }

    public function firstIndex(): int
    {
        return $this->isEmpty() ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastIndex(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }

    /**
     * @return list<int>
     */
    public function range(int $window = 2): array
    {
        $from = max(1, $this->page - $window);
        $to = min($this->totalPages, $this->page + $window);

        return range($from, $to);
    }
}
