<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidItemProvidedException;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class ListSortingServiceTest extends AbstractAcademicPersonsEditTestCase
{
    private static function createTestModel(?int $uid = null, ?int $sorting = null): AbstractEntity
    {
        $model = new class () extends AbstractEntity {
            protected int $sorting = 0;

            public function getSorting(): int
            {
                return $this->sorting;
            }

            public function setSorting(int $sorting): void
            {
                $this->sorting = $sorting;
            }
        };
        if ($uid !== null) {
            $model->_setProperty('uid', $uid);
        }
        if ($sorting !== null) {
            $model->setSorting($sorting);
        }
        return $model;
    }

    /**
     * @param AbstractEntity[] $items
     * @return array<int, array{
     *     uid: int|null,
     *     sorting: int,
     * }>
     */
    private static function createComparableItemsArray(array $items, string $getter = 'getSorting'): array
    {
        /**
         * @var array<int, array{
         *      uid: int|null,
         *      sorting: int,
         *  }> $return
         */
        $return = array_map(
            static fn(AbstractEntity $entity): array => ['uid' => $entity->getUid(), 'sorting' => method_exists($entity, $getter) ? $entity->{$getter}() : 0],
            $items
        );
        return $return;
    }

    #[Test]
    public function createListSortingProcessThrowsInvalidItemProvidedExceptionForNonObjectItem(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $invoker = (new \ReflectionMethod($listSortingService, 'createListSortingProcess'));

        $this->expectException(InvalidItemProvidedException::class);
        $this->expectExceptionCode(1774370991);

        $invoker->invoke($listSortingService, [0 => null], 0);
    }

    #[Test]
    public function createListSortingProcessThrowsInvalidItemProvidedExceptionForNonExtbaseEntityItem(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $invoker = (new \ReflectionMethod($listSortingService, 'createListSortingProcess'));

        $this->expectException(InvalidItemProvidedException::class);
        $this->expectExceptionCode(1774371062);

        $invoker->invoke($listSortingService, [0 => new \stdClass()], 0);
    }

    #[Test]
    public function createListSortingProcessReturnsListSortingProcessForEmptyItemsArray(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $invoker = (new \ReflectionMethod($listSortingService, 'createListSortingProcess'));
        $process = $invoker->invoke($listSortingService, [], 0);
        $this->assertInstanceOf(ListSortingProcess::class, $process);
        $this->assertSame(0, $process->targetItemUid);
        $this->assertNull($process->targetItemIndex);
        $this->assertSame(ListSortingMode::NONE, $process->mode);
        $this->assertSame([], $process->items);
        $this->assertFalse($process->changed);
        $this->assertSame('getSorting', $process->itemSortingGetter);
        $this->assertSame('setSorting', $process->itemSortingSetter);
    }

    #[Test]
    public function createListSortingProcessReturnsListSortingProcessForExtbaseEntityAndMatchedTargetItemIndex(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $model1 = self::createTestModel(2);
        $model2 = self::createTestModel(1);
        $items = [0 => $model1, 1 => $model2];
        $invoker = (new \ReflectionMethod($listSortingService, 'createListSortingProcess'));
        $process = $invoker->invoke($listSortingService, $items, 1);
        $this->assertInstanceOf(ListSortingProcess::class, $process);
        $this->assertSame(1, $process->targetItemUid);
        $this->assertSame(1, $process->targetItemIndex);
        $this->assertSame(ListSortingMode::NONE, $process->mode);
        $this->assertSame($items, $process->items);
        $this->assertFalse($process->changed);
        $this->assertSame('getSorting', $process->itemSortingGetter);
        $this->assertSame('setSorting', $process->itemSortingSetter);
    }

    #[Test]
    public function createListSortingProcessReturnsListSortingProcessForExtbaseEntityAndNullAsTargetItemIndexForUnmatchedTargetItemId(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $model1 = self::createTestModel(2);
        $model2 = self::createTestModel(1);
        $items = [0 => $model1, 1 => $model2];
        $invoker = (new \ReflectionMethod($listSortingService, 'createListSortingProcess'));
        $process = $invoker->invoke($listSortingService, $items, 3);
        $this->assertInstanceOf(ListSortingProcess::class, $process);
        $this->assertSame(3, $process->targetItemUid);
        $this->assertNull($process->targetItemIndex);
        $this->assertSame(ListSortingMode::NONE, $process->mode);
        $this->assertSame($items, $process->items);
        $this->assertFalse($process->changed);
        $this->assertSame('getSorting', $process->itemSortingGetter);
        $this->assertSame('setSorting', $process->itemSortingSetter);
    }

    #[Test]
    public function applyArrayItemsIndexAsSortingValueDoesNotCallPersistenceManagerMethodsForEmptyItems(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->never())->method('persistAll');
        $persistenceManagerMock->expects($this->never())->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $process = new ListSortingProcess(
            items: [],
        );
        $invoker = (new \ReflectionMethod($listSortingService, 'applyArrayItemsIndexAsSortingValue'));
        $returnedProcess = $invoker->invoke($listSortingService, $process);
        $this->assertInstanceOf(ListSortingProcess::class, $returnedProcess);
        $this->assertFalse($returnedProcess->changed);
        $this->assertSame([], $returnedProcess->items);
    }

    #[Test]
    public function applyArrayItemsIndexAsSortingValueDoesNotCallPersistenceManagerMethodsForEmptyItems1(): void
    {
        $persistenceManagerMock = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManagerMock->expects($this->once())->method('persistAll');
        $persistenceManagerMock->expects($this->atLeast(2))->method('update');
        $listSortingService = new ListSortingService();
        $listSortingService->injectPersistenceManager($persistenceManagerMock);
        $model1 = self::createTestModel(2);
        $model2 = self::createTestModel(1);
        $items = [0 => $model1, 1 => $model2];
        $process = new ListSortingProcess(
            items: $items,
        );
        $invoker = (new \ReflectionMethod($listSortingService, 'applyArrayItemsIndexAsSortingValue'));
        $returnedProcess = $invoker->invoke($listSortingService, $process);
        $this->assertInstanceOf(ListSortingProcess::class, $returnedProcess);
        $this->assertTrue($returnedProcess->changed);
        $this->assertArrayHasKey(0, $returnedProcess->items);
        $item1 = $returnedProcess->items[0];
        $this->assertInstanceOf(AbstractEntity::class, $item1);
        $this->assertTrue(method_exists($item1, 'getSorting'));
        $this->assertSame(10, $item1->getSorting());
        $item2 = $returnedProcess->items[1];
        $this->assertInstanceOf(AbstractEntity::class, $item2);
        $this->assertTrue(method_exists($item2, 'getSorting'));
        $this->assertSame(20, $item2->getSorting());
    }

    public static function sortDataSets(): \Generator
    {
        $model1 = self::createTestModel(1, 10);
        $model2 = self::createTestModel(2, 20);
        $model3 = self::createTestModel(3, 30);
        $model4 = self::createTestModel(4, 40);
        $model5 = self::createTestModel(5, 50);
        $items = [
            0 => $model1,
            1 => $model2,
            2 => $model3,
            3 => $model4,
            4 => $model5,
        ];
        yield '#1 TOP mode sorts middle item to the top' => [
            'items' => $items,
            'mode' => ListSortingMode::TOP,
            'targetItemUid' => $model3->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 3, 'sorting' => 10],
                1 => ['uid' => 1, 'sorting' => 20],
                2 => ['uid' => 2, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
        yield '#2 TOP mode sorts last item to the top' => [
            'items' => $items,
            'mode' => ListSortingMode::TOP,
            'targetItemUid' => $model5->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 5, 'sorting' => 10],
                1 => ['uid' => 1, 'sorting' => 20],
                2 => ['uid' => 2, 'sorting' => 30],
                3 => ['uid' => 3, 'sorting' => 40],
                4 => ['uid' => 4, 'sorting' => 50],
            ],
        ];
        yield '#3 BOTTOM mode sorts middle item to the bottom' => [
            'items' => $items,
            'mode' => ListSortingMode::BOTTOM,
            'targetItemUid' => $model3->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 1, 'sorting' => 10],
                1 => ['uid' => 2, 'sorting' => 20],
                2 => ['uid' => 4, 'sorting' => 30],
                3 => ['uid' => 5, 'sorting' => 40],
                4 => ['uid' => 3, 'sorting' => 50],
            ],
        ];
        yield '#4 BOTTOM mode sorts first item to the bottom' => [
            'items' => $items,
            'mode' => ListSortingMode::BOTTOM,
            'targetItemUid' => $model1->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 2, 'sorting' => 10],
                1 => ['uid' => 3, 'sorting' => 20],
                2 => ['uid' => 4, 'sorting' => 30],
                3 => ['uid' => 5, 'sorting' => 40],
                4 => ['uid' => 1, 'sorting' => 50],
            ],
        ];
        // @todo broken - reactive when fixing code
        yield '#5 UP mode skips doing something for first item' => [
            'items' => $items,
            'mode' => ListSortingMode::UP,
            'targetItemUid' => $model1->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 1, 'sorting' => 10],
                1 => ['uid' => 2, 'sorting' => 20],
                2 => ['uid' => 3, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
        yield '#6 UP mode flips second item with first one for second item as target' => [
            'items' => $items,
            'mode' => ListSortingMode::UP,
            'targetItemUid' => $model2->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 2, 'sorting' => 10],
                1 => ['uid' => 1, 'sorting' => 20],
                2 => ['uid' => 3, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
        yield '#7 UP mode flips third with second item with for third item as target' => [
            'items' => $items,
            'mode' => ListSortingMode::UP,
            'targetItemUid' => $model3->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 1, 'sorting' => 10],
                1 => ['uid' => 3, 'sorting' => 20],
                2 => ['uid' => 2, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
        yield '#8 DOWN mode does nothing when last item is target item' => [
            'items' => $items,
            'mode' => ListSortingMode::DOWN,
            'targetItemUid' => $model5->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 1, 'sorting' => 10],
                1 => ['uid' => 2, 'sorting' => 20],
                2 => ['uid' => 3, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
        yield '#8 DOWN mode flips second last item with last item for second last item as target' => [
            'items' => $items,
            'mode' => ListSortingMode::DOWN,
            'targetItemUid' => $model4->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 1, 'sorting' => 10],
                1 => ['uid' => 2, 'sorting' => 20],
                2 => ['uid' => 3, 'sorting' => 30],
                3 => ['uid' => 5, 'sorting' => 40],
                4 => ['uid' => 4, 'sorting' => 50],
            ],
        ];
        yield '#8 DOWN mode flips first with second item with first item as target' => [
            'items' => $items,
            'mode' => ListSortingMode::DOWN,
            'targetItemUid' => $model1->getUid(),
            'expectedChangedState' => true,
            'expectedSortedItems' => [
                0 => ['uid' => 2, 'sorting' => 10],
                1 => ['uid' => 1, 'sorting' => 20],
                2 => ['uid' => 3, 'sorting' => 30],
                3 => ['uid' => 4, 'sorting' => 40],
                4 => ['uid' => 5, 'sorting' => 50],
            ],
        ];
    }

    /**
     * @param AbstractEntity[] $items
     * @param ListSortingMode $mode
     * @param int<1, max> $targetItemUid
     * @param bool $expectedChangedState
     * @param array<int, array{uid: int|null, sorting: int}> $expectedSortedItems
     */
    #[DataProvider('sortDataSets')]
    #[Test]
    public function sortReturnsExpectedSortedArray(
        array $items,
        ListSortingMode $mode,
        int $targetItemUid,
        bool $expectedChangedState,
        array $expectedSortedItems,
    ): void {
        $listSortingService = new ListSortingService();
        $result = $listSortingService->sort(
            items: $items,
            targetItemUid: $targetItemUid,
            mode: $mode,
        );
        $this->assertSame($expectedChangedState, $result->changed);
        $sortedItems = self::createComparableItemsArray($result->items);
        $this->assertSame($expectedSortedItems, $sortedItems);
    }
}
