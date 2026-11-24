<?php

declare(strict_types=1);

namespace App\Tests\App\Item;

use App\Entity\Item;
use App\Enum\DatumTypeEnum;
use App\Enum\VisibilityEnum;
use App\Tests\AppTestCase;
use App\Tests\Factory\ChoiceListFactory;
use App\Tests\Factory\CollectionFactory;
use App\Tests\Factory\DatumFactory;
use App\Tests\Factory\ItemFactory;
use App\Tests\Factory\LoanFactory;
use App\Tests\Factory\TagFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ItemDuplicateTest extends AppTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
    }

    public function test_can_duplicate_item(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $relatedItem = ItemFactory::createOne(['name' => 'Frieren #2', 'collection' => $collection, 'owner' => $user]);
        $choiceList = ChoiceListFactory::createOne(['name' => 'Edition', 'choices' => ['Normal', 'Collector'], 'owner' => $user]);
        $item = ItemFactory::createOne([
            'name' => 'Frieren #1',
            'collection' => $collection,
            'owner' => $user,
            'quantity' => 3,
            'visibility' => VisibilityEnum::VISIBILITY_PRIVATE,
            'file' => $this->createFile('png'),
        ]);
        $tag = TagFactory::createOne(['owner' => $user, 'label' => 'Manga']);
        $item->addTag($tag);
        $item->addRelatedItem($relatedItem);
        \Zenstruck\Foundry\Persistence\save($item);

        DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 1, 'type' => DatumTypeEnum::TYPE_TEXT, 'label' => 'Authors', 'value' => 'Abe Tsukasa']);
        DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 2, 'type' => DatumTypeEnum::TYPE_PRICE, 'currency' => 'EUR', 'label' => 'Price', 'value' => '7.95']);
        DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 3, 'type' => DatumTypeEnum::TYPE_CHOICE_LIST, 'label' => 'Edition', 'value' => json_encode(['Collector']), 'choiceList' => $choiceList]);
        $fileDatum = DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 4, 'type' => DatumTypeEnum::TYPE_FILE, 'label' => 'File', 'fileFile' => $this->createFile('txt')]);
        $imageDatum = DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 5, 'type' => DatumTypeEnum::TYPE_IMAGE, 'label' => 'Image', 'fileImage' => $this->createFile('png')]);
        $signDatum = DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 6, 'type' => DatumTypeEnum::TYPE_SIGN, 'label' => 'Sign', 'fileImage' => $this->createFile('png')]);
        $videoDatum = DatumFactory::createOne(['owner' => $user, 'item' => $item, 'position' => 7, 'type' => DatumTypeEnum::TYPE_VIDEO, 'label' => 'Video', 'fileVideo' => $this->createFile('mp4')]);
        LoanFactory::createOne(['owner' => $user, 'item' => $item, 'lentTo' => 'Someone', 'lentAt' => new \DateTimeImmutable()]);

        // Act
        $crawler = $this->client->request(Request::METHOD_GET, '/items/' . $item->getId());
        $this->client->submit($crawler->filter('.inline-action-form')->form());

        // Assert: we land on the edit form of the copy
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="item"]');

        /** @var Item $copy */
        $copy = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Item::class)->findOneBy(['name' => 'Frieren #1 (copy)']);
        $this->assertNotNull($copy);
        $this->assertNotSame($item->getId(), $copy->getId());
        $this->assertSame($collection->getId(), $copy->getCollection()->getId());
        $this->assertSame(3, $copy->getQuantity());
        $this->assertSame(VisibilityEnum::VISIBILITY_PRIVATE, $copy->getVisibility());
        $this->assertSame($user->getId(), $copy->getOwner()->getId());
        $this->assertCount(1, $copy->getTags());
        $this->assertSame('Manga', $copy->getTags()->first()->getLabel());

        // Not copied
        $this->assertCount(0, $copy->getRelatedItems());
        $this->assertCount(0, $copy->getLoans());
        LoanFactory::assert()->count(1);

        // Data copied, original untouched
        $this->assertCount(7, $copy->getData());
        DatumFactory::assert()->count(14);
        $this->assertSame('Abe Tsukasa', $copy->getDatumByLabel('Authors')->getValue());
        $this->assertSame('7.95', $copy->getDatumByLabel('Price')->getValue());
        $this->assertSame('EUR', $copy->getDatumByLabel('Price')->getCurrency());
        $this->assertSame($choiceList->getId(), $copy->getDatumByLabel('Edition')->getChoiceList()->getId());
        $this->assertSame(json_encode(['Collector']), $copy->getDatumByLabel('Edition')->getValue());

        // Files are physically copied, not shared
        $this->assertNotNull($copy->getImage());
        $this->assertNotSame($item->getImage(), $copy->getImage());
        $this->assertFileExists($copy->getImage());
        $this->assertFileExists($copy->getImageSmallThumbnail());
        $this->assertFileExists($item->getImage());

        $copyFile = $copy->getDatumByLabel('File');
        $this->assertNotSame($fileDatum->getFile(), $copyFile->getFile());
        $this->assertFileExists($copyFile->getFile());
        $this->assertSame($fileDatum->getOriginalFilename(), $copyFile->getOriginalFilename());

        foreach (['Image' => $imageDatum, 'Sign' => $signDatum] as $label => $sourceDatum) {
            $copyDatum = $copy->getDatumByLabel($label);
            $this->assertNotSame($sourceDatum->getImage(), $copyDatum->getImage());
            $this->assertFileExists($copyDatum->getImage());
            $this->assertFileExists($sourceDatum->getImage());
        }

        $copyVideo = $copy->getDatumByLabel('Video');
        $this->assertNotSame($videoDatum->getVideo(), $copyVideo->getVideo());
        $this->assertFileExists($copyVideo->getVideo());
    }

    public function test_cant_duplicate_item_without_valid_csrf_token(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $item = ItemFactory::createOne(['collection' => $collection, 'owner' => $user]);

        // Act
        $this->client->request(Request::METHOD_POST, '/items/' . $item->getId() . '/duplicate', ['_token' => 'invalid']);

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        ItemFactory::assert()->count(1);
    }

    public function test_cant_duplicate_item_with_get(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $this->client->loginUser($user);
        $collection = CollectionFactory::createOne(['owner' => $user]);
        $item = ItemFactory::createOne(['collection' => $collection, 'owner' => $user]);

        // Act
        $this->client->request(Request::METHOD_GET, '/items/' . $item->getId() . '/duplicate');

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        ItemFactory::assert()->count(1);
    }

    public function test_cant_duplicate_item_of_another_user(): void
    {
        // Arrange
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $this->client->loginUser($user);
        $collection = CollectionFactory::createOne(['owner' => $otherUser]);
        $item = ItemFactory::createOne(['collection' => $collection, 'owner' => $otherUser]);

        // Act
        $this->client->request(Request::METHOD_POST, '/items/' . $item->getId() . '/duplicate', ['_token' => 'invalid']);

        // Assert
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        ItemFactory::assert()->count(1);
    }
}
