<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The `actions` allow-list of the `contracts` section reaches the Contract
 * contact endpoints, not only the buttons.
 *
 * The contacts of a Contract have no section of their own - they are edited
 * inside the Contract editor - so they follow the allow-list and the `readOnly`
 * flag of the `contracts` document section. The fixture extension
 * `test_contract_contact_actions` takes every mutating action away from it and
 * leaves `readonly` false, which is the configuration an integrator writes for
 * "the contact rows are read only": the buttons disappear, and before this the
 * endpoints still carried the request out for anyone who sent it by hand.
 */
final class AcademicPersonsEditContractContactActionsTest extends AbstractFrontendProfilePluginTestCase
{
    private const CONTRACT_ID = 1;
    private const ADDRESS_ID = 1;

    /**
     * @var array<string, string>
     */
    private array $endpointUrls = [];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-contract-contact-actions');
        parent::setUp();
    }

    /**
     * Every request the allow-list forbids, and the payload it needs to get
     * past parsing. The `edit` and `delete` modes of the form endpoint are
     * entries of their own: the form is the first request of an edit, and
     * refusing it only at the following write would show the visitor a form
     * that cannot be submitted. `add` is not here - like a document section,
     * creation follows `readonly` rather than the list, and the fixture leaves
     * `readonly` false; `theListedActionIsStillCarriedOut()` covers it.
     *
     * @return \Generator<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function forbiddenContractContactActionProvider(): \Generator
    {
        yield 'form, edit' => [
            'contractContactForm',
            [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'mode' => 'edit',
            ],
        ];
        yield 'form, delete' => [
            'contractContactForm',
            [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'mode' => 'delete',
            ],
        ];
        yield 'update' => [
            'updateContractContact',
            [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'fields' => ['street' => 'Taken over'],
            ],
        ];
        yield 'delete' => [
            'deleteContractContact',
            ['contract' => self::CONTRACT_ID, 'section' => 'physicalAddresses', 'record' => self::ADDRESS_ID],
        ];
        yield 'visibility' => [
            'toggleContractContactVisibility',
            [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'hidden' => true,
            ],
        ];
        yield 'sort' => [
            'sortContractContact',
            [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'record' => self::ADDRESS_ID,
                'direction' => 'up',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('forbiddenContractContactActionProvider')]
    public function anActionTheSectionDoesNotListIsRefused(string $action, array $data): void
    {
        $this->setUpContractContactTestCase();

        $response = $this->postJson($this->endpointUrls[$action], [
            'profile' => self::PROFILE_ID,
            'data' => $data,
        ]);

        $this->assertSame(
            ['status' => 403, 'error' => 'contract_contact_action_not_allowed'],
            $this->decodeError($response),
        );
        $this->assertSame('Campus Road', $this->getAddressStreet(self::ADDRESS_ID));
        $this->assertSame(0, $this->getDeletedAddressCount());
        $this->assertSame(0, $this->getHiddenAddressCount());
    }

    /**
     * The other direction: `view` is listed, so reading a contact still works -
     * a blanket refusal would be just as wrong as no refusal at all. `add` is
     * governed by `readonly` rather than by the list, exactly as it is for a
     * document section, and the fixture leaves `readonly` false.
     */
    #[Test]
    public function theListedActionIsStillCarriedOut(): void
    {
        $this->setUpContractContactTestCase();

        $response = $this->postJson($this->endpointUrls['createContractContact'], [
            'profile' => self::PROFILE_ID,
            'data' => [
                'contract' => self::CONTRACT_ID,
                'section' => 'physicalAddresses',
                'fields' => ['street' => 'Added anyway'],
            ],
        ]);

        $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertTrue($body['success'] ?? false, (string)$response->getBody());
    }

    private function setUpContractContactTestCase(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv',
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2],
                ['uid' => self::PROFILE_ID],
            );
        $content = $this->renderProfileEditingPage();
        foreach ([
            'contractContactForm' => 'data-contract-contact-form-url',
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

    private function getAddressStreet(int $addressUid): string
    {
        return (string)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_address')
            ->executeQuery(
                'SELECT street FROM tx_academicpersons_domain_model_address WHERE uid = ?',
                [$addressUid],
            )
            ->fetchOne();
    }

    private function getHiddenAddressCount(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_address')
            ->executeQuery('SELECT COUNT(uid) FROM tx_academicpersons_domain_model_address WHERE hidden = 1')
            ->fetchOne();
    }

    private function getDeletedAddressCount(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_address')
            ->executeQuery('SELECT COUNT(uid) FROM tx_academicpersons_domain_model_address WHERE deleted = 1')
            ->fetchOne();
    }
}
