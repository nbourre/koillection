<?php

declare(strict_types=1);

namespace App\Plugin\Dto;

use App\Enum\DatumTypeEnum;

/**
 * Data a plugin passes to a writer to create or replace a Datum.
 */
final readonly class DatumInput
{
    public function __construct(
        public string $label,
        public string $type = DatumTypeEnum::TYPE_TEXT,
        public ?string $value = null,
        public ?string $currency = null,
    ) {
    }
}
