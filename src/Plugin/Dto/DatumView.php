<?php

declare(strict_types=1);

namespace App\Plugin\Dto;

/**
 * Read-only snapshot of a Datum, exposed to plugins instead of the Doctrine entity.
 */
final readonly class DatumView
{
    public function __construct(
        public string $id,
        public ?string $label,
        public ?string $type,
        public ?string $value,
        public ?string $currency,
        public ?int $position,
        public ?string $itemId,
        public ?string $collectionId,
    ) {
    }
}
