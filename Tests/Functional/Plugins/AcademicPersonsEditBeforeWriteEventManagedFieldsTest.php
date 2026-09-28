<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestEditorWriteListener\EventListener\EditorWriteListener;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The write event is no way around the fields and rows a synchronisation owns.
 *
 * The fixture extension `test_managed_fields_editor` manages the profile website,
 * the contract position, the e-mail address and both fields of a phone number, and
 * the profile, contract 1, e-mail 1 and phone number 1 carry an import identifier.
 * A value a listener adds for such a field is dropped like a submitted one, and a
 * delete or edit the lock refuses never reaches a listener.
 */
final class AcademicPersonsEditBeforeWriteEventManagedFieldsTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * @var array<string, string>
     */
    private array $endpointUrls = [];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-managed-fields-editor');
        $this->addTestExtensionsToLoad('tests/test-editor-write-listener');
        parent::setUp();
        EditorWriteListener::reset();
    }

    protected function tearDown(): void
    {
        EditorWriteListener::reset();
        parent::tearDown();
    }

    private function setUpManagedRecords(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditManagedFields/records.csv');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                [
                    'contracts' => 2,
                    'import_identifier' => 'fe_users:1',
                    'website' => 'https://synced.example.org',
                ],
                ['uid' => self::PROFILE_ID],
            );
        $content = $this->renderProfileEditingPage();
        foreach ([
            'update' => 'data-update-url',
            'updateDocument' => 'data-update-document-url',
            'deleteDocument' => 'data-delete-document-url',
            'updateContractContact' => 'data-update-contract-contact-url',
            'deleteContractContact' => 'data-delete-contract-contact-url',
        ] as $action => $attribute) {
            $this->assertSame(
                1,
                preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
                sprintf('The rendered component has no "%s" URL.', $attribute),
            );
            $url = html_entity_decode($match[1]);
            $this->endpointUrls[$action] = str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
        }
        EditorWriteListener::reset();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(string $action, array $data): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => $data], JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($this->endpointUrls[$action]))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }

    private function fetchValue(string $table, string $column, int $uid): mixed
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select($column)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function replaceFieldsWith(array $fields): void
    {
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event) use ($fields): void {
            $event->setFields($fields);
        };
    }

    #[Test]
    public function aManagedProfileFieldAListenerAddsIsDropped(): void
    {
        $this->setUpManagedRecords();
        $this->replaceFieldsWith(['website' => 'https://taken.example.org', 'websiteTitle' => 'Replaced']);

        $response = $this->post('update', ['websiteTitle' => 'Submitted']);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['websiteTitle'], array_keys(EditorWriteListener::$calls[0]['fields']));
        // The answer names what the editor accepted, so it shows the drop on its own,
        // before the factory would drop the managed value a second time.
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame(['websiteTitle' => 'Replaced'], $body['data'] ?? null);
        $this->assertSame(
            'https://synced.example.org',
            $this->fetchValue('tx_academicpersons_domain_model_profile', 'website', self::PROFILE_ID),
        );
        $this->assertSame(
            'Replaced',
            $this->fetchValue('tx_academicpersons_domain_model_profile', 'website_title', self::PROFILE_ID),
        );
    }

    #[Test]
    public function aManagedContractFieldAListenerAddsIsDropped(): void
    {
        $this->setUpManagedRecords();
        $this->replaceFieldsWith(['position' => 'Taken over', 'room' => 'B 2']);

        $response = $this->post('updateDocument', ['section' => 'contracts', 'record' => 1, 'fields' => ['room' => 'A 1']]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('Rectorate', $this->fetchValue('tx_academicpersons_domain_model_contract', 'position', 1));
        $this->assertSame('B 2', $this->fetchValue('tx_academicpersons_domain_model_contract', 'room', 1));
    }

    #[Test]
    public function aManagedContactFieldAListenerAddsIsDropped(): void
    {
        $this->setUpManagedRecords();
        $this->replaceFieldsWith(['email' => 'taken@example.com', 'type' => 'business']);

        $response = $this->post(
            'updateContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'fields' => ['type' => 'business']],
        );

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('rectorate@example.com', $this->fetchValue('tx_academicpersons_domain_model_email', 'email', 1));
    }

    /**
     * @return \Generator<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function lockedRowProvider(): \Generator
    {
        yield 'delete of a synchronised contract' => [
            'deleteDocument',
            ['section' => 'contracts', 'record' => 1],
            'document_action_not_allowed',
        ];
        yield 'delete of a synchronised e-mail' => [
            'deleteContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1],
            'contract_contact_action_not_allowed',
        ];
        yield 'edit of a phone number whose fields are all managed' => [
            'updateContractContact',
            ['contract' => 1, 'section' => 'phoneNumbers', 'record' => 1, 'fields' => ['phoneNumber' => '+49 1']],
            'contract_contact_action_not_allowed',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('lockedRowProvider')]
    public function aWriteTheLockRefusesNeverReachesAListener(string $action, array $data, string $expectedError): void
    {
        $this->setUpManagedRecords();

        $response = $this->post($action, $data);

        $this->assertSame(403, $response->getStatusCode(), (string)$response->getBody());
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame($expectedError, $body['error'] ?? null);
        $this->assertSame([], EditorWriteListener::$calls);
    }
}
