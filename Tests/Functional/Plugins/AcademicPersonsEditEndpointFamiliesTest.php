<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TESTS\TestEditorWriteListener\EventListener\EditorWriteListener;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidActionNameException;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Each family of endpoints of the editor is served by a controller of its own:
 * `Profile` for the profile, its switches and its image, `Document` for the
 * contracts and profile information entries, `ContractContact` for the contacts
 * of a contract. A template override takes the controller name over with the
 * address, and a listener of a write reads it from the plugin action context.
 */
final class AcademicPersonsEditEndpointFamiliesTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * The page of the editing plugin in `profileEditingPage.csv`.
     */
    private const EDITING_PAGE_ID = 2;

    private const PLUGIN_NAMESPACE = 'tx_academicpersonsedit_profileediting';

    /**
     * The data attribute of every address the editor page hands its script, and
     * the controller it has to name.
     */
    private const ADDRESSES = [
        'data-update-url' => 'Profile',
        'data-skip-sync-url' => 'Profile',
        'data-visibility-url' => 'Profile',
        'data-delete-image-url' => 'Profile',
        'data-document-form-url' => 'Document',
        'data-create-document-url' => 'Document',
        'data-update-document-url' => 'Document',
        'data-delete-document-url' => 'Document',
        'data-sort-document-url' => 'Document',
        'data-toggle-document-visibility-url' => 'Document',
        'data-contract-contact-form-url' => 'ContractContact',
        'data-create-contract-contact-url' => 'ContractContact',
        'data-update-contract-contact-url' => 'ContractContact',
        'data-delete-contract-contact-url' => 'ContractContact',
        'data-sort-contract-contact-url' => 'ContractContact',
        'data-toggle-contract-contact-visibility-url' => 'ContractContact',
    ];

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

    /**
     * @return array<string, string> The address of every data attribute.
     */
    private function setUpEndpointTestCase(): array
    {
        $this->setUpProfileEditingTestCase();
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
        $content = $this->renderProfileEditingPage();
        $addresses = [];
        foreach (array_keys(self::ADDRESSES) as $attribute) {
            $this->assertSame(
                1,
                preg_match(sprintf('@\b%s="([^"]+)"@', preg_quote($attribute, '@')), $content, $match),
                sprintf('The rendered component has no "%s" URL.', $attribute),
            );
            $url = html_entity_decode($match[1]);
            $addresses[$attribute] = str_starts_with($url, '/') ? 'https://www.acme.com' . $url : $url;
        }
        // Rendering the page is not a write, and nothing that follows may count it.
        EditorWriteListener::reset();
        return $addresses;
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginArguments(string $url): array
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $arguments = $query[self::PLUGIN_NAMESPACE] ?? null;
        $this->assertIsArray($arguments, $url);
        return $arguments;
    }

    /**
     * An address of the endpoint page type on the editing page, built with a valid
     * cHash, so that a refusal comes from the plugin and not from the cHash check.
     */
    private function buildAddress(string $controller, string $action): string
    {
        $arguments = [self::PLUGIN_NAMESPACE => ['action' => $action, 'controller' => $controller]];
        $cacheHash = $this->get(CacheHashCalculator::class)
            ->generateForParameters(HttpUtility::buildQueryString(['id' => self::EDITING_PAGE_ID] + $arguments));
        return 'https://www.acme.com/home?' . HttpUtility::buildQueryString(
            ['type' => 1733735] + $arguments + ['cHash' => $cacheHash],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function postJson(string $url, array $data): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode(['profile' => self::PROFILE_ID, 'data' => $data], JSON_THROW_ON_ERROR));
        $body->rewind();
        return $this->requestAsFrontendUser(
            (new InternalRequest($url))
                ->withMethod('POST')
                ->withAddedHeader('Content-Type', 'application/json')
                ->withAddedHeader('X-Requested-With', 'XMLHttpRequest')
                ->withBody($body),
        );
    }

    private function countCooperationEntries(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile_information')
            ->count('*', 'tx_academicpersons_domain_model_profile_information', ['type' => 'cooperation', 'deleted' => 0]);
    }

    #[Test]
    public function theShippedTemplateAddressesEachFamilyUnderItsController(): void
    {
        $addresses = $this->setUpEndpointTestCase();

        $controllers = [];
        foreach ($addresses as $attribute => $url) {
            $controllers[$attribute] = $this->pluginArguments($url)['controller'] ?? null;
        }

        $this->assertSame(self::ADDRESSES, $controllers);
    }

    /**
     * The address a template override builds the way the shipped template does
     * reaches the endpoint. Built here rather than read from the page, so that
     * the test states what an override has to name.
     */
    #[Test]
    public function aDocumentCreationAddressedUnderItsControllerIsStored(): void
    {
        $this->setUpEndpointTestCase();
        $before = $this->countCooperationEntries();

        $response = $this->postJson(
            $this->buildAddress('Document', 'createDocument'),
            ['section' => 'cooperation', 'fields' => ['title' => 'Addressed', 'year' => 2026]],
        );

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame($before + 1, $this->countCooperationEntries());
    }

    /**
     * An override that kept the address of a document endpoint under `Profile`
     * names an action that controller does not have, and Extbase refuses it
     * before any controller runs. The exception reaches the test as it is, and a
     * site answers it with its error handling.
     */
    #[Test]
    public function aDocumentCreationAddressedUnderProfileFailsAndStoresNothing(): void
    {
        $this->setUpEndpointTestCase();
        $before = $this->countCooperationEntries();

        try {
            $response = $this->postJson(
                $this->buildAddress('Profile', 'createDocument'),
                ['section' => 'cooperation', 'fields' => ['title' => 'Misaddressed', 'year' => 2026]],
            );
            $this->fail('The request was answered: ' . $response->getStatusCode() . ' ' . $response->getBody());
        } catch (InvalidActionNameException $exception) {
            $this->assertSame(1313855175, $exception->getCode());
        }

        $this->assertSame($before, $this->countCooperationEntries());
        $this->assertSame([], EditorWriteListener::$calls);
    }

    /**
     * @return \Generator<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function writeProvider(): \Generator
    {
        yield 'profile field' => ['data-update-url', ['firstName' => 'Changed'], 'Profile'];
        yield 'document creation' => [
            'data-create-document-url',
            ['section' => 'cooperation', 'fields' => ['title' => 'Offered', 'year' => 2026]],
            'Document',
        ];
        yield 'contact creation' => [
            'data-create-contract-contact-url',
            ['contract' => 1, 'section' => 'emailAddresses', 'fields' => ['email' => 'offered@example.com']],
            'ContractContact',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('writeProvider')]
    public function theWriteOfferNamesTheControllerOfTheEndpoint(string $attribute, array $data, string $controller): void
    {
        $addresses = $this->setUpEndpointTestCase();
        $action = $this->pluginArguments($addresses[$attribute])['action'] ?? null;

        $response = $this->postJson($addresses[$attribute], $data);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame([[$controller, $action]], EditorWriteListener::$contexts);
    }
}
