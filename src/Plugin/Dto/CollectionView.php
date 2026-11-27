<?php

declare(strict_types=1);

namespace App\Plugin\Dto;

/**
 * Read-only snapshot of a Collection, exposed to plugins instead of the Doctrine entity.
 */
final readonly class CollectionView
{
    /**
     * @param list<DatumView> $data
     */
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $parentId,
        public ?string $ownerId,
        public array $data,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
