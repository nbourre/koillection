<?php

declare(strict_types=1);

namespace App\Plugin\EventListener;

use App\Entity\Collection;
use App\Entity\Datum;
use App\Entity\Item;
use App\Plugin\Dto\CollectionView;
use App\Plugin\Dto\DatumView;
use App\Plugin\Dto\ItemView;
use App\Plugin\Event\CollectionCreated;
use App\Plugin\Event\CollectionDeleted;
use App\Plugin\Event\CollectionUpdated;
use App\Plugin\Event\DatumChanged;
use App\Plugin\Event\ItemCreated;
use App\Plugin\Event\ItemDeleted;
use App\Plugin\Event\ItemUpdated;
use App\Plugin\Mapper\ViewMapper;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Turns Doctrine changes into domain events for plugins.
 *
 * Changes are read in onFlush, so every write path is covered (controllers, REST API, imports, console),
 * but events are only dispatched in postFlush, once the data is actually stored. Listeners therefore
 * never see a change that was rolled back, and they can safely flush again.
 */
#[AsDoctrineListener(event: Events::onFlush, priority: -10)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::onClear)]
final class DomainEventsListener implements ResetInterface
{
    /**
     * Fields whose change is worth an Updated event. Technical fields (counters, cached values,
     * normalized copies, timestamps...) are ignored.
     */
    private const array ITEM_FIELDS = ['name', 'quantity', 'description', 'collection', 'visibility', 'image'];

    private const array COLLECTION_FIELDS = ['title', 'parent', 'visibility', 'color', 'image'];

    private const array DATUM_FIELDS = ['label', 'type', 'value', 'currency', 'position', 'item', 'collection', 'image', 'file', 'video'];

    /** @var list<object> */
    private array $pending = [];

    public function __construct(
        private readonly ViewMapper $mapper,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            match (true) {
                $entity instanceof Item => $this->pending[] = new ItemCreated($this->mapper->item($entity)),
                $entity instanceof Collection => $this->pending[] = new CollectionCreated($this->mapper->collection($entity)),
                $entity instanceof Datum => $this->pending[] = new DatumChanged(null, $this->mapper->datum($entity)),
                default => null,
            };
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            match (true) {
                $entity instanceof Item => $this->itemUpdated($uow, $entity),
                $entity instanceof Collection => $this->collectionUpdated($uow, $entity),
                $entity instanceof Datum => $this->datumUpdated($uow, $entity),
                default => null,
            };
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            match (true) {
                $entity instanceof Item => $this->pending[] = new ItemDeleted($this->mapper->item($entity)),
                $entity instanceof Collection => $this->pending[] = new CollectionDeleted($this->mapper->collection($entity)),
                $entity instanceof Datum => $this->pending[] = new DatumChanged($this->mapper->datum($entity), null),
                default => null,
            };
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Take the queue first: a listener may flush again and fill it anew
        $events = $this->pending;
        $this->pending = [];

        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }

    public function onClear(): void
    {
        $this->reset();
    }

    #[\Override]
    public function reset(): void
    {
        $this->pending = [];
    }

    private function itemUpdated(UnitOfWork $uow, Item $item): void
    {
        $changeset = $this->relevantChanges($uow->getEntityChangeSet($item), self::ITEM_FIELDS);
        if ($changeset === []) {
            return;
        }

        $after = $this->mapper->item($item);
        $before = new ItemView(
            id: $after->id,
            name: \array_key_exists('name', $changeset) ? $changeset['name'][0] : $after->name,
            quantity: \array_key_exists('quantity', $changeset) ? (int) $changeset['quantity'][0] : $after->quantity,
            collectionId: \array_key_exists('collection', $changeset) ? $changeset['collection'][0]?->getId() : $after->collectionId,
            ownerId: $after->ownerId,
            data: $after->data,
            createdAt: $after->createdAt,
            updatedAt: $after->updatedAt,
        );

        $this->pending[] = new ItemUpdated($before, $after, array_keys($changeset));
    }

    private function collectionUpdated(UnitOfWork $uow, Collection $collection): void
    {
        $changeset = $this->relevantChanges($uow->getEntityChangeSet($collection), self::COLLECTION_FIELDS);
        if ($changeset === []) {
            return;
        }

        $after = $this->mapper->collection($collection);
        $before = new CollectionView(
            id: $after->id,
            title: \array_key_exists('title', $changeset) ? $changeset['title'][0] : $after->title,
            parentId: \array_key_exists('parent', $changeset) ? $changeset['parent'][0]?->getId() : $after->parentId,
            ownerId: $after->ownerId,
            data: $after->data,
            createdAt: $after->createdAt,
            updatedAt: $after->updatedAt,
        );

        $this->pending[] = new CollectionUpdated($before, $after, array_keys($changeset));
    }

    private function datumUpdated(UnitOfWork $uow, Datum $datum): void
    {
        $changeset = $this->relevantChanges($uow->getEntityChangeSet($datum), self::DATUM_FIELDS);
        if ($changeset === []) {
            return;
        }

        $after = $this->mapper->datum($datum);
        $before = new DatumView(
            id: $after->id,
            label: \array_key_exists('label', $changeset) ? $changeset['label'][0] : $after->label,
            type: \array_key_exists('type', $changeset) ? $changeset['type'][0] : $after->type,
            value: \array_key_exists('value', $changeset) ? $changeset['value'][0] : $after->value,
            currency: \array_key_exists('currency', $changeset) ? $changeset['currency'][0] : $after->currency,
            position: \array_key_exists('position', $changeset) ? $changeset['position'][0] : $after->position,
            itemId: \array_key_exists('item', $changeset) ? $changeset['item'][0]?->getId() : $after->itemId,
            collectionId: \array_key_exists('collection', $changeset) ? $changeset['collection'][0]?->getId() : $after->collectionId,
        );

        $this->pending[] = new DatumChanged($before, $after);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $changeset
     * @param list<string>                             $fields
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function relevantChanges(array $changeset, array $fields): array
    {
        return array_filter(
            array_intersect_key($changeset, array_flip($fields)),
            static fn (array $change): bool => $change[0] !== $change[1],
        );
    }
}
