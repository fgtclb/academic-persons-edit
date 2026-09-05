<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The sort endpoints renumber the whole list, at a realistic size.
 *
 * `sortDocument` and `sortContractContact` answer the new order and write
 * `sorting` for every record of the list they were asked about, not only for
 * the one that moved: position `n` of the answered order is stored as
 * `(n + 1) * 10`. The existing coverage exercises that on two and three rows
 * that already carry `10, 20, 30`, where a defect in the renumbering is
 * indistinguishable from a swap. These tests use six rows - the maintainer's
 * own example - and start from the values TYPO3's DataHandler leaves behind
 * on a manually sortable table, `10, 20, 30, 256, 512, 1024`, which is what a
 * list looks like after a backend editor added rows to it.
 *
 * Every test asserts both halves of the contract - the answered `order` and
 * the rows - because the answer is built from the in-memory objects and says
 * nothing about what reached the database. The rows are read back ordered by
 * `sorting, uid`, so the key order of the asserted map is the stored order on
 * every DBMS.
 */
final class AcademicPersonsEditDocumentSortingTest extends AbstractFrontendProfilePluginTestCase
{
    private const CONTRACT_ID = 1;

    /**
     * The starting point of the maintainer's example: six rows, `sorting` 1..6.
     */
    private const UNIT_SORTINGS = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6];

    /**
     * What TYPO3's DataHandler produces for a table with `ctrl.sortby`: it
     * appends by doubling the last value once the list is longer than the
     * interval it started with. Three of these were made by the editor, three
     * in the backend.
     */
    private const DATA_HANDLER_SORTINGS = [1 => 10, 2 => 20, 3 => 30, 4 => 256, 5 => 512, 6 => 1024];

    /**
     * The maintainer's example: rec-2 dragged between rec-4 and rec-5.
     */
    private const ORDER_TWO_BETWEEN_FOUR_AND_FIVE = [1, 3, 4, 2, 5, 6];

    /**
     * What the six rows carry after any complete reorder to that order.
     */
    private const RENUMBERED_TWO_BETWEEN_FOUR_AND_FIVE = [1 => 10, 3 => 20, 4 => 30, 2 => 40, 5 => 50, 6 => 60];

    /**
     * Six rows with `sorting` 1..6, rec-2 dragged between rec-4 and rec-5, is
     * rec-1, rec-3, rec-4, rec-2, rec-5, rec-6 with `10..60`.
     *
     * Rows 1, 5 and 6 did not move, yet every one of the six is rewritten,
     * because the endpoint derives the value from the position in the
     * submitted order and never from the value a row had.
     */
    #[Test]
    public function aDragRenumbersEveryRecordOfAProfileInformationSection(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedCooperationRecords(self::UNIT_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');
        $this->assertSame(self::UNIT_SORTINGS, $this->getPersistedCooperationSorting());

        $body = $this->sortDocument($sortUrl, [
            'section' => 'cooperation',
            'order' => self::ORDER_TWO_BETWEEN_FOUR_AND_FIVE,
        ]);

        $this->assertTrue($body['changed']);
        $this->assertSame(self::ORDER_TWO_BETWEEN_FOUR_AND_FIVE, $body['order']);
        $this->assertSame(self::RENUMBERED_TWO_BETWEEN_FOUR_AND_FIVE, $this->getPersistedCooperationSorting());
    }

    /**
     * @return \Generator<string, array{0: list<int>, 1: array<int, int>}>
     */
    public static function dragToEitherEndProvider(): \Generator
    {
        yield 'rec-2 to the first position' => [
            [2, 1, 3, 4, 5, 6],
            [2 => 10, 1 => 20, 3 => 30, 4 => 40, 5 => 50, 6 => 60],
        ];
        yield 'rec-2 to the last position' => [
            [1, 3, 4, 5, 6, 2],
            [1 => 10, 3 => 20, 4 => 30, 5 => 40, 6 => 50, 2 => 60],
        ];
    }

    /**
     * A drag to either end of a list with DataHandler values leaves `10..60`.
     *
     * The three rows a backend editor added carry `256, 512, 1024`. Moving a
     * row to the first or the last position does not touch those three, so a
     * renumbering that only wrote the moved row - or only the rows between the
     * old and the new position - would leave them as they are. The stored
     * order would still be right; the next `getNextSortingValue()` would then
     * append at `1034` and the gaps would grow with every row.
     *
     * @param list<int> $order
     * @param array<int, int> $expectedSorting
     */
    #[Test]
    #[DataProvider('dragToEitherEndProvider')]
    public function aDragToEitherEndRenumbersDataHandlerSortings(array $order, array $expectedSorting): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedCooperationRecords(self::DATA_HANDLER_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');
        $this->assertSame(self::DATA_HANDLER_SORTINGS, $this->getPersistedCooperationSorting());

        $body = $this->sortDocument($sortUrl, ['section' => 'cooperation', 'order' => $order]);

        $this->assertTrue($body['changed']);
        $this->assertSame($order, $body['order']);
        $this->assertSame($expectedSorting, $this->getPersistedCooperationSorting());
    }

    /**
     * The `contracts` section takes the same drag, from the same starting point.
     *
     * Contracts are the one document section whose records are not read
     * through a repository query but through the inline relation of the
     * Profile, which Extbase orders by `sorting` alone. The renumbering is the
     * same code, but nothing exercised it with a full `order` for this section
     * before, only with a single step on three rows.
     */
    #[Test]
    public function aDragRenumbersEveryContractOfTheProfile(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedContracts(self::DATA_HANDLER_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');
        $this->assertSame(self::DATA_HANDLER_SORTINGS, $this->getPersistedContractSorting());

        $body = $this->sortDocument($sortUrl, [
            'section' => 'contracts',
            'order' => self::ORDER_TWO_BETWEEN_FOUR_AND_FIVE,
        ]);

        $this->assertTrue($body['changed']);
        $this->assertSame(self::ORDER_TWO_BETWEEN_FOUR_AND_FIVE, $body['order']);
        $this->assertSame(self::RENUMBERED_TWO_BETWEEN_FOUR_AND_FIVE, $this->getPersistedContractSorting());
    }

    /**
     * One arrow press renumbers the whole contracts list as well.
     *
     * The step-wise branch delegates to `ListSortingService`, a different
     * implementation from the drag branch, and it swaps two neighbours only.
     * It then rewrites every row from its array index the same way, so the
     * three DataHandler values disappear on the first step a visitor takes -
     * rows 1, 2, 3 and 6 did not move, and are rewritten regardless.
     */
    #[Test]
    public function aStepMovesOneContractAndRenumbersEveryOther(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedContracts(self::DATA_HANDLER_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');

        $body = $this->sortDocument($sortUrl, ['section' => 'contracts', 'record' => 5, 'direction' => 'up']);

        $this->assertTrue($body['changed']);
        $this->assertSame([1, 2, 3, 5, 4, 6], $body['order']);
        $this->assertSame(
            [1 => 10, 2 => 20, 3 => 30, 5 => 40, 4 => 50, 6 => 60],
            $this->getPersistedContractSorting(),
        );
    }

    /**
     * The contacts of a Contract only move one step at a time, and that step
     * renumbers all of them.
     *
     * There is no drag handle for addresses, e-mail addresses and phone
     * numbers, and `sortContractContact` accepts no `order` - `direction` is
     * mandatory there. The one path that exists is the same `ListSortingService`
     * call the contracts step uses, on the rows of one Contract, read through
     * the repository ordered by `sorting, uid`. E-mail addresses stand in for
     * the three contact kinds: the endpoint switches only the repository on the
     * section, and the rest of the request is identical.
     */
    #[Test]
    public function aStepRenumbersEveryContactOfAContract(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedContracts([self::CONTRACT_ID => 10]);
        $this->seedEmailAddresses(self::DATA_HANDLER_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-contract-contact-url');
        $this->assertSame(self::DATA_HANDLER_SORTINGS, $this->getPersistedEmailSorting());

        $body = $this->sortDocument($sortUrl, [
            'contract' => self::CONTRACT_ID,
            'section' => 'emailAddresses',
            'record' => 2,
            'direction' => 'down',
        ]);

        $this->assertTrue($body['changed']);
        $this->assertSame(self::CONTRACT_ID, $body['contract']);
        $this->assertSame([1, 3, 2, 4, 5, 6], $body['order']);
        $this->assertSame(
            [1 => 10, 3 => 20, 2 => 30, 4 => 40, 5 => 50, 6 => 60],
            $this->getPersistedEmailSorting(),
        );
    }

    /**
     * @return \Generator<string, array{0: list<int>}>
     */
    public static function malformedOrderProvider(): \Generator
    {
        yield 'a record is missing' => [[1, 3, 4, 2, 5]];
        yield 'a record of another profile is listed' => [[1, 3, 4, 2, 5, 90]];
        yield 'a record is listed twice' => [[1, 3, 4, 2, 5, 5]];
    }

    /**
     * An order that is not a permutation of the section is refused as a whole.
     *
     * The drag handle sends every uid of the list, so a payload that misses
     * one, names one of another profile's section or names one twice can only
     * come from a client the editor did not render. The endpoint refuses it
     * with 400 before it touches a row, and the message is the one the
     * controller writes for exactly this check - a 400 from the shape checks
     * of `getSubmittedDocumentOrder()` would carry a different one. Record 90
     * is the cooperation of `foreignProfile.csv`: the same type, the same
     * folder, another profile.
     *
     * @param list<int> $order
     */
    #[Test]
    #[DataProvider('malformedOrderProvider')]
    public function aMalformedDragOrderIsRefusedAndChangesNothing(array $order): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/foreignProfile.csv');
        $this->seedCooperationRecords(self::UNIT_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');

        $response = $this->postJson($sortUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'cooperation', 'order' => $order],
        ]);

        $this->assertSame(400, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            [
                'success' => false,
                'error' => 'invalid_payload',
                'message' => 'The document order must contain every section record exactly once.',
            ],
            $this->decodeBody($response),
        );
        $this->assertSame(self::UNIT_SORTINGS, $this->getPersistedCooperationSorting());
        $this->assertSame([90 => 10], $this->getPersistedCooperationSorting(2));
    }

    /**
     * The current order, sent back, still renumbers DataHandler values.
     *
     * A drop on the row's own position sends the unchanged order. The endpoint
     * does not compare orders; it compares every row's `sorting` with the
     * value its position demands, so `10, 20, 30, 256, 512, 1024` is rewritten
     * to `10..60` and answered with `changed: true`, although no row moved.
     * That is the behaviour, and it is a useful one - it is how a list a
     * backend editor extended gets its gaps closed - so this pins it rather
     * than the intuition that an unchanged order is a no-op. Only a list that
     * already carries `(position + 1) * 10` everywhere is answered with
     * `changed: false`, and then nothing is written.
     */
    #[Test]
    public function theCurrentOrderIsANoOpOnlyOnceEveryRowCarriesItsPositionValue(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedCooperationRecords(self::DATA_HANDLER_SORTINGS);
        $sortUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-sort-document-url');
        $currentOrder = [1, 2, 3, 4, 5, 6];

        $firstBody = $this->sortDocument($sortUrl, ['section' => 'cooperation', 'order' => $currentOrder]);

        $this->assertTrue($firstBody['changed']);
        $this->assertSame($currentOrder, $firstBody['order']);
        $renumbered = [1 => 10, 2 => 20, 3 => 30, 4 => 40, 5 => 50, 6 => 60];
        $this->assertSame($renumbered, $this->getPersistedCooperationSorting());

        $secondBody = $this->sortDocument($sortUrl, ['section' => 'cooperation', 'order' => $currentOrder]);

        $this->assertFalse($secondBody['changed']);
        $this->assertSame($currentOrder, $secondBody['order']);
        $this->assertSame($renumbered, $this->getPersistedCooperationSorting());
    }

    /**
     * Six cooperation rows of the profile, one per uid, with the given `sorting`.
     *
     * Inserted directly rather than through a CSV so the uids are the ones of
     * the maintainer's example, and so the starting values are visible next to
     * the expectation they are compared with. The Profile's `cooperation`
     * counter is what Extbase reads before it loads the relation.
     *
     * @param array<int, int> $sortingByUid
     */
    private function seedCooperationRecords(array $sortingByUid): void
    {
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information');
        foreach ($sortingByUid as $uid => $sorting) {
            $connection->insert('tx_academicpersons_domain_model_profile_information', [
                'uid' => $uid,
                'pid' => self::PROFILE_PAGE_ID,
                'profile' => self::PROFILE_ID,
                'type' => 'cooperation',
                'title' => sprintf('Cooperation %d', $uid),
                'sorting' => $sorting,
            ]);
        }
        $this->updateProfileCounter('cooperation', count($sortingByUid));
    }

    /**
     * @param array<int, int> $sortingByUid
     */
    private function seedContracts(array $sortingByUid): void
    {
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_contract');
        foreach ($sortingByUid as $uid => $sorting) {
            $connection->insert('tx_academicpersons_domain_model_contract', [
                'uid' => $uid,
                'pid' => self::PROFILE_PAGE_ID,
                'profile' => self::PROFILE_ID,
                'position' => sprintf('Contract %d', $uid),
                'publish' => 1,
                'sorting' => $sorting,
            ]);
        }
        $this->updateProfileCounter('contracts', count($sortingByUid));
    }

    /**
     * @param array<int, int> $sortingByUid
     */
    private function seedEmailAddresses(array $sortingByUid): void
    {
        $connection = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_email');
        foreach ($sortingByUid as $uid => $sorting) {
            $connection->insert('tx_academicpersons_domain_model_email', [
                'uid' => $uid,
                'pid' => self::PROFILE_PAGE_ID,
                'contract' => self::CONTRACT_ID,
                'email' => sprintf('contact-%d@example.com', $uid),
                'type' => 'business',
                'sorting' => $sorting,
            ]);
        }
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_contract')
            ->update(
                'tx_academicpersons_domain_model_contract',
                ['email_addresses' => count($sortingByUid)],
                ['uid' => self::CONTRACT_ID],
            );
    }

    private function updateProfileCounter(string $column, int $count): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update('tx_academicpersons_domain_model_profile', [$column => $count], ['uid' => self::PROFILE_ID]);
    }

    /**
     * @return array<int, int>
     */
    private function getPersistedCooperationSorting(int $profileUid = self::PROFILE_ID): array
    {
        return $this->getPersistedSorting(
            'tx_academicpersons_domain_model_profile_information',
            ['profile' => $profileUid, 'type' => 'cooperation'],
        );
    }

    /**
     * @return array<int, int>
     */
    private function getPersistedContractSorting(): array
    {
        return $this->getPersistedSorting('tx_academicpersons_domain_model_contract', ['profile' => self::PROFILE_ID]);
    }

    /**
     * @return array<int, int>
     */
    private function getPersistedEmailSorting(): array
    {
        return $this->getPersistedSorting('tx_academicpersons_domain_model_email', ['contract' => self::CONTRACT_ID]);
    }

    /**
     * The `sorting` of every live row of one list, keyed by uid, in stored order.
     *
     * Ordered by `sorting, uid` so that the key order is deterministic on every
     * DBMS and an assertion with `assertSame()` pins the stored order as well as
     * the values.
     *
     * @param array<string, int|string> $identifiers
     * @return array<int, int>
     */
    private function getPersistedSorting(string $table, array $identifiers): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable($table)
            ->select(['uid', 'sorting'], $table, $identifiers + ['deleted' => 0], [], ['sorting' => 'ASC', 'uid' => 'ASC'])
            ->fetchAllAssociative();
        $sorting = [];
        foreach ($rows as $row) {
            $sorting[(int)$row['uid']] = (int)$row['sorting'];
        }
        return $sorting;
    }

    /**
     * Posts a sort request that is expected to succeed and returns its body.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sortDocument(string $url, array $data): array
    {
        $response = $this->postJson($url, ['profile' => self::PROFILE_ID, 'data' => $data]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $body = $this->decodeBody($response);
        $this->assertTrue($body['success'] ?? null, (string)$response->getBody());
        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(ResponseInterface $response): array
    {
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        return $body;
    }

    private function extractDataUrl(string $content, string $attribute): string
    {
        $pattern = sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@'));
        $this->assertSame(
            1,
            preg_match($pattern, $content, $match),
            sprintf('The rendered component has no "%s" URL.', $attribute),
        );
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }
}
