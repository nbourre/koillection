<?php

declare(strict_types=1);

namespace App\Plugin\Writer;

use App\Entity\Collection;
use App\Entity\User;
use App\Plugin\Dto\CollectionView;
use App\Plugin\Dto\DatumInput;
use App\Plugin\Mapper\ViewMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Write API for plugins: creates, updates and deletes collections without exposing Doctrine entities.
 *
 * Like ItemWriter, it does not check permissions: that is the calling plugin's job.
 */
#[Autoconfigure(public: true)]
final readonly class CollectionWriter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ViewMapper $mapper,
        private DataApplier $dataApplier,
    ) {
    }

    /**
     * Creates a collection. With a parent, the new collection inherits its owner; without one,
     * $ownerId is required.
     *
     * @param iterable<DatumInput> $data
     *
     * @throws EntityNotFoundException   when the parent or the owner does not exist
     * @throws \InvalidArgumentException when neither a parent nor an owner is given
     * @throws ValidationFailedException when the resulting collection is invalid
     */
    public function create(string $title, ?string $parentId = null, ?string $ownerId = null, iterable $data = []): CollectionView
    {
        $parent = $parentId !== null
            ? $this->em->find(Collection::class, $parentId) ?? throw EntityNotFoundException::collection($parentId) : null;

        $owner = $parent?->getOwner();
        if (!$owner instanceof User && $ownerId !== null) {
            $owner = $this->em->find(User::class, $ownerId) ?? throw EntityNotFoundException::user($ownerId);
        }

        if (!$owner instanceof User) {
            throw new \InvalidArgumentException('A root collection needs an owner id.');
        }

        $collection = new Collection()
            ->setTitle($title)
            ->setOwner($owner)
        ;

        if ($parent instanceof Collection) {
            $collection
                ->setParent($parent)
                ->setVisibility($parent->getVisibility())
            ;
        }

        $this->dataApplier->apply($collection, $data, $owner);

        $this->save($collection);

        return $this->mapper->collection($collection);
    }

    /**
     * Only the given values are changed. Data is matched by label, as in ItemWriter::update().
     *
     * @param iterable<DatumInput> $data
     *
     * @throws EntityNotFoundException   when the collection does not exist
     * @throws ValidationFailedException when the resulting collection is invalid
     */
    public function update(string $collectionId, ?string $title = null, iterable $data = []): CollectionView
    {
        $collection = $this->find($collectionId);

        if ($title !== null) {
            $collection->setTitle($title);
        }

        $this->dataApplier->apply($collection, $data, $collection->getOwner());

        $this->save($collection);

        return $this->mapper->collection($collection);
    }

    /**
     * Deletes the collection with its children and items, as the UI does.
     *
     * @throws EntityNotFoundException when the collection does not exist
     */
    public function delete(string $collectionId): void
    {
        $this->em->remove($this->find($collectionId));
        $this->em->flush();
    }

    private function find(string $collectionId): Collection
    {
        return $this->em->find(Collection::class, $collectionId) ?? throw EntityNotFoundException::collection($collectionId);
    }

    private function save(Collection $collection): void
    {
        $violations = $this->validator->validate($collection);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($collection, $violations);
        }

        $this->em->persist($collection);
        $this->em->flush();
    }
}
