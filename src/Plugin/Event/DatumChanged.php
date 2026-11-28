<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\DatumView;

/**
 * Dispatched after a datum (a field of an item or a collection) has been created, updated or deleted.
 * $before is null on creation, $after is null on deletion.
 */
final readonly class DatumChanged
{
    public function __construct(
        public ?DatumView $before,
        public ?DatumView $after,
    ) {
    }

    public function isCreation(): bool
    {
        return !$this->before instanceof DatumView;
    }

    public function isDeletion(): bool
    {
        return !$this->after instanceof DatumView;
    }
}
