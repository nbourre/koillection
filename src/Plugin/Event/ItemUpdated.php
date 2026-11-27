<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\ItemView;

/**
 * Dispatched after an item's own fields (name, quantity, collection...) have been updated and flushed.
 * Changes to its data are dispatched separately as DatumChanged.
 */
final readonly class ItemUpdated
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public ItemView $before,
        public ItemView $after,
        public array $changedFields,
    ) {
    }
}
