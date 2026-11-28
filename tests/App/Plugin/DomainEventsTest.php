<?php

declare(strict_types=1);

namespace App\Tests\App\Plugin;

use App\Entity\Item;
use App\Enum\DatumTypeEnum;
use App\Plugin\Dto\DatumInput;
use App\Plugin\Event\CollectionCreated;
use App\Plugin\Event\CollectionDeleted;
use App\Plugin\Event\CollectionUpdated;
use App\Plugin\Event\DatumChanged;
use App\Plugin\Event\ItemCreated;
use App\Plugin\Event\ItemDeleted;
use App\Plugin\Event\ItemUpdated;
use App\Plugin\Writer\CollectionWriter;
use App\Plugin\Writer\ItemWriter;
use App\Tests\AppTestCase;
use App\Tests\Factory\CollectionFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DomainEventsTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private const array EVENTS = [
        ItemCreated::class, ItemUpdated::class, ItemDeleted::class,
        CollectionCreated::class, CollectionUpdated::class, CollectionDeleted::class,
        DatumChanged::class,
    ];

    /** @var list<object> */
    private array $events = [];

    private EntityManagerInterface $em;

    private ItemWriter $itemWriter;

    private CollectionWriter $collectionWriter;

    #[\Override]
    protected function setUp(): void
    {
        $this->em = $this->getContainer()->get(EntityManagerInterface::class);
        $this->itemWriter = $this->getContainer()->get(ItemWriter::class);
        $this->collectionWriter = $this->getContainer()->get(CollectionWriter::class);

        $dispatcher = $this->getContainer()->get(EventDispatcherInterface::class);
        foreach (self::EVENTS as $eventClass) {
            $dispatcher->addListener($eventClass, function (object $event): void {
                $this->events[] = $event;
            });
        }
    }

    public function test_item_creation_dispatches_item_and_datum_events(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $this->events = [];

        // Act
        $view = $this->itemWriter->create($collection->getId(), 'Coin', [new DatumInput('Year', DatumTypeEnum::TYPE_NUMBER, '1987')]);

        // Assert
        $itemCreated = $this->only(ItemCreated::class);
        $this->assertSame($view->id, $itemCreated->item->id);
        $this->assertSame('Coin', $itemCreated->item->name);

        $datumChanged = $this->only(DatumChanged::class);
        $this->assertTrue($datumChanged->isCreation());
        $this->assertSame('1987', $datumChanged->after?->value);
        $this->assertSame($view->id, $datumChanged->after?->itemId);
    }

    public function test_item_update_carries_before_and_after(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $view = $this->itemWriter->create($collection->getId(), 'Old name', [new DatumInput('Price', DatumTypeEnum::TYPE_PRICE, '10', 'CAD')]);
        $this->events = [];

        // Act
        $this->itemWriter->update($view->id, name: 'New name', data: [new DatumInput('Price', DatumTypeEnum::TYPE_PRICE, '12', 'CAD')]);

        // Assert
        $itemUpdated = $this->only(ItemUpdated::class);
        $this->assertSame('Old name', $itemUpdated->before->name);
        $this->assertSame('New name', $itemUpdated->after->name);
        $this->assertSame(['name'], $itemUpdated->changedFields);

        $datumChanged = $this->only(DatumChanged::class);
        $this->assertSame('10', $datumChanged->before?->value);
        $this->assertSame('12', $datumChanged->after?->value);
        $this->assertSame('CAD', $datumChanged->after?->currency);
    }

    public function test_item_deletion_dispatches_last_known_state(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $view = $this->itemWriter->create($collection->getId(), 'Coin', [new DatumInput('Year', DatumTypeEnum::TYPE_NUMBER, '1987')]);
        $this->events = [];

        // Act
        $this->itemWriter->delete($view->id);

        // Assert
        $this->assertSame('Coin', $this->only(ItemDeleted::class)->item->name);
        $this->assertTrue($this->only(DatumChanged::class)->isDeletion());
    }

    public function test_changes_made_outside_the_writers_are_dispatched_too(): void
    {
        // Arrange: same path as a controller or the REST API, plain Doctrine
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $this->events = [];

        // Act
        $item = new Item()->setName('From the API')->setOwner($user)->setCollection($collection);
        $this->em->persist($item);
        $this->em->flush();

        // Assert
        $this->assertSame('From the API', $this->only(ItemCreated::class)->item->name);
    }

    public function test_technical_changes_dispatch_nothing(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $view = $this->itemWriter->create($collection->getId(), 'Coin');
        $this->events = [];

        // Act: viewing an item increments its counter
        $item = $this->em->find(Item::class, $view->id);
        $item?->setSeenCounter(($item->getSeenCounter() ?? 0) + 1);
        $this->em->flush();

        // Assert
        $this->assertSame([], $this->events);
    }

    public function test_collection_events(): void
    {
        // Arrange
        $user = UserFactory::createOne();

        // Act
        $created = $this->collectionWriter->create('Coins', ownerId: $user->getId());
        $this->collectionWriter->update($created->id, title: 'World coins');
        $this->collectionWriter->delete($created->id);

        // Assert
        $this->assertSame('Coins', $this->only(CollectionCreated::class)->collection->title);
        $updated = $this->only(CollectionUpdated::class);
        $this->assertSame('Coins', $updated->before->title);
        $this->assertSame('World coins', $updated->after->title);
        $this->assertSame('World coins', $this->only(CollectionDeleted::class)->collection->title);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function only(string $class): object
    {
        $matching = array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
        $this->assertCount(1, $matching, \sprintf('Expected exactly one %s event.', $class));

        return $matching[0];
    }
}
