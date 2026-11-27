<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\ItemView;

/**
 * Dispatched after an item has been created and flushed, whatever created it (UI, API, import...).
 */
final readonly class ItemCreated
{
    public function __construct(
        public ItemView $item,
    ) {
    }
}
