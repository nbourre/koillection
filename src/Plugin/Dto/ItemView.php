<?php

declare(strict_types=1);

namespace App\Plugin\Dto;

/**
 * Read-only snapshot of an Item, exposed to plugins instead of the Doctrine entity.
 */
final readonly class ItemView
{
    /**
     * @param list<DatumView> $data
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public int $quantity,
        public ?string $collectionId,
        public ?string $ownerId,
        public array $data,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }
}
