<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidProjectProfileFieldException;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A project column the editor must not use fails the editor with a message naming
 * it. The fixture extension `test_project_profile_column_removed` removes the
 * column `tx_test_prefix` of the project field `namePrefix` in a TCA listener
 * ordered after the persons settings. The settings listener saw the column, so no
 * notice was raised, and the check of the editor is the only one left.
 */
final class AcademicPersonsEditProjectProfileFieldMistakeTest extends AbstractFrontendProfilePluginTestCase
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-project-profile-fields');
        $this->addTestExtensionsToLoad('tests/project-column-removed');
        parent::setUp();
    }

    #[Test]
    public function theEditorFailsOnAProjectColumnTheTcaDoesNotHave(): void
    {
        $this->setUpProfileEditingTestCase();

        $this->expectException(InvalidProjectProfileFieldException::class);
        $this->expectExceptionCode(1790600101);
        $this->expectExceptionMessage(
            'The project field "profile.namePrefix" of the persons settings cannot use the column "tx_test_prefix"'
            . ' of the table "tx_academicpersons_domain_model_profile": the TCA of the table has no such column.',
        );
        $this->renderProfileEditingPage();
    }

    /**
     * The write is refused before anything of it is stored, the regular field of the
     * same request included.
     */
    #[Test]
    public function aWriteToTheColumnStoresNothing(): void
    {
        $this->setUpProfileEditingTestCase();

        $response = $this->postJson($this->updateUrl(), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => 'van', 'title' => 'Dr.'],
        ]);

        $this->assertSame(500, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_project_field', $body['error']);
        $this->assertStringContainsString('the column "tx_test_prefix"', $body['message']);
        $this->assertStringEndsWith('the TCA of the table has no such column.', $body['message']);
        $this->assertSame(['tx_test_prefix' => '', 'title' => ''], $this->profileRow(['tx_test_prefix', 'title']));
    }

    /**
     * The column is checked before the value is validated, so an empty value of the
     * required field is answered as the mistake of the installation it is.
     */
    #[Test]
    public function theColumnIsCheckedBeforeTheValue(): void
    {
        $this->setUpProfileEditingTestCase();

        $response = $this->postJson($this->updateUrl(), [
            'profile' => self::PROFILE_ID,
            'data' => ['namePrefix' => ''],
        ]);

        $this->assertSame(500, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('invalid_project_field', json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR)['error']);
    }

    /**
     * A request that names no project field keeps working: only the editor page,
     * which renders every project field, fails.
     */
    #[Test]
    public function aWriteWithoutTheProjectFieldIsStored(): void
    {
        $this->setUpProfileEditingTestCase();

        $response = $this->postJson($this->updateUrl(), [
            'profile' => self::PROFILE_ID,
            'data' => ['title' => 'Dr.'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['title' => 'Dr.'], $this->profileRow(['title']));
    }

    /**
     * The editor page fails and cannot hand out the URL of the endpoint, so it is
     * built as the editor builds it: on the page of the plugin, with the page type
     * of the endpoints and a cHash over the plugin arguments.
     */
    private function updateUrl(): string
    {
        $arguments = ['tx_academicpersonsedit_profileediting' => ['action' => 'update', 'controller' => 'Profile']];
        $cacheHash = $this->get(CacheHashCalculator::class)
            ->generateForParameters(HttpUtility::buildQueryString(['id' => 2] + $arguments));
        return 'https://www.acme.com/home?' . HttpUtility::buildQueryString(
            ['type' => 1733735] + $arguments + ['cHash' => $cacheHash],
        );
    }

    /**
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function profileRow(array $columns): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$columns)
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(self::PROFILE_ID, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        $this->assertIsArray($row);
        return $row;
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
