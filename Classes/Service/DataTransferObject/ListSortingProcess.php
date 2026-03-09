<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service\DataTransferObject;

use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * DataTransferObject used as result for {@see ListSortingService::sortItems()} and
 * internal handling in that service, meant to be used in extension controllers only.
 *
 * @internal for use in `EXT:academic_persons_edit` only and not part of public API.
 */
final class ListSortingProcess
{
    /**
     * @param AbstractEntity[] $items
     * @param non-empty-string $itemSortingGetter
     * @param non-empty-string $itemSortingSetter
     */
    public function __construct(
        public ListSortingMode $mode = ListSortingMode::NONE,
        public array $items = [],
        public int $targetItemUid = 0,
        public ?int $targetItemIndex = null,
        public bool $changed = false,
        public string $itemSortingGetter = 'getSorting',
        public string $itemSortingSetter = 'setSorting',
    ) {}
}
