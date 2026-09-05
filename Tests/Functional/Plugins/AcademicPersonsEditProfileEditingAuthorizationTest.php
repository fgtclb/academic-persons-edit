<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The refusal side of the JSON API, driven through the real plugin.
 *
 * Everything the endpoints accept is covered by
 * {@see AcademicPersonsEditProfileEditingTest}; every test here asserts that a
 * request is *not* carried out. The fixture adds a second frontend user with a
 * profile, a contract, a contact and a document of its own, so "foreign" means a
 * record that really exists and really belongs to somebody else - a uid that does
 * not exist at all would pass a check that only looks for a row.
 *
 * The gate itself is `ProfileUpdateRequestService::validate()` for the JSON
 * actions and `ProfileController::initializeAction()` for the header; the
 * ownership of a document or contact record is re-derived from the profile's own
 * relations in the action.
 */
final class AcademicPersonsEditProfileEditingAuthorizationTest extends AbstractFrontendProfilePluginTestCase
{
    private const FOREIGN_PROFILE_ID = 2;
    private const FOREIGN_CONTRACT_ID = 90;
    private const FOREIGN_DOCUMENT_ID = 90;
    private const FOREIGN_ADDRESS_ID = 90;

    /**
     * @var array<string, string>
     */
    private array $endpointUrls = [];

    /**
     * The rendered editing page the endpoint URLs and the image form were read from.
     */
    private string $renderedProfileEditingPage = '';

