<?php

declare(strict_types=1);

namespace App\Plugin\Writer;

use App\Entity\Collection;
use App\Entity\Item;
use App\Plugin\Dto\DatumInput;
use App\Plugin\Dto\ItemView;
use App\Plugin\Mapper\ViewMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Write API for plugins: creates, updates and deletes items without exposing Doctrine entities.
 *
 * It does not check permissions: the calling plugin is responsible for checking that the current
 * user may modify the target collection or item. Domain events are dispatched by
 * DomainEventsListener after the flush, like for any other write path.
 */
#[Autoconfigure(public: true)]
final readonly class ItemWriter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ViewMapper $mapper,
        private DataApplier $dataApplier,
    ) {
    }

    /**
     * @param iterable<DatumInput> $data
     *
     * @throws EntityNotFoundException   when the collection does not exist
     * @throws ValidationFailedException when the resulting item is invalid
     */
    public function create(string $collectionId, string $name, iterable $data = [], int $quantity = 1): ItemView
    {
        $collection = $this->em->find(Collection::class, $collectionId)
            ?? throw EntityNotFoundException::collection($collectionId);

        $item = new Item()
            ->setName($name)
            ->setQuantity($quantity)
            ->setOwner($collection->getOwner())
            ->setCollection($collection)
            ->setVisibility($collection->getVisibility())
        ;
        $this->dataApplier->apply($item, $data, $collection->getOwner());

        $this->save($item);

        return $this->mapper->item($item);
    }

    /**
     * Only the given values are changed. Data is matched by label: an existing datum is updated,
     * a new label creates a datum, and data not mentioned is left untouched.
     *
     * @param iterable<DatumInput> $data
     *
     * @throws EntityNotFoundException   when the item does not exist
     * @throws ValidationFailedException when the resulting item is invalid
     */
    public function update(string $itemId, ?string $name = null, ?int $quantity = null, iterable $data = []): ItemView
    {
        $item = $this->find($itemId);

        if ($name !== null) {
            $item->setName($name);
        }

        if ($quantity !== null) {
            $item->setQuantity($quantity);
        }

        $this->dataApplier->apply($item, $data, $item->getOwner());

        $this->save($item);

        return $this->mapper->item($item);
    }

    /**
     * @throws EntityNotFoundException when the item does not exist
     */
    public function delete(string $itemId): void
    {
        $this->em->remove($this->find($itemId));
        $this->em->flush();
    }

    private function find(string $itemId): Item
    {
        return $this->em->find(Item::class, $itemId) ?? throw EntityNotFoundException::item($itemId);
    }

    private function save(Item $item): void
    {
        $violations = $this->validator->validate($item);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($item, $violations);
        }

        $this->em->persist($item);
        $this->em->flush();
    }
}
