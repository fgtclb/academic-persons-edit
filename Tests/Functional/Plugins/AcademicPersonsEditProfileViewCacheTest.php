<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A change saved through the `academicpersonsedit_profileediting` plugin leaves the
 * cached public views of the profile: the list, tagged `profile_list_view`, and the
 * detail view, tagged `profile_detail_view_<uid>`. Before ACE-858 both kept showing the
 * old content on a single-language site until the page cache expired, because the
 * editor writes through Extbase and only the DataHandler hooks flushed those tags.
 *
 * The cached views are stand-in entries carrying the tags the public plugins of
 * `academic_persons` put on their pages, so the test does not depend on rendering them.
 */
final class AcademicPersonsEditProfileViewCacheTest extends AbstractFrontendProfilePluginTestCase
{
    private const CONTRACT_ID = 1;

    /**
     * @param array<string, mixed> $additionalConfiguration
     * @return array<string, mixed>
     */
    protected function frontendPluginTestConfiguration(array $additionalConfiguration = []): array
    {
        return parent::frontendPluginTestConfiguration(array_replace_recursive(
            ['SYS' => ['caching' => ['cacheConfigurations' => ['pages' => ['backend' => Typo3DatabaseBackend::class]]]]],
            $additionalConfiguration,
        ));
    }

    #[Test]
    public function savingAProfileFieldFlushesTheCachedPublicViewsOfTheProfile(): void
    {
        $this->setUpProfileEditingTestCase();
        $updateUrl = $this->extractDataUrl($this->renderProfileEditingPage(), 'data-update-url');
        $cache = $this->cachePublicViews();

        $response = $this->postJson(
            $updateUrl,
            ['profile' => self::PROFILE_ID, 'data' => ['coreCompetences' => '<p>Submitted</p>']],
        );

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            '<p>Submitted</p>',
            $this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_profile')
                ->executeQuery(
                    'SELECT core_competences FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                    [self::PROFILE_ID],
                )
                ->fetchOne(),
            'The profile field was not saved.',
        );
        $this->assertPublicViewsOfTheProfileWereFlushed($cache);
    }

    /**
     * Hiding a contract writes no profile field, only the contract, so this is the path
     * that resolves the profile from a record of it.
     */
    #[Test]
    public function hidingAContractFlushesTheCachedPublicViewsOfTheProfile(): void
    {
        $this->setUpProfileEditingTestCase();
        $this->importCSVDataSet(
            __DIR__ . '/Fixtures/AcademicPersonsEditProfileEditing/structuredDocumentSections.csv',
        );
        $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update('tx_academicpersons_domain_model_profile', ['contracts' => 2], ['uid' => self::PROFILE_ID]);
        $toggleUrl = $this->extractDataUrl(
            $this->renderProfileEditingPage(),
            'data-toggle-document-visibility-url',
        );
        $cache = $this->cachePublicViews();

        $response = $this->postJson($toggleUrl, [
            'profile' => self::PROFILE_ID,
            'data' => ['section' => 'contracts', 'record' => self::CONTRACT_ID, 'hidden' => true],
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertSame(
            1,
            (int)$this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_contract')
                ->executeQuery(
                    'SELECT hidden FROM tx_academicpersons_domain_model_contract WHERE uid = ?',
                    [self::CONTRACT_ID],
                )
                ->fetchOne(),
            'The contract was not hidden.',
        );
        $this->assertPublicViewsOfTheProfileWereFlushed($cache);
    }

    private function cachePublicViews(): FrontendInterface
    {
        $cache = $this->get(CacheManager::class)->getCache('pages');
        $cache->set('public-list', 'list', ['profile_list_view']);
        $cache->set('public-detail-1', 'detail', [sprintf('profile_detail_view_%d', self::PROFILE_ID)]);
        $cache->set('public-detail-2', 'detail', ['profile_detail_view_2']);
        return $cache;
    }

    private function assertPublicViewsOfTheProfileWereFlushed(FrontendInterface $cache): void
    {
        $this->assertFalse($cache->has('public-list'), 'The cached list still shows the old profile.');
        $this->assertFalse($cache->has('public-detail-1'), 'The cached detail view still shows the old profile.');
        $this->assertTrue($cache->has('public-detail-2'), 'The cached detail view of another profile was flushed.');
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
}
