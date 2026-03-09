<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidItemProvidedException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\Service\Attribute\Required;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Internal helper service for controllers managing sortable items list.
 *
 * @note Service must be kept stateless.
 * @internal for use in `EXT:academic_persons_edit` only and not public API. Can change at any point without
 *           being considered as breaking and not communicated in that way. Extending it is done on own risk
 *           and **must** be properly covered with integration/implementation tests in projects.
 */
#[Autoconfigure(public: true)]
class ListSortingService
{
    private ?PersistenceManagerInterface $persistenceManager = null;

    #[Required]
    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * Sort items respecting target item index position for supported sorting modes
     * defined by {@see ListSortingMode}. Used within controller implementations of
     * `EXT:academic_persons_edit` only.
     *
     * @param AbstractEntity[] $items
     * @param int<1,max> $targetItemUid
     * @param non-empty-string $itemSortingGetter
     * @param non-empty-string $itemSortingSetter
     * @return ListSortingProcess
     */
    public function sort(
        array $items,
        int $targetItemUid,
        ListSortingMode $mode = ListSortingMode::NONE,
        string $itemSortingGetter = 'getSorting',
        string $itemSortingSetter = 'setSorting',
    ): ListSortingProcess {
        return $this->sortItems($this->createListSortingProcess(
            $items,
            $targetItemUid,
            $mode,
            $itemSortingGetter,
            $itemSortingSetter,
        ));
    }

    /**
     * Sort items respecting target item index position for supported sorting modes
     * defined by {@see ListSortingMode}. Used within controller implementations of
     * `EXT:academic_persons_edit` only.
     */
    private function sortItems(ListSortingProcess $process): ListSortingProcess
    {
        $itemsCount = count($process->items);
        if ($process->targetItemIndex === null
            || $itemsCount === 0
            || $process->mode === ListSortingMode::NONE
        ) {
            return $this->applyArrayItemsIndexAsSortingValue($process);
        }
        $targetItemIndex = $process->targetItemIndex;
        $items = $process->items;
        $targetItem = $items[$targetItemIndex];
        // Sort direction is top and target not the first item,
        // remove item from array then prepend it to the array.
        if ($process->mode === ListSortingMode::TOP && $targetItemIndex > 0) {
            array_splice($items, $targetItemIndex, 1);
            array_unshift($items, $targetItem);
            $process->items = $items;
            $process->changed = true;
            return $this->applyArrayItemsIndexAsSortingValue($process);
        }
        // Sort direction is bottom and target not the last item,
        // remove item from array then append it to the array.
        if ($process->mode === ListSortingMode::BOTTOM && $targetItemIndex < ($itemsCount - 1)) {
            array_splice($items, $targetItemIndex, 1);
            array_push($items, $targetItem);
            $process->items = $items;
            $process->changed = true;
            return $this->applyArrayItemsIndexAsSortingValue($process);
        }
        // Sort direction is up and target not the first item,
        // swap the target with the previous item.
        if ($process->mode === ListSortingMode::UP && $targetItemIndex > 0) {
            $items[$targetItemIndex] = $items[$targetItemIndex - 1];
            $items[$targetItemIndex - 1] = $targetItem;
            $process->items = $items;
            $process->changed = true;
            return $this->applyArrayItemsIndexAsSortingValue($process);
        }
        // Sort direction is down and target not the last item,
        // swap the target with the next item.
        if ($process->mode === ListSortingMode::DOWN && $targetItemIndex < ($itemsCount - 1)) {
            $items[$targetItemIndex] = $items[$targetItemIndex + 1];
            $items[$targetItemIndex + 1] = $targetItem;
            $process->items = $items;
            $process->changed = true;
            return $this->applyArrayItemsIndexAsSortingValue($process);
        }
        // Return process DTO. Either invalid mode or no suitable handling defined.
        return $this->applyArrayItemsIndexAsSortingValue($process);
    }

    /**
     * Builds an array having the targetItemIndex and the normalized items array for the
     * further processing with other methods by this service used within sorting methods
     * in controllers.
     *
     * @param array<int|string, mixed> $items
     * @param int<1,max> $targetItemUid
     * @param non-empty-string $itemSortingGetter
     * @param non-empty-string $itemSortingSetter
     */
    private function createListSortingProcess(
        array $items,
        int $targetItemUid,
        ListSortingMode $mode = ListSortingMode::NONE,
        string $itemSortingGetter = 'getSorting',
        string $itemSortingSetter = 'setSorting',
    ): ListSortingProcess {
        $index = 0;
        $targetItemIndex = null;
        $sortingItems = [];
        foreach ($items as $itemIndex => $item) {
            if (!is_object($item)) {
                throw new InvalidItemProvidedException(
                    sprintf(
                        '$items must be an array of AbstractEntity objects, "%s" given for index "%s"',
                        gettype($item),
                        $itemIndex
                    ),
                    1774370991,
                );
            }
            if (! $item instanceof AbstractEntity) {
                throw new InvalidItemProvidedException(
                    sprintf(
                        '$items must be an array of AbstractEntity objects, "%s"  given for index "%s"',
                        gettype($item),
                        get_class($item) . ': ' . implode(',', class_implements($item) ?: []),
                    ),
                    1774371062,
                );
            }
            $sortingItems[] = $item;
            if ($item->getUid() === $targetItemUid) {
                $targetItemIndex = $index;
            }
            $index++;
        }
        return new ListSortingProcess(
            mode: $mode,
            items: $sortingItems,
            targetItemUid: $targetItemUid,
            targetItemIndex: $targetItemIndex,
            changed: false,
            itemSortingGetter: $itemSortingGetter,
            itemSortingSetter: $itemSortingSetter,
        );
    }

    private function applyArrayItemsIndexAsSortingValue(ListSortingProcess $process): ListSortingProcess
    {
        if ($process->items === []) {
            // No items provided, skip processing.
            return $process;
        }
        foreach ($process->items as $index => $item) {
            // Make PHPStan happy
            /** @phpstan-ignore booleanOr.alwaysFalse */
            if (!is_object($item)
                || ! $item instanceof AbstractEntity
                || !method_exists($item, $process->itemSortingGetter)
                || !method_exists($item, $process->itemSortingSetter)
            ) {
                // Invalid item - skip for now.
                // @todo Consider throwing and exception here.
                continue;
            }
            // Use strictly increasing sorting values (standard TYPO3 behavior)
            $expectedSorting = ($index + 1) * 10;
            if ($expectedSorting !== $item->{$process->itemSortingGetter}()) {
                $item->{$process->itemSortingSetter}($expectedSorting);
                $process->changed = true;
                // We use PersistenceManager here intentionally to avoid dealing with concrete
                // Extbase repositories. Needs to be monitored and in case concrete handling
                // is really required this needs to be adopted/extended. Keep it simple for now.
                $this->persistenceManager?->update($item);
            }
        }
        if ($process->changed === true) {
            $this->persistenceManager?->persistAll();
        }
        return $process;
    }
}
