<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersonsEdit\Service\DocumentRecords;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DocumentRecordsTest extends UnitTestCase
{
    private function createSubject(): DocumentRecords
    {
        return new DocumentRecords(
            $this->createStub(ContractRepository::class),
            $this->createStub(ProfileInformationRepository::class),
            $this->createStub(PersistenceManager::class),
        );
    }

    /**
     * @return list<Contract>
     */
    private function createRecords(int ...$uids): array
    {
        $records = [];
        foreach ($uids as $uid) {
            $record = new Contract();
            $record->_setProperty('uid', $uid);
            $records[] = $record;
        }
        return $records;
    }

    #[Test]
    public function anOrderNamingEveryRecordOnceIsAccepted(): void
    {
        $this->createSubject()->assertCompleteDocumentOrder($this->createRecords(1, 2, 3), [3, 1, 2]);

        $this->addToAssertionCount(1);
    }

    /**
     * @return \Generator<string, array{0: list<int>}>
     */
    public static function incompleteOrderProvider(): \Generator
    {
        yield 'a record missing' => [[3, 1]];
        yield 'a record named twice' => [[3, 1, 1, 2]];
        yield 'a record of another section on top' => [[3, 1, 2, 4]];
        yield 'a record of another section instead of one of its own' => [[3, 1, 4]];
    }

    /**
     * @param list<int> $order
     */
    #[Test]
    #[DataProvider('incompleteOrderProvider')]
    public function anOrderNotNamingEveryRecordOnceIsRefused(array $order): void
    {
        try {
            $this->createSubject()->assertCompleteDocumentOrder($this->createRecords(1, 2, 3), $order);
        } catch (EditorRequestException $exception) {
            $this->assertSame('invalid_payload', $exception->error);
            $this->assertSame(400, $exception->statusCode);
            $this->assertSame('The document order must contain every section record exactly once.', $exception->responseMessage);
            $this->assertSame([], $exception->fieldErrors);
            return;
        }
        $this->fail('The order was accepted.');
    }
}
