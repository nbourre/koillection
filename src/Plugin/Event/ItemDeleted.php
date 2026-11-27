<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\ItemView;

/**
 * Dispatched after an item has been deleted and the deletion flushed. Holds its last known state.
 */
final readonly class ItemDeleted
{
    public function __construct(
        public ItemView $item,
    ) {
    }
}
