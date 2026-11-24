<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Datum;
use App\Entity\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds a copy of an item: same collection, tags, visibility and data.
 * Uploaded files (main image, image/sign/file/video data) are physically copied
 * so the copy and the original never share a file on disk.
 * Loans and related items are not copied.
 */
readonly class ItemDuplicator
{
    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%/public')] private string $publicPath
    ) {
    }

    public function duplicate(Item $source): Item
    {
        $copy = new Item();
        $copy
            ->setName($this->translator->trans('label.item.copy_name', ['name' => $source->getName()]))
            ->setDescription($source->getDescription())
            ->setQuantity($source->getQuantity())
            ->setCollection($source->getCollection())
            ->setVisibility($source->getVisibility())
            ->setScrapedFromUrl($source->getScrapedFromUrl())
        ;

        foreach ($source->getTags() as $tag) {
            $copy->addTag($tag);
        }

        if (null !== $file = $this->copyUploadedFile($source->getImage())) {
            $copy->setFile($file);
        }

        foreach ($source->getData() as $sourceDatum) {
            $copy->addData($this->duplicateDatum($sourceDatum));
        }

        return $copy;
    }

    private function duplicateDatum(Datum $source): Datum
    {
        $datum = new Datum();
        $datum
            ->setType($source->getType())
            ->setLabel($source->getLabel())
            ->setValue($source->getValue())
            ->setPosition($source->getPosition())
            ->setCurrency($source->getCurrency())
            ->setChoiceList($source->getChoiceList())
            ->setVisibility($source->getVisibility())
        ;

        if (null !== $file = $this->copyUploadedFile($source->getImage())) {
            $datum->setFileImage($file);
        }

        if (null !== $file = $this->copyUploadedFile($source->getFile(), $source->getOriginalFilename())) {
            $datum->setFileFile($file);
        }

        if (null !== $file = $this->copyUploadedFile($source->getVideo())) {
            $datum->setFileVideo($file);
        }

        return $datum;
    }

    /**
     * Copies a stored file to a temporary location and wraps it in an UploadedFile,
     * so the regular upload listener moves it, names it and generates thumbnails.
     */
    private function copyUploadedFile(?string $relativePath, ?string $originalName = null): ?UploadedFile
    {
        if (null === $relativePath) {
            return null;
        }

        $absolutePath = str_starts_with($relativePath, '/tmp') ? $relativePath : $this->publicPath . '/' . $relativePath;
        if (!is_file($absolutePath)) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'koi_dup_');
        if (false === $tmpPath || !copy($absolutePath, $tmpPath)) {
            return null;
        }

        return new UploadedFile($tmpPath, $originalName ?? basename($absolutePath), null, null, true);
    }
}
