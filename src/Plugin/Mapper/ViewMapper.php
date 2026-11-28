<?php

declare(strict_types=1);

namespace App\Plugin\Mapper;

use App\Entity\Collection;
use App\Entity\Datum;
use App\Entity\Item;
use App\Plugin\Dto\CollectionView;
use App\Plugin\Dto\DatumView;
use App\Plugin\Dto\ItemView;

/**
 * Builds the read-only views exposed to plugins from Doctrine entities.
 */
final class ViewMapper
{
    public function item(Item $item): ItemView
    {
        return new ItemView(
            id: (string) $item->getId(),
            name: $item->getName(),
            quantity: $item->getQuantity() ?? 1,
            collectionId: $item->getCollection()?->getId(),
            ownerId: $item->getOwner()?->getId(),
            data: $this->data($item->getData()),
            createdAt: $this->createdAt($item),
            updatedAt: $item->getUpdatedAt(),
        );
    }

    public function collection(Collection $collection): CollectionView
    {
        return new CollectionView(
            id: (string) $collection->getId(),
            title: $collection->getTitle(),
            parentId: $collection->getParent()?->getId(),
            ownerId: $collection->getOwner()?->getId(),
            data: $this->data($collection->getData()),
            createdAt: $collection->getCreatedAt(),
            updatedAt: $collection->getUpdatedAt(),
        );
    }

    public function datum(Datum $datum): DatumView
    {
        return new DatumView(
            id: (string) $datum->getId(),
            label: $datum->getLabel(),
            type: $datum->getType(),
            value: $datum->getValue(),
            currency: $datum->getCurrency(),
            position: $datum->getPosition(),
            itemId: $datum->getItem()?->getId(),
            collectionId: $datum->getCollection()?->getId(),
        );
    }

    /**
     * @param iterable<Datum> $data
     *
     * @return list<DatumView>
     */
    private function data(iterable $data): array
    {
        $views = [];
        foreach ($data as $datum) {
            $views[] = $this->datum($datum);
        }

        usort($views, static fn (DatumView $a, DatumView $b): int => ($a->position ?? 0) <=> ($b->position ?? 0));

        return $views;
    }

    private function createdAt(Item $item): ?\DateTimeImmutable
    {
        // createdAt is a non-nullable typed property only set on persist
        return new \ReflectionProperty(Item::class, 'createdAt')->isInitialized($item) ? $item->getCreatedAt() : null;
    }
}
