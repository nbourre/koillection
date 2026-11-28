<?php

declare(strict_types=1);

namespace App\Tests\App\Plugin;

use App\Entity\Item;
use App\Enum\DatumTypeEnum;
use App\Plugin\Dto\DatumInput;
use App\Plugin\Writer\EntityNotFoundException;
use App\Plugin\Writer\ItemWriter;
use App\Tests\AppTestCase;
use App\Tests\Factory\CollectionFactory;
use App\Tests\Factory\DatumFactory;
use App\Tests\Factory\ItemFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ItemWriterTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private ItemWriter $writer;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->writer = $this->getContainer()->get(ItemWriter::class);
        $this->em = $this->getContainer()->get(EntityManagerInterface::class);
    }

    public function test_create_item_with_data(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);

        // Act
        $view = $this->writer->create($collection->getId(), 'Canada 1 dollar 1987', [
            new DatumInput('Year', DatumTypeEnum::TYPE_NUMBER, '1987'),
            new DatumInput('Price', DatumTypeEnum::TYPE_PRICE, '2.50', 'CAD'),
        ], 2);

        // Assert
        $this->assertSame('Canada 1 dollar 1987', $view->name);
        $this->assertSame(2, $view->quantity);
        $this->assertSame($collection->getId(), $view->collectionId);
        $this->assertSame($user->getId(), $view->ownerId);
        $this->assertCount(2, $view->data);
        $this->assertSame('Year', $view->data[0]->label);
        $this->assertSame('1987', $view->data[0]->value);
        $this->assertSame('CAD', $view->data[1]->currency);

        $this->em->clear();
        $item = $this->em->find(Item::class, $view->id);
        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame($user->getId(), $item->getOwner()?->getId());
        $this->assertCount(2, $item->getData());
    }

    public function test_update_item_changes_only_given_values_and_matches_data_by_label(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $item = ItemFactory::createOne(['name' => 'Old name', 'quantity' => 3, 'collection' => $collection, 'owner' => $user]);
        DatumFactory::createOne(['item' => $item, 'owner' => $user, 'label' => 'Grade', 'type' => DatumTypeEnum::TYPE_TEXT, 'value' => 'VF', 'position' => 1]);
        DatumFactory::createOne(['item' => $item, 'owner' => $user, 'label' => 'Mint', 'type' => DatumTypeEnum::TYPE_TEXT, 'value' => 'Ottawa', 'position' => 2]);
        $this->em->clear();

        // Act
        $view = $this->writer->update($item->getId(), name: 'New name', data: [
            new DatumInput('Grade', DatumTypeEnum::TYPE_TEXT, 'XF'),
            new DatumInput('Country', DatumTypeEnum::TYPE_COUNTRY, 'CA'),
        ]);

        // Assert
        $this->assertSame('New name', $view->name);
        $this->assertSame(3, $view->quantity);
        $values = array_column(array_map(static fn ($datum): array => (array) $datum, $view->data), 'value', 'label');
        $this->assertSame(['Grade' => 'XF', 'Mint' => 'Ottawa', 'Country' => 'CA'], $values);
    }

    public function test_delete_item(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $item = ItemFactory::createOne(['collection' => $collection, 'owner' => $user]);
        $itemId = $item->getId();
        $this->em->clear();

        // Act
        $this->writer->delete($itemId);

        // Assert
        $this->em->clear();
        $this->assertNull($this->em->find(Item::class, $itemId));
    }

    public function test_create_in_unknown_collection_fails(): void
    {
        $this->expectException(EntityNotFoundException::class);

        $this->writer->create('00000000-0000-0000-0000-000000000000', 'Lost item');
    }
}
