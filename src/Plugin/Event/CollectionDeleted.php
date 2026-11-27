<?php

declare(strict_types=1);

namespace App\Plugin\Event;

use App\Plugin\Dto\CollectionView;

final readonly class CollectionDeleted
{
    public function __construct(
        public CollectionView $collection,
    ) {
    }
}
