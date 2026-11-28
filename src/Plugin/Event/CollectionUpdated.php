<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\CollectionView;

/**
 * Changes to a collection's data are dispatched separately as DatumChanged.
 */
final readonly class CollectionUpdated
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public CollectionView $before,
        public CollectionView $after,
        public array $changedFields,
    ) {
    }
}