    private function setUpAuthorizationTestCase(): void
    {
        $this->setUpProfileEditingTestCase();
        // The own profile needs records of its own, so that a refusal is a refusal
        // rather than an empty relation the action would reject anyway.
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv',
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2, 'cooperation' => 2],
                ['uid' => self::PROFILE_ID],
            );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/foreignProfile.csv');
        $content = $this->renderProfileEditingPage();
        $this->renderedProfileEditingPage = $content;
        foreach ([
            'update' => 'data-update-url',
            'updateSkipSync' => 'data-skip-sync-url',
            'deleteImage' => 'data-delete-image-url',
            'documentForm' => 'data-document-form-url',
            'createDocument' => 'data-create-document-url',
            'updateDocument' => 'data-update-document-url',
            'deleteDocument' => 'data-delete-document-url',
            'sortDocument' => 'data-sort-document-url',
            'contractContactForm' => 'data-contract-contact-form-url',
            'createContractContact' => 'data-create-contract-contact-url',
            'updateContractContact' => 'data-update-contract-contact-url',
            'deleteContractContact' => 'data-delete-contract-contact-url',
            'sortContractContact' => 'data-sort-contract-contact-url',
            'toggleContractContactVisibility' => 'data-toggle-contract-contact-visibility-url',
        ] as $action => $attribute) {
            $this->endpointUrls[$action] = $this->extractDataUrl($content, $attribute);
        }
    }

    private function extractDataUrl(string $content, string $attribute): string
    {
        $this->assertSame(
            1,
            preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
            sprintf('The rendered component has no "%s" URL.', $attribute),
        );
        $url = html_entity_decode($match[1]);
        return str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildJsonRequest(string $url, array $payload): InternalRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $body->rewind();
        return (new InternalRequest($url))
            ->withMethod('POST')
            ->withAddedHeader('Content-Type', 'application/json')
            ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
            ->withBody($body);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $url, array $payload): ResponseInterface
    {
        return $this->requestAsFrontendUser($this->buildJsonRequest($url, $payload));
    }

    /**
     * @return array{status: int, error: string}
     */
    private function decodeError(ResponseInterface $response): array
    {
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        $this->assertFalse($body['success'] ?? true, (string)$response->getBody());
        return [
            'status' => $response->getStatusCode(),
            'error' => (string)($body['error'] ?? ''),
        ];
    }

    /**
     * One endpoint per family, and the payload each needs to get past parsing.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function endpointFamilyProvider(): \Generator
    {
        yield 'profile fields' => ['update', ['firstName' => 'Taken over']];
        yield 'synchronisation switch' => ['updateSkipSync', ['skipSync' => true]];
        yield 'image' => ['deleteImage', []];
        yield 'document form' => ['documentForm', ['section' => 'cooperation', 'mode' => 'add']];
        yield 'document create' => ['createDocument', ['section' => 'cooperation', 'fields' => ['title' => 'x']]];
        yield 'document sort' => ['sortDocument', ['section' => 'cooperation', 'record' => 1, 'direction' => 'up']];
        yield 'contact form' => [
            'contractContactForm',
            ['contract' => 1, 'section' => 'physicalAddresses', 'mode' => 'add'],
        ];
        yield 'contact create' => [
            'createContractContact',
            ['contract' => 1, 'section' => 'physicalAddresses', 'fields' => ['street' => 'x']],
        ];
        yield 'contact visibility' => [
            'toggleContractContactVisibility',
            ['contract' => 1, 'section' => 'physicalAddresses', 'record' => 1, 'hidden' => true],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('endpointFamilyProvider')]
    public function everyEndpointFamilyRefusesAForeignProfile(string $action, array $data): void
    {
        $this->setUpAuthorizationTestCase();

        $response = $this->postJson($this->endpointUrls[$action], [
            'profile' => self::FOREIGN_PROFILE_ID,
            'data' => $data,
        ]);

        $this->assertSame(
            ['status' => 403, 'error' => 'profile_not_editable'],
            $this->decodeError($response),
        );
        $this->assertSame(
            'Eve',
            $this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_profile')
                ->executeQuery(
                    'SELECT first_name FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                    [self::FOREIGN_PROFILE_ID],
                )
                ->fetchOne(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('endpointFamilyProvider')]
    public function everyEndpointFamilyRefusesAnAnonymousCaller(string $action, array $data): void
    {
        $this->setUpAuthorizationTestCase();

        // No session cookie: the request is not sent through requestAsFrontendUser().
        $response = $this->requestFrontendPage(
            $this->buildJsonRequest($this->endpointUrls[$action], [
                'profile' => self::PROFILE_ID,
                'data' => $data,
            ]),
        );

        $this->assertSame(
            ['status' => 401, 'error' => 'authentication_required'],
            $this->decodeError($response),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('endpointFamilyProvider')]
    public function everyEndpointFamilyRefusesARequestWithoutTheEditorHeader(string $action, array $data): void
    {
        $this->setUpAuthorizationTestCase();
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => $data], JSON_THROW_ON_ERROR));
        $body->rewind();

        $response = $this->requestAsFrontendUser(
            (new InternalRequest($this->endpointUrls[$action]))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withBody($body),
        );

        $this->assertSame(
            ['status' => 400, 'error' => 'invalid_request'],
            $this->decodeError($response),
        );
    }

    #[Test]
    public function aGetRequestIsRefusedWithMethodNotAllowed(): void
    {
        $this->setUpAuthorizationTestCase();

        $response = $this->requestAsFrontendUser(
            (new InternalRequest($this->endpointUrls['update']))
                ->withMethod('GET')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest'),
        );

        $this->assertSame(
            ['status' => 405, 'error' => 'method_not_allowed'],
            $this->decodeError($response),
        );
    }

    #[Test]
    public function aFormEncodedRequestIsRefusedWithUnsupportedMediaType(): void
    {
        $this->setUpAuthorizationTestCase();
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query(['profile' => self::PROFILE_ID, 'data' => ['firstName' => 'x']]));
        $body->rewind();

        $response = $this->requestAsFrontendUser(
            (new InternalRequest($this->endpointUrls['update']))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );

        $this->assertSame(
            ['status' => 415, 'error' => 'unsupported_media_type'],
            $this->decodeError($response),
        );
    }

    /**
     * The HTML action propagates the site's own access denied response instead of
     * rendering. Which status that carries is the site's error handling, not this
     * extension's - the fixture site configures none, so core answers 404. What
     * this pins is that the response is an error and carries none of the foreign
     * profile.
     */
    #[Test]
    public function theIndexActionOfAForeignProfileIsAnsweredWithAnErrorResponse(): void
    {
        $this->setUpAuthorizationTestCase();
        $listPage = $this->getPageAsFrontendUser('https://www.acme.com/home');
        $ownProfileLink = $this->extractPluginActionLink(
            $listPage,
            'tx_academicpersonsedit_profileediting',
            'index',
            'profileUid',
            self::PROFILE_ID,
        );

        $response = $this->requestAsFrontendUser(
            new InternalRequest(
                str_replace(
                    'profileUid%5D=' . self::PROFILE_ID,
                    'profileUid%5D=' . self::FOREIGN_PROFILE_ID,
                    $ownProfileLink,
                ),
            ),
        );

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertStringNotContainsString('Eve', (string)$response->getBody());
        $this->assertStringNotContainsString('data-academic-persons-profile-editing', (string)$response->getBody());
    }

    #[Test]
    public function aDocumentOfAForeignProfileIsNotFound(): void
    {
        $this->setUpAuthorizationTestCase();

        foreach ([
            'updateDocument' => [
                'section' => 'cooperation',
                'record' => self::FOREIGN_DOCUMENT_ID,
                'fields' => ['title' => 'Taken over'],
            ],
            'deleteDocument' => ['section' => 'cooperation', 'record' => self::FOREIGN_DOCUMENT_ID],
            'documentForm' => [
                'section' => 'cooperation',
                'record' => self::FOREIGN_DOCUMENT_ID,
                'mode' => 'edit',
            ],
        ] as $action => $data) {
            $this->assertSame(
                ['status' => 404, 'error' => 'document_not_found'],
                $this->decodeError($this->postJson($this->endpointUrls[$action], [
                    'profile' => self::PROFILE_ID,
                    'data' => $data,
                ])),
                sprintf('Action "%s" did not refuse a foreign document.', $action),
            );
        }
        $this->assertSame(
            'Foreign cooperation',
            $this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
                ->executeQuery(
                    'SELECT title FROM tx_academicpersons_domain_model_profile_information'
                        . ' WHERE uid = ? AND deleted = 0',
                    [self::FOREIGN_DOCUMENT_ID],
                )
                ->fetchOne(),
        );
    }

    #[Test]
    public function aContractContactOfAForeignContractIsNotFound(): void
    {
        $this->setUpAuthorizationTestCase();

        $response = $this->postJson($this->endpointUrls['updateContractContact'], [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => self::FOREIGN_CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::FOREIGN_ADDRESS_ID,
                'fields' => ['street' => 'Taken over'],
            ],
        ]);

        $this->assertSame(404, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            'Foreign street 1',
            $this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_address')
                ->executeQuery(
                    'SELECT street FROM tx_academicpersons_domain_model_address WHERE uid = ? AND deleted = 0',
                    [self::FOREIGN_ADDRESS_ID],
                )
                ->fetchOne(),
        );
    }

    #[Test]
    public function anUnknownSectionIdentifierIsRefused(): void
    {
        $this->setUpAuthorizationTestCase();

        $this->assertSame(
            ['status' => 404, 'error' => 'unknown_document_section'],
            $this->decodeError($this->postJson($this->endpointUrls['documentForm'], [
                'profile' => self::PROFILE_ID,
                'data' => ['section' => 'thereIsNoSuchSection', 'mode' => 'add'],
            ])),
        );
        $this->assertSame(
            ['status' => 404, 'error' => 'unknown_contract_contact_section'],
            $this->decodeError($this->postJson($this->endpointUrls['contractContactForm'], [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => 'thereIsNoSuchSection',
                    'mode' => 'add',
                ],
            ])),
        );
    }

    /**
     * Builds the multipart image upload the editor sends, with a real file attached.
     *
     * The file matters: every guard below runs before Extbase maps the upload, so a
     * refusal that still produced a `sys_file` row would mean the guard fired too
     * late. The tests assert the storage stayed empty for exactly that reason.
     */
    private function buildImageUpload(?int $profileUid = null): InternalRequest
    {
        $submitData = $this->extractImageFormSubmissionData($this->renderedProfileEditingPage);
        $temporaryFile = $this->instancePath . '/typo3temp/'
            . uniqid('profile-editing-authorization-', false) . '.upload';
        copy(__DIR__ . '/Fixtures/Uploads/profile-image.png', $temporaryFile);
        $uploadedFiles = [];
        $this->addNestedFormValue(
            $uploadedFiles,
            $submitData['fileInputName'],
            new UploadedFile(
                $temporaryFile,
                (int)filesize($temporaryFile),
                UPLOAD_ERR_OK,
                'profile-image.png',
                'application/octet-stream',
            ),
        );
        $body = $submitData['body'];
        if ($profileUid !== null) {
            $body = $this->withReplacedIdentity($body, $profileUid);
            $this->assertNotSame($submitData['body'], $body, 'The image form carries no profile identity.');
        }
        return $this->buildProfileImageFormRequest($submitData['action'], $body, $uploadedFiles);
    }

    /**
     * Rewrites the `__identity` the image form carries, wherever the Extbase argument
     * namespace happens to nest it.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function withReplacedIdentity(array $body, int $profileUid): array
    {
        foreach ($body as $key => $value) {
            if ($key === '__identity') {
                $body[$key] = (string)$profileUid;
                continue;
            }
            if (is_array($value)) {
                $body[$key] = $this->withReplacedIdentity($value, $profileUid);
            }
        }
        return $body;
    }

    /**
     * The upload endpoint is the one endpoint whose guards are not the shared
     * `ProfileUpdateRequestService::validate()`: the header is demanded by the
     * hand-written `uploadImage` branch of `ProfileController::initializeAction()`,
     * the rest by `initializeUploadImageAction()`. Nothing else in this suite would
     * notice if either went away.
     */
    private function assertNoUploadReachedTheStorage(): void
    {
        $this->assertSame([], $this->getStoredFiles(), 'A refused upload created a file.');
        $this->assertSame(
            0,
            (int)$this->getConnectionPool()
                ->getConnectionForTable('sys_file_reference')
                ->executeQuery('SELECT COUNT(*) FROM sys_file_reference')
                ->fetchOne(),
            'A refused upload created a file reference.',
        );
        $this->assertSame(0, $this->getPersistedProfileImageCount());
    }

    #[Test]
    public function theImageUploadRefusesARequestWithoutTheEditorHeader(): void
    {
        $this->setUpAuthorizationTestCase();

        $response = $this->requestAsFrontendUser(
            $this->buildImageUpload()->withoutHeader('X-Requested-With'),
        );

        $this->assertSame(
            ['status' => 400, 'error' => 'invalid_request'],
            $this->decodeError($response),
        );
        $this->assertNoUploadReachedTheStorage();
    }

    #[Test]
    public function theImageUploadRefusesAnAnonymousCaller(): void
    {
        $this->setUpAuthorizationTestCase();

        // No session cookie: the request is not sent through requestAsFrontendUser().
        $response = $this->requestFrontendPage($this->buildImageUpload());

        // `initializeAction()` refuses an anonymous caller before
        // `initializeUploadImageAction()` is reached, so the answer is the site's
        // access denied response rather than the JSON error the editor parses. What
        // is pinned here is that the upload is refused and stored nothing.
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertNoUploadReachedTheStorage();
    }

    #[Test]
    public function theImageUploadRefusesAForeignProfile(): void
    {
        $this->setUpAuthorizationTestCase();

        $response = $this->requestAsFrontendUser(
            $this->buildImageUpload(self::FOREIGN_PROFILE_ID),
        );

        $this->assertSame(
            ['status' => 403, 'error' => 'profile_not_editable'],
            $this->decodeError($response),
        );
        $this->assertNoUploadReachedTheStorage();
    }
}
