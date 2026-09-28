<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestEditorWriteListener\EventListener\EditorWriteListener;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The event every write of the profile editing is offered to before it is stored,
 * driven through the real plugin.
 *
 * The fixture extension `test_editor_write_listener` records each event and hands it
 * to a closure the test sets: one that refuses, or one that replaces the fields. The
 * image upload is covered by {@see AcademicPersonsEditBeforeWriteEventImageUploadTest},
 * which TYPO3 v13 cannot run, and the allow-list of the actions by
 * {@see AcademicPersonsEditContractContactActionsTest}.
 */
final class AcademicPersonsEditBeforeWriteEventTest extends AbstractFrontendProfilePluginTestCase
{
    private const FOREIGN_PROFILE_ID = 2;

    private const REASON = 'Ask the <b>office</b> to change this.';

    /**
     * The tables a write of the editor changes, which a refused write must leave as
     * they were.
     */
    private const WRITTEN_TABLES = [
        'tx_academicpersons_domain_model_profile',
        'tx_academicpersons_domain_model_profile_information',
        'tx_academicpersons_domain_model_contract',
        'tx_academicpersons_domain_model_address',
        'tx_academicpersons_domain_model_email',
        'tx_academicpersons_domain_model_phone_number',
        'sys_file_reference',
    ];

