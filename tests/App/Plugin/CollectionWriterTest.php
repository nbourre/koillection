<?php

declare(strict_types=1);

namespace App\Tests\App\Plugin;

use App\Entity\Collection;
use App\Enum\DatumTypeEnum;
use App\Plugin\Dto\DatumInput;
use App\Plugin\Writer\CollectionWriter;
use App\Tests\AppTestCase;
use App\Tests\Factory\CollectionFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CollectionWriterTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private CollectionWriter $writer;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->writer = $this->getContainer()->get(CollectionWriter::class);
        $this->em = $this->getContainer()->get(EntityManagerInterface::class);
    }

    public function test_create_root_collection(): void
    {
        // Arrange
        $user = UserFactory::createOne();

        // Act
        $view = $this->writer->create('Coins', ownerId: $user->getId(), data: [
            new DatumInput('Country', DatumTypeEnum::TYPE_COUNTRY, 'CA'),
        ]);

        // Assert
        $this->assertSame('Coins', $view->title);
        $this->assertNull($view->parentId);
        $this->assertSame($user->getId(), $view->ownerId);
        $this->assertCount(1, $view->data);
    }

    public function test_create_child_collection_inherits_owner(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $parent = CollectionFactory::createOne(['owner' => $user]);

        // Act
        $view = $this->writer->create('Canada', parentId: $parent->getId());

        // Assert
        $this->assertSame($parent->getId(), $view->parentId);
        $this->assertSame($user->getId(), $view->ownerId);
    }

    public function test_create_root_collection_without_owner_fails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->writer->create('Orphan');
    }

    public function test_update_and_delete_collection(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $collection = CollectionFactory::createOne(['title' => 'Old', 'owner' => $user]);
        $collectionId = $collection->getId();
        $this->em->clear();

        // Act
        $view = $this->writer->update($collectionId, title: 'New');
        $this->writer->delete($collectionId);

        // Assert
        $this->assertSame('New', $view->title);
        $this->em->clear();
        $this->assertNull($this->em->find(Collection::class, $collectionId));
    }
}
