<?php

declare(strict_types=1);

namespace App\Plugin\Writer;

final class EntityNotFoundException extends \RuntimeException
{
    public static function item(string $id): self
    {
        return new self(\sprintf('Item "%s" not found.', $id));
    }

    public static function collection(string $id): self
    {
        return new self(\sprintf('Collection "%s" not found.', $id));
    }

    public static function user(string $id): self
    {
        return new self(\sprintf('User "%s" not found.', $id));
    }
}
