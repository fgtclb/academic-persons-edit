<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Service\ManagedRecordLocks;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Fields and rows a synchronisation owns, as the editor presents and
 * protects them.
 *
 * The fixture extension `test_managed_fields_editor` manages the profile
 * website, the contract position, the e-mail address (not its type) and both
 * fields of a phone number. The profile carries an import identifier, and so
 * do contract 1, e-mail 1 and phone number 1. Contract 2, e-mail 2 and phone
 * number 2 were added by the owner.
 */
final class AcademicPersonsEditManagedFieldsTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * @var array<string, string>
     */
    private array $endpointUrls = [];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-managed-fields-editor');
        parent::setUp();
    }

    #[Test]
    public function aManagedProfileFieldIsRenderedReadOnlyAndMarked(): void
    {
        $content = $this->setUpManagedRecords();

        $this->assertStringContainsString('readonly="readonly"', $this->control($content, 'profile-editing-1-website'));
        $this->assertStringNotContainsString('readonly', $this->control($content, 'profile-editing-1-websiteTitle'));
        $this->assertSame(1, substr_count($content, 'data-pe-field-managed'));
        $this->assertMatchesRegularExpression('@data-pe-field-managed\s+data-pe-for="profile-editing-1-website"@', $content);
    }

    #[Test]
    public function aSynchronisedContractOffersNoDelete(): void
    {
        $content = $this->setUpManagedRecords();

        $synchronised = $this->contractRow($content, 1);
        $this->assertStringContainsString('data-pe-document-managed-badge', $synchronised);
        $this->assertStringNotContainsString('data-pe-document-delete', $synchronised);
        $this->assertStringContainsString('data-pe-document-edit', $synchronised);
        $this->assertStringContainsString('data-pe-document-hide', $synchronised);
        $manual = $this->contractRow($content, 2);
        $this->assertStringNotContainsString('data-pe-document-managed-badge', $manual);
        $this->assertStringContainsString('data-pe-document-delete', $manual);
    }

    #[Test]
    public function aProfileExcludedFromTheSynchronisationHasNothingManaged(): void
    {
        $content = $this->setUpManagedRecords(skipSync: true);

        $this->assertStringNotContainsString('data-pe-field-managed', $content);
        $this->assertStringNotContainsString('readonly', $this->control($content, 'profile-editing-1-website'));
        $this->assertStringNotContainsString('data-pe-document-managed-badge', $content);
        $this->assertStringContainsString('data-pe-document-delete', $this->contractRow($content, 1));
        $items = $this->contactItems();
        $this->assertSame(
            ['managed' => false, 'editable' => true, 'deletable' => true],
            $this->itemFlags($items['phoneNumbers'][1]),
        );

        $this->assertSame(200, $this->deleteContact('phoneNumbers', 1)->getStatusCode());
        $this->assertFalse($this->recordExists('tx_academicpersons_domain_model_phone_number', 1));
    }

    /**
     * The flags the contact list takes its buttons from: an e-mail with one
     * managed field loses the delete only, a phone number with every field
     * managed loses the edit too, and a contact the owner added keeps both.
     */
    #[Test]
    public function theContactsOfASynchronisedContractCarryTheirLocks(): void
    {
        $this->setUpManagedRecords();

        $items = $this->contactItems();

        $this->assertSame(
            ['managed' => true, 'editable' => true, 'deletable' => false],
            $this->itemFlags($items['emailAddresses'][1]),
        );
        $this->assertSame(
            ['managed' => false, 'editable' => true, 'deletable' => true],
            $this->itemFlags($items['emailAddresses'][2]),
        );
        $this->assertSame(
            ['managed' => true, 'editable' => false, 'deletable' => false],
            $this->itemFlags($items['phoneNumbers'][1]),
        );
        $this->assertSame(
            ['managed' => false, 'editable' => true, 'deletable' => true],
            $this->itemFlags($items['phoneNumbers'][2]),
        );
    }

    #[Test]
    public function theFormOfASynchronisedEmailMarksTheManagedField(): void
    {
        $this->setUpManagedRecords();

        $body = $this->decode($this->postJson($this->endpointUrls['contractContactForm'], [
            'profile' => self::PROFILE_ID,
            'data' => ['contract' => 1, 'section' => 'emailAddresses', 'record' => 1, 'mode' => 'edit'],
        ]));

        $fields = array_column($body['fields'], null, 'name');
        $this->assertSame(
            ['required' => false, 'readOnly' => true, 'managed' => true],
            array_intersect_key($fields['email'], ['readOnly' => 0, 'managed' => 0, 'required' => 0]),
        );
        $this->assertSame(
            ['readOnly' => false, 'managed' => false],
            array_intersect_key($fields['type'], ['readOnly' => 0, 'managed' => 0]),
        );
    }

    /**
     * The browser sends every field of the open contact. The managed address
     * keeps its stored value, the type of the same request is stored.
     */
    #[Test]
    public function aChangedSynchronisedEmailKeepsItsAddress(): void
    {
        $this->setUpManagedRecords();

        $response = $this->postJson($this->endpointUrls['updateContractContact'], [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => 1,
                'section' => 'emailAddresses',
                'record' => 1,
                'fields' => ['email' => 'changed@example.com', 'type' => 'private'],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['email' => 'rectorate@example.com', 'type' => 'private'],
            $this->row('tx_academicpersons_domain_model_email', 1, ['email', 'type']),
        );
    }

    #[Test]
    public function aManualEmailStoresItsAddress(): void
    {
        $this->setUpManagedRecords();

        $response = $this->postJson($this->endpointUrls['updateContractContact'], [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => 1,
                'section' => 'emailAddresses',
                'record' => 2,
                'fields' => ['email' => 'changed@example.com', 'type' => 'private'],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['email' => 'changed@example.com', 'type' => 'private'],
            $this->row('tx_academicpersons_domain_model_email', 2, ['email', 'type']),
        );
    }

    #[Test]
    public function aSynchronisedContactCannotBeDeleted(): void
    {
        $this->setUpManagedRecords();

        $this->assertSame(
            ['status' => 403, 'error' => 'contract_contact_action_not_allowed'],
            $this->decodeError($this->deleteContact('phoneNumbers', 1)),
        );
        $this->assertTrue($this->recordExists('tx_academicpersons_domain_model_phone_number', 1));
        $this->assertSame(
            ['status' => 403, 'error' => 'contract_contact_action_not_allowed'],
            $this->decodeError($this->deleteContact('emailAddresses', 1)),
        );
        $this->assertTrue($this->recordExists('tx_academicpersons_domain_model_email', 1));
    }

    #[Test]
    public function aManualContactIsDeleted(): void
    {
        $this->setUpManagedRecords();

        $this->assertSame(200, $this->deleteContact('phoneNumbers', 2)->getStatusCode());
        $this->assertFalse($this->recordExists('tx_academicpersons_domain_model_phone_number', 2));
    }

    /**
     * Every field of the phone number is managed, so neither the form of an
     * edit nor the write of one is accepted.
     */
    #[Test]
    public function aFullyManagedContactCannotBeEdited(): void
    {
        $this->setUpManagedRecords();

        $this->assertSame(
            ['status' => 403, 'error' => 'contract_contact_action_not_allowed'],
            $this->decodeError($this->postJson($this->endpointUrls['contractContactForm'], [
                'profile' => self::PROFILE_ID,
                'data' => ['contract' => 1, 'section' => 'phoneNumbers', 'record' => 1, 'mode' => 'edit'],
            ])),
        );
        $this->assertSame(
            ['status' => 403, 'error' => 'contract_contact_action_not_allowed'],
            $this->decodeError($this->postJson($this->endpointUrls['updateContractContact'], [
                'profile' => self::PROFILE_ID,
                'data' => [
                    'contract' => 1,
                    'section' => 'phoneNumbers',
                    'record' => 1,
                    'fields' => ['phoneNumber' => '+49 30 1', 'type' => 'private'],
                ],
            ])),
        );
        $this->assertSame(
            ['phone_number' => '+49 2161 123456', 'type' => 'business'],
            $this->row('tx_academicpersons_domain_model_phone_number', 1, ['phone_number', 'type']),
        );
    }

    /**
     * The synchronisation never writes `hidden`, so hiding a contact it
     * maintains stays the owner's choice. That a later run leaves the flag
     * alone is pinned by the synchronisation tests of `academic_persons`,
     * `UsingDefaultProfileFactoryOnlyTest`, case "#8 exclude pids - keeps
     * hidden relation records hidden".
     */
    #[Test]
    public function aSynchronisedContactCanBeHidden(): void
    {
        $this->setUpManagedRecords();

        $response = $this->postJson($this->endpointUrls['toggleContractContactVisibility'], [
            'profile' => self::PROFILE_ID,
            'data' => ['contract' => 1, 'section' => 'phoneNumbers', 'record' => 1, 'hidden' => true],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(1, (int)$this->row('tx_academicpersons_domain_model_phone_number', 1, ['hidden'])['hidden']);
    }

    #[Test]
    public function aSynchronisedContractKeepsItsPositionAndStoresTheRest(): void
    {
        $this->setUpManagedRecords();

        $response = $this->postJson($this->endpointUrls['updateDocument'], [
            'profile' => self::PROFILE_ID,
            'data' => [
                'section' => 'contracts',
                'record' => 1,
                'fields' => ['position' => 'Changed', 'room' => 'B 1.23'],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['position' => 'Rectorate', 'room' => 'B 1.23'],
            $this->row('tx_academicpersons_domain_model_contract', 1, ['position', 'room']),
        );
    }

    #[Test]
    public function aSynchronisedContractCannotBeDeleted(): void
    {
        $this->setUpManagedRecords();

        $this->assertSame(
            ['status' => 403, 'error' => 'document_action_not_allowed'],
            $this->decodeError($this->postJson($this->endpointUrls['deleteDocument'], [
                'profile' => self::PROFILE_ID,
                'data' => ['section' => 'contracts', 'record' => 1],
            ])),
        );
        $this->assertTrue($this->recordExists('tx_academicpersons_domain_model_contract', 1));
    }

    #[Test]
    public function theFormOfASynchronisedContractMarksTheManagedField(): void
    {
        $this->setUpManagedRecords();

        $body = $this->decode($this->postJson($this->endpointUrls['documentForm'], [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'mode' => 'edit'],
        ]));

        $fields = array_column($body['fields'], null, 'name');
        // The position is required in the shipped settings, and a managed one
        // is not: the owner cannot supply a value they cannot edit.
        $this->assertSame(
            ['required' => false, 'readOnly' => true, 'managed' => true],
            array_intersect_key($fields['position'], ['readOnly' => 0, 'managed' => 0, 'required' => 0]),
        );
        $this->assertFalse($fields['room']['managed']);
    }

    /**
     * The fixture manages one contract field, so the edit of a contract is
     * decided here with the managed names handed in, as the endpoints hand in
     * what the resolver answered.
     */
    #[Test]
    public function aContractWhoseEditableFieldsAreAllManagedOffersNoEdit(): void
    {
        $this->setUpManagedRecords();
        $settings = $this->get(AcademicPersonsSettings::class);
        $section = $settings->getDocumentSection('contracts');
        $this->assertNotNull($section);
        $contract = new Contract();
        $contract->_setProperty('uid', 1);
        $every = array_values(array_map(
            static fn($field): string => $field->propertyName,
            $settings->contractFields,
        ));
        $locks = $this->get(ManagedRecordLocks::class);

        $this->assertSame(['delete', 'edit'], $locks->getLockedDocumentActions($section, $contract, $every));
        $this->assertSame(['delete'], $locks->getLockedDocumentActions($section, $contract, array_slice($every, 1)));
        $this->assertSame([], $locks->getLockedDocumentActions($section, $contract, []));
    }

    /**
     * The switch is the whole request of its endpoint, so a locked switch is
     * refused rather than ignored as a locked field of the profile form is.
     */
    #[Test]
    public function aLockedSynchronisationSwitchIsStillRefused(): void
    {
        $this->setUpManagedRecords();

        $this->assertSame(
            ['status' => 422, 'error' => 'invalid_profile_data'],
            $this->decodeError($this->postJson($this->endpointUrls['skipSync'], [
                'profile' => self::PROFILE_ID,
                'data' => ['skipSync' => true],
            ])),
        );
        $this->assertSame(0, (int)$this->row('tx_academicpersons_domain_model_profile', self::PROFILE_ID, ['skip_sync'])['skip_sync']);
    }

    #[Test]
    public function aSubmittedManagedProfileFieldKeepsItsValue(): void
    {
        $this->setUpManagedRecords();

        $response = $this->postJson($this->endpointUrls['update'], [
            'profile' => self::PROFILE_ID,
            'data' => ['website' => 'https://changed.example.org', 'websiteTitle' => 'Changed'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            ['website' => 'https://synced.example.org', 'website_title' => 'Changed'],
            $this->row('tx_academicpersons_domain_model_profile', self::PROFILE_ID, ['website', 'website_title']),
        );
    }

    private function setUpManagedRecords(bool $skipSync = false): string
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
                    'skip_sync' => (int)$skipSync,
                    'website' => 'https://synced.example.org',
                ],
                ['uid' => self::PROFILE_ID],
            );
        $content = $this->renderProfileEditingPage();
        foreach ([
            'update' => 'data-update-url',
            'documentForm' => 'data-document-form-url',
            'updateDocument' => 'data-update-document-url',
            'deleteDocument' => 'data-delete-document-url',
            'contractContactForm' => 'data-contract-contact-form-url',
            'updateContractContact' => 'data-update-contract-contact-url',
            'deleteContractContact' => 'data-delete-contract-contact-url',
            'toggleContractContactVisibility' => 'data-toggle-contract-contact-visibility-url',
            'skipSync' => 'data-skip-sync-url',
        ] as $action => $attribute) {
            $this->assertSame(
                1,
                preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
                sprintf('The rendered component has no "%s" URL.', $attribute),
            );
            $url = html_entity_decode($match[1]);
            $this->endpointUrls[$action] = str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
        }
        return $content;
    }

    private function control(string $content, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('@<input\b(?=[^>]*\bid="' . preg_quote($id, '@') . '")[^>]*>@', $content, $control),
            sprintf('The control "%s" is not rendered.', $id),
        );
        return $control[0];
    }

    private function contractRow(string $content, int $uid): string
    {
        $this->assertSame(
            1,
            preg_match('@<article\b[^>]*\bdata-item-uid="' . $uid . '".*?</article>@s', $content, $row),
            sprintf('The row of contract %d is not rendered.', $uid),
        );
        return $row[0];
    }

    /**
     * The contacts of contract 1 by section and uid, as its view form answers them.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function contactItems(): array
    {
        $body = $this->decode($this->postJson($this->endpointUrls['documentForm'], [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'mode' => 'view'],
        ]));
        $items = [];
        foreach ($body['contactSections'] as $section) {
            foreach ($section['items'] as $item) {
                $items[$section['identifier']][$item['uid']] = $item;
            }
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{managed: mixed, editable: mixed, deletable: mixed}
     */
    private function itemFlags(array $item): array
    {
        return ['managed' => $item['managed'], 'editable' => $item['editable'], 'deletable' => $item['deletable']];
    }

    private function deleteContact(string $section, int $uid): ResponseInterface
    {
        return $this->postJson($this->endpointUrls['deleteContractContact'], [
            'profile' => self::PROFILE_ID,
            'data' => ['contract' => 1, 'section' => $section, 'record' => $uid],
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body, (string)$response->getBody());
        $this->assertTrue($body['success'] ?? false, (string)$response->getBody());
        return $body;
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
     * Read past every restriction: `Connection::select()` leaves a hidden row out.
     *
     * @param list<string> $columns
     * @return array<string, mixed>
     */
    private function row(string $table, int $uid, array $columns): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$columns)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        $this->assertIsArray($row);
        return $row;
    }

    private function recordExists(string $table, int $uid): bool
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable($table)
            ->count('uid', $table, ['uid' => $uid, 'deleted' => 0]) === 1;
    }
}
