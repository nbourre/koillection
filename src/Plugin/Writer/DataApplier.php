<?php

declare(strict_types=1);

namespace App\Plugin\Writer;

use App\Entity\Collection;
use App\Entity\Datum;
use App\Entity\Item;
use App\Entity\User;
use App\Plugin\Dto\DatumInput;

/**
 * Creates or updates the data of an item or a collection from plugin input, matching data by label.
 *
 * @internal
 */
final class DataApplier
{
    /**
     * @param iterable<DatumInput> $inputs
     */
    public function apply(Item|Collection $parent, iterable $inputs, ?User $owner): void
    {
        $position = 0;
        foreach ($parent->getData() as $existing) {
            $position = max($position, (int) $existing->getPosition());
        }

        foreach ($inputs as $input) {
            $datum = $this->findByLabel($parent, $input->label);
            if (!$datum instanceof Datum) {
                $datum = new Datum()
                    ->setLabel($input->label)
                    ->setPosition(++$position)
                    ->setOwner($owner)
                ;
                $parent->addData($datum);
            }

            $datum
                ->setType($input->type)
                ->setValue($input->value)
                ->setCurrency($input->currency)
            ;
        }
    }

    private function findByLabel(Item|Collection $parent, string $label): ?Datum
    {
        foreach ($parent->getData() as $datum) {
            if ($datum->getLabel() === $label) {
                return $datum;
            }
        }

        return null;
    }
}