    /**
     * @var array<string, string>
     */
    private array $endpointUrls = [];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-editor-write-listener');
        parent::setUp();
        EditorWriteListener::reset();
    }

    protected function tearDown(): void
    {
        EditorWriteListener::reset();
        parent::tearDown();
    }

    private function setUpWriteEventTestCase(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv',
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2, 'cooperation' => 2, 'vita' => 2],
                ['uid' => self::PROFILE_ID],
            );
        // A second e-mail address of contract 1, so that moving the first one
        // changes the order and a refused move has something to leave alone.
        // Without a uid of its own: an explicit one does not advance the sequence
        // on PostgreSQL, and the create of an e-mail would then collide with it.
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_email')
            ->insert('tx_academicpersons_domain_model_email', [
                'pid' => 100,
                'contract' => 1,
                'email' => 'second@example.com',
                'type' => 'business',
                'sorting' => 20,
            ]);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/foreignProfile.csv');
        $content = $this->renderProfileEditingPage();
        foreach ([
            'update' => 'data-update-url',
            'updateSkipSync' => 'data-skip-sync-url',
            'updateVisibility' => 'data-visibility-url',
            'deleteImage' => 'data-delete-image-url',
            'createDocument' => 'data-create-document-url',
            'updateDocument' => 'data-update-document-url',
            'deleteDocument' => 'data-delete-document-url',
            'sortDocument' => 'data-sort-document-url',
            'toggleDocumentVisibility' => 'data-toggle-document-visibility-url',
            'createContractContact' => 'data-create-contract-contact-url',
            'updateContractContact' => 'data-update-contract-contact-url',
            'deleteContractContact' => 'data-delete-contract-contact-url',
            'sortContractContact' => 'data-sort-contract-contact-url',
            'toggleContractContactVisibility' => 'data-toggle-contract-contact-visibility-url',
        ] as $action => $attribute) {
            $this->assertSame(
                1,
                preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
                sprintf('The rendered component has no "%s" URL.', $attribute),
            );
            $url = html_entity_decode($match[1]);
            $this->endpointUrls[$action] = str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
        }
        // Rendering the page is not a write, and nothing that follows may count it.
        EditorWriteListener::reset();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(string $action, array $data, int $profileUid = self::PROFILE_ID): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => $profileUid, 'data' => $data], JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($this->endpointUrls[$action]))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        return $body;
    }

    /**
     * Every row of the tables a write changes, hidden and deleted ones included.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshotWrittenTables(): array
    {
        $snapshot = [];
        foreach (self::WRITTEN_TABLES as $table) {
            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $snapshot[$table] = $queryBuilder
                ->select('*')
                ->from($table)
                ->orderBy('uid')
                ->executeQuery()
                ->fetchAllAssociative();
        }
        return $snapshot;
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
     * @return list<string>
     */
    private function fetchVitaTitles(): array
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_academicpersons_domain_model_profile_information');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select('title')
            ->from('tx_academicpersons_domain_model_profile_information')
            ->where(
                $queryBuilder->expr()->eq('profile', $queryBuilder->createNamedParameter(self::PROFILE_ID)),
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter('curriculum_vitae')),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * Every writing JSON endpoint, the payload of a write it accepts, and what the
     * listener is told about it. The image endpoints are covered by tests of their own.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>, 2: array{action: string, section: string|null, record: int|null, contract: int|null, fields: array<string, mixed>}}>
     */
    public static function writeProvider(): \Generator
    {
        yield 'profile field' => [
            'update',
            ['website' => 'https://example.org'],
            ['action' => 'update', 'section' => null, 'record' => null, 'contract' => null, 'fields' => ['website' => 'https://example.org']],
        ];
        yield 'synchronisation switch' => [
            'updateSkipSync',
            ['skipSync' => true],
            ['action' => 'updateSkipSync', 'section' => null, 'record' => null, 'contract' => null, 'fields' => ['skipSync' => true]],
        ];
        yield 'visibility switch' => [
            'updateVisibility',
            ['hidden' => true],
            ['action' => 'updateVisibility', 'section' => null, 'record' => null, 'contract' => null, 'fields' => ['hidden' => true]],
        ];
        yield 'document create' => [
            'createDocument',
            ['section' => 'vita', 'fields' => ['title' => 'Vita third', 'year' => 2026]],
            ['action' => 'createDocument', 'section' => 'vita', 'record' => null, 'contract' => null, 'fields' => ['title' => 'Vita third', 'year' => 2026]],
        ];
        yield 'document update' => [
            'updateDocument',
            ['section' => 'vita', 'record' => 6, 'fields' => ['title' => 'Vita renamed']],
            ['action' => 'updateDocument', 'section' => 'vita', 'record' => 6, 'contract' => null, 'fields' => ['title' => 'Vita renamed']],
        ];
        yield 'document visibility' => [
            'toggleDocumentVisibility',
            ['section' => 'vita', 'record' => 6, 'hidden' => true],
            ['action' => 'toggleDocumentVisibility', 'section' => 'vita', 'record' => 6, 'contract' => null, 'fields' => ['hidden' => true]],
        ];
        yield 'document delete' => [
            'deleteDocument',
            ['section' => 'vita', 'record' => 6],
            ['action' => 'deleteDocument', 'section' => 'vita', 'record' => 6, 'contract' => null, 'fields' => []],
        ];
        yield 'document moved one step' => [
            'sortDocument',
            ['section' => 'vita', 'record' => 7, 'direction' => 'up'],
            ['action' => 'sortDocument', 'section' => 'vita', 'record' => 7, 'contract' => null, 'fields' => ['direction' => 'up']],
        ];
        yield 'document section reordered' => [
            'sortDocument',
            ['section' => 'cooperation', 'order' => [2, 1]],
            ['action' => 'sortDocument', 'section' => 'cooperation', 'record' => null, 'contract' => null, 'fields' => ['order' => [2, 1]]],
        ];
        yield 'contact create' => [
            'createContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'fields' => ['email' => 'new@example.com', 'type' => 'business']],
            ['action' => 'createContractContact', 'section' => 'emailAddresses', 'record' => null, 'contract' => 1, 'fields' => ['email' => 'new@example.com', 'type' => 'business']],
        ];
        yield 'contact update' => [
            'updateContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'fields' => ['email' => 'changed@example.com']],
            ['action' => 'updateContractContact', 'section' => 'emailAddresses', 'record' => 1, 'contract' => 1, 'fields' => ['email' => 'changed@example.com']],
        ];
        yield 'contact visibility' => [
            'toggleContractContactVisibility',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'hidden' => true],
            ['action' => 'toggleContractContactVisibility', 'section' => 'emailAddresses', 'record' => 1, 'contract' => 1, 'fields' => ['hidden' => true]],
        ];
        yield 'contact delete' => [
            'deleteContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1],
            ['action' => 'deleteContractContact', 'section' => 'emailAddresses', 'record' => 1, 'contract' => 1, 'fields' => []],
        ];
        yield 'contact moved one step' => [
            'sortContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'direction' => 'down'],
            ['action' => 'sortContractContact', 'section' => 'emailAddresses', 'record' => 1, 'contract' => 1, 'fields' => ['direction' => 'down']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array{action: string, section: string|null, record: int|null, contract: int|null, fields: array<string, mixed>} $expectedCall
     */
    #[Test]
    #[DataProvider('writeProvider')]
    public function everyWriteIsOfferedToTheListenerOnce(string $action, array $data, array $expectedCall): void
    {
        $this->setUpWriteEventTestCase();

        $response = $this->post($action, $data);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $body = $this->decode($response);
        $this->assertTrue($body['success'] ?? null);
        if (str_starts_with($action, 'sort')) {
            // A move that changes nothing would prove nothing in the refusal test.
            $this->assertTrue($body['changed'] ?? null);
        }
        $this->assertSame([$expectedCall], EditorWriteListener::$calls);
    }

    /**
     * @param array<string, mixed> $data
     * @param array{action: string, section: string|null, record: int|null, contract: int|null, fields: array<string, mixed>} $expectedCall
     */
    #[Test]
    #[DataProvider('writeProvider')]
    public function everyRefusedWriteIsAnsweredWithTheReasonAndStoresNothing(string $action, array $data, array $expectedCall): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->refuse(self::REASON);
        };
        $before = $this->snapshotWrittenTables();

        $response = $this->post($action, $data);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['success' => false, 'error' => 'write_refused', 'message' => self::REASON],
            $this->decode($response),
        );
        $this->assertSame([$expectedCall], EditorWriteListener::$calls);
        $this->assertSame($before, $this->snapshotWrittenTables());
    }

    /**
     * A project refuses new entries of one section, and the other sections keep working.
     */
    #[Test]
    public function aListenerRefusesNewVitaEntriesOnly(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            if ($event->getAction() === ProfileEditingAction::CreateDocument && $event->getSectionIdentifier() === 'vita') {
                $event->refuse('The curriculum vitae is maintained by the office.');
            }
        };

        $refused = $this->post('createDocument', ['section' => 'vita', 'fields' => ['title' => 'Vita third', 'year' => 2026]]);
        $accepted = $this->post('createDocument', ['section' => 'cooperation', 'fields' => ['title' => 'Cooperation third', 'year' => 2026]]);

        $this->assertSame(422, $refused->getStatusCode(), (string)$refused->getBody());
        $this->assertSame('write_refused', $this->decode($refused)['error'] ?? null);
        $this->assertSame('The curriculum vitae is maintained by the office.', $this->decode($refused)['message'] ?? null);
        $this->assertSame(['Vita first', 'Vita second'], $this->fetchVitaTitles());
        $this->assertSame(200, $accepted->getStatusCode(), (string)$accepted->getBody());
    }

    #[Test]
    public function theImageRemovalIsOfferedWithTheRemovedReferenceAndCanBeRefused(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->seedProfileImage();
        $deleteUrl = $this->extractImageDeleteUrl($this->renderProfileEditingPage());
        EditorWriteListener::reset();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->refuse(self::REASON);
        };
        $referenceUid = (int)$this->getConnectionPool()
            ->getConnectionForTable('sys_file_reference')
            ->executeQuery('SELECT uid FROM sys_file_reference WHERE tablenames = ? AND deleted = 0', ['tx_academicpersons_domain_model_profile'])
            ->fetchOne();

        $response = $this->postTo($deleteUrl, []);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['success' => false, 'error' => 'write_refused', 'message' => self::REASON],
            $this->decode($response),
        );
        $this->assertSame(
            [['action' => 'deleteImage', 'section' => null, 'record' => $referenceUid, 'contract' => null, 'fields' => []]],
            EditorWriteListener::$calls,
        );
        $this->assertSame(1, $this->getPersistedProfileImageCount());
        $this->assertFileExists($this->instancePath . '/fileadmin' . self::IMAGE_IDENTIFIER);
    }

    #[Test]
    public function aListenerCompletesTheTitleOfANewDocument(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $fields = $event->getFields();
            $fields['title'] = $fields['title'] . ' (confirmed)';
            $event->setFields($fields);
        };

        $response = $this->post('createDocument', ['section' => 'vita', 'fields' => ['title' => 'Vita third', 'year' => 2026]]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('Vita third (confirmed)', $this->decode($response)['item']['values']['title'] ?? null);
        $this->assertSame(['Vita first', 'Vita second', 'Vita third (confirmed)'], $this->fetchVitaTitles());
    }

    #[Test]
    public function aListenerChangesAProfileFieldAndAContactValue(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(match ($event->getAction()) {
                ProfileEditingAction::UpdateProfile => ['website' => 'https://example.org/replaced'],
                ProfileEditingAction::UpdateContractContact => ['email' => 'replaced@example.com'],
                default => $event->getFields(),
            });
        };

        $profileResponse = $this->post('update', ['website' => 'https://example.org']);
        $contactResponse = $this->post(
            'updateContractContact',
            ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'fields' => ['email' => 'changed@example.com']],
        );

        $this->assertSame(200, $profileResponse->getStatusCode(), (string)$profileResponse->getBody());
        $this->assertSame(['website' => 'https://example.org/replaced'], $this->decode($profileResponse)['data'] ?? null);
        $this->assertSame(
            'https://example.org/replaced',
            $this->fetchValue('tx_academicpersons_domain_model_profile', 'website', self::PROFILE_ID),
        );
        $this->assertSame(200, $contactResponse->getStatusCode(), (string)$contactResponse->getBody());
        $this->assertSame(
            'replaced@example.com',
            $this->fetchValue('tx_academicpersons_domain_model_email', 'email', 1),
        );
    }

    /**
     * A replaced value runs through the validation of a submitted one, so a listener
     * cannot store what the person could not.
     */
    #[Test]
    public function anEmptiedRequiredValueIsAnsweredAsAValidationError(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['title' => '', 'year' => 2026]);
        };
        $before = $this->snapshotWrittenTables();

        $response = $this->post('createDocument', ['section' => 'vita', 'fields' => ['title' => 'Vita third', 'year' => 2026]]);

        $this->assertSame(422, $response->getStatusCode(), (string)$response->getBody());
        $body = $this->decode($response);
        $this->assertSame('validation_failed', $body['error'] ?? null);
        $this->assertArrayHasKey('title', $body['errors'] ?? []);
        $this->assertSame($before, $this->snapshotWrittenTables());
    }

    /**
     * And through the sanitiser of a submitted one.
     */
    #[Test]
    public function aReplacedRichTextValueIsSanitised(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['bodytext' => '<p>Kept</p><script>alert(1)</script>']);
        };

        $response = $this->post('updateDocument', ['section' => 'cooperation', 'record' => 1, 'fields' => ['bodytext' => '<p>Submitted</p>']]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $stored = (string)$this->fetchValue('tx_academicpersons_domain_model_profile_information', 'bodytext', 1);
        $this->assertStringContainsString('Kept', $stored);
        $this->assertStringNotContainsString('<script', $stored);
    }

    /**
     * The locks of the configuration and of the synchronisation apply to a replaced
     * value as to a submitted one: the shipped settings lock the first name, so it is
     * dropped, and the rest of the write is stored.
     */
    #[Test]
    public function aLockedFieldAListenerAddsIsDropped(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['firstName' => 'Taken over', 'website' => 'https://example.org/replaced']);
        };

        $response = $this->post('update', ['website' => 'https://example.org']);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(['website' => 'https://example.org/replaced'], $this->decode($response)['data'] ?? null);
        $this->assertSame('Max', $this->fetchValue('tx_academicpersons_domain_model_profile', 'first_name', self::PROFILE_ID));
        $this->assertSame(
            'https://example.org/replaced',
            $this->fetchValue('tx_academicpersons_domain_model_profile', 'website', self::PROFILE_ID),
        );
    }

    /**
     * A listener mistake is loud: the fields of a write that carries none cannot be
     * replaced, and the request fails rather than silently storing what was asked.
     */
    #[Test]
    public function replacingTheFieldsOfAWriteWithoutFieldsFails(): void
    {
        $this->setUpWriteEventTestCase();
        EditorWriteListener::$behaviour = static function (BeforeProfileEditingWriteEvent $event): void {
            $event->setFields(['hidden' => false]);
        };
        $before = $this->snapshotWrittenTables();

        $response = $this->post('toggleDocumentVisibility', ['section' => 'vita', 'record' => 6, 'hidden' => true]);

        $this->assertSame(500, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame('internal_server_error', $this->decode($response)['error'] ?? null);
        $this->assertSame($before, $this->snapshotWrittenTables());
    }

    /**
     * Requests the editor refuses on its own, and the answer it gives. None of them
     * reaches a listener.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>, 2: int, 3: int, 4: string}>
     */
    public static function rejectedRequestProvider(): \Generator
    {
        yield 'foreign profile' => [
            'createDocument',
            ['section' => 'vita', 'fields' => ['title' => 'Vita third', 'year' => 2026]],
            self::FOREIGN_PROFILE_ID,
            403,
            'profile_not_editable',
        ];
        yield 'missing required value' => [
            'createDocument',
            ['section' => 'vita', 'fields' => ['title' => 'Vita third']],
            self::PROFILE_ID,
            422,
            'validation_failed',
        ];
        yield 'unknown profile property' => [
            'update',
            ['notAProperty' => 'x'],
            self::PROFILE_ID,
            422,
            'invalid_profile_data',
        ];
        yield 'invalid visibility flag' => [
            'toggleDocumentVisibility',
            ['section' => 'vita', 'record' => 6, 'hidden' => 'yes'],
            self::PROFILE_ID,
            400,
            'invalid_payload',
        ];
        yield 'incomplete order' => [
            'sortDocument',
            ['section' => 'cooperation', 'order' => [2]],
            self::PROFILE_ID,
            400,
            'invalid_payload',
        ];
        yield 'record of another section' => [
            'deleteDocument',
            ['section' => 'vita', 'record' => 1],
            self::PROFILE_ID,
            404,
            'document_not_found',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('rejectedRequestProvider')]
    public function aRejectedRequestNeverReachesAListener(
        string $action,
        array $data,
        int $profileUid,
        int $expectedStatus,
        string $expectedError,
    ): void {
        $this->setUpWriteEventTestCase();

        $response = $this->post($action, $data, $profileUid);

        $this->assertSame($expectedStatus, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame($expectedError, $this->decode($response)['error'] ?? null);
        $this->assertSame([], EditorWriteListener::$calls);
    }

    private function extractImageDeleteUrl(string $content): string
    {
        $this->assertSame(1, preg_match('@\bdata-delete-image-url="([^"]+)"@', $content, $match));
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function postTo(string $url, array $data): ResponseInterface
    {
        $this->endpointUrls['__direct'] = $url;
        return $this->post('__direct', $data);
    }
}
