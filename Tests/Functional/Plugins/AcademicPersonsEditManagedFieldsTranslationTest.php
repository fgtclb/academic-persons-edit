<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The managed fields of `AcademicPersonsEditManagedFieldsTest` in a translated
 * site language.
 *
 * There the editor holds translation overlays. A managed field whose column
 * all languages share, such as the website, stays locked, as the backend
 * shows it read-only on a translation. A translatable one, such as the
 * contract position, is text of the translation and editable. A delete
 * removes the default-language record, so it is refused for a row the
 * synchronisation owns in every language.
 */
final class AcademicPersonsEditManagedFieldsTranslationTest extends AbstractFrontendProfilePluginTestCase
{
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-managed-fields-editor');
        parent::setUp();
    }

    #[Test]
    public function theGermanEditorKeepsTheDeleteOfASynchronisedContractAway(): void
    {
        $content = $this->renderGermanEditor();

        $this->assertSame(1, preg_match('@<article\b[^>]*\bdata-item-uid="1".*?</article>@s', $content, $row));
        $this->assertStringContainsString('data-pe-document-managed-badge', $row[0]);
        $this->assertStringNotContainsString('data-pe-document-delete', $row[0]);
        $this->assertStringContainsString('data-pe-document-edit', $row[0]);
        $this->assertSame(1, preg_match('@<article\b[^>]*\bdata-item-uid="2".*?</article>@s', $content, $manual));
        $this->assertStringContainsString('data-pe-document-delete', $manual[0]);
    }

    #[Test]
    public function aSharedManagedFieldStaysLockedAndATranslatableOneIsEditable(): void
    {
        $content = $this->renderGermanEditor();

        $this->assertSame(1, substr_count($content, 'data-pe-field-managed'));
        $this->assertMatchesRegularExpression('@data-pe-field-managed\s+data-pe-for="profile-editing-1-website"@', $content);
        $this->assertSame(1, preg_match('@<input\b(?=[^>]*\bid="profile-editing-1-website")[^>]*>@', $content, $website));
        $this->assertStringContainsString('readonly="readonly"', $website[0]);

        $response = $this->postJson($this->extractUrl($content, 'data-document-form-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1, 'mode' => 'edit'],
        ]);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $fields = array_column(json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR)['fields'], null, 'name');
        $this->assertFalse($fields['position']['managed']);
        $this->assertFalse($fields['position']['readOnly']);
    }

    #[Test]
    public function aSharedManagedFieldKeepsItsValueWhenSavedInGerman(): void
    {
        $content = $this->renderGermanEditor();

        $response = $this->postJson($this->extractUrl($content, 'data-update-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['website' => 'https://changed.example.org'],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_academicpersons_domain_model_profile');
        $queryBuilder->getRestrictions()->removeAll();
        $this->assertSame(
            ['https://synced.example.org', 'https://synced.example.org'],
            array_map('strval', $queryBuilder
                ->select('website')
                ->from('tx_academicpersons_domain_model_profile')
                ->orderBy('uid')
                ->executeQuery()
                ->fetchFirstColumn()),
        );
    }

    #[Test]
    public function aSynchronisedContractCannotBeDeletedFromTheGermanEditor(): void
    {
        $content = $this->renderGermanEditor();

        $response = $this->postJson($this->extractUrl($content, 'data-delete-document-url'), [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => 1],
        ]);

        $this->assertSame(403, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(0, $this->deletedFlag(1));
    }

    private function renderGermanEditor(): string
    {
        $this->setUpFrontendProfileTestCase(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/profileEditingPage.csv',
        );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AcademicPersonsEditManagedFields/records.csv');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['contracts' => 2, 'import_identifier' => 'fe_users:1', 'skip_sync' => 0, 'website' => 'https://synced.example.org'],
                ['uid' => self::PROFILE_ID],
            );
        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'uid' => 4,
            'pid' => 1,
            'doktype' => 1,
            'sys_language_uid' => 1,
            'l10n_parent' => 2,
            'slug' => '/home',
            'title' => 'Home (DE)',
        ]);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 3,
            'pid' => 2,
            'sys_language_uid' => 1,
            'l18n_parent' => 1,
            'CType' => 'academicpersonsedit_profileediting',
        ]);
        $this->writeFrontendPluginTestSite([
            $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
            $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
        ]);
        $this->get(DataHandlerExecutionContext::class)->runAsBackendUser(
            function (): void {
                $this->assertGreaterThan(self::PROFILE_ID, $this->get(ProfileTranslator::class)->translateTo(self::PROFILE_ID, 1));
            },
        );
        return $this->getPageAsFrontendUser(
            $this->extractPluginActionLink(
                $this->getPageAsFrontendUser('https://www.acme.com/de/home'),
                'tx_academicpersonsedit_profileediting',
                'index',
                'profileUid',
                self::PROFILE_ID,
            ),
        );
    }

    private function extractUrl(string $content, string $attribute): string
    {
        $this->assertSame(1, preg_match('@\b' . preg_quote($attribute, '@') . '="([^"]+)"@', $content, $match), $attribute);
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

    private function deletedFlag(int $contractUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_academicpersons_domain_model_contract');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder
            ->select('deleted')
            ->from('tx_academicpersons_domain_model_contract')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($contractUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
