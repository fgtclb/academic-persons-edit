<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A change saved through the `academicpersonsedit_profileediting` plugin leaves the
 * cached public views of the profile: the list, tagged `profile_list_view`, and the
 * detail view, tagged `profile_detail_view_<uid>`. Before ACE-828 both kept showing the
 * old content until the page cache expired, because the editor writes through Extbase
 * and only the DataHandler hooks flushed those tags.
 *
 * The cached views are stand-in entries carrying the tags the public plugins of
 * `academic_persons` put on their pages, so the test does not depend on rendering them.
 */
final class AcademicPersonsEditProfileViewCacheTest extends AbstractProfileEditingPluginTestCase
{
    protected function setUp(): void
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->configurationToUseInTestInstance,
            ['SYS' => ['caching' => ['cacheConfigurations' => ['pages' => ['backend' => Typo3DatabaseBackend::class]]]]],
        );
        parent::setUp();
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

    #[Test]
    public function savingTheProfileFormFlushesTheCachedPublicViewsOfTheProfile(): void
    {
        $this->setUpTestCase();
        $formUrl = $this->getProfileEditFormUrl();
        $cache = $this->cachePublicViews();

        $this->submitProfileForm($formUrl, ['website' => 'https://submitted.example.org']);

        $this->assertSame(
            'https://submitted.example.org',
            $this->getConnectionPool()
                ->getConnectionForTable('tx_academicpersons_domain_model_profile')
                ->executeQuery(
                    'SELECT website FROM tx_academicpersons_domain_model_profile WHERE uid = ?',
                    [self::PROFILE_ID],
                )
                ->fetchOne(),
            'The profile form was not saved.',
        );
        $this->assertPublicViewsOfTheProfileWereFlushed($cache);
    }

    /**
     * Removing the image writes no profile field through Extbase, only the file reference,
     * so this is the path that resolves the profile from the reference.
     */
    #[Test]
    public function removingTheProfileImageFlushesTheCachedPublicViewsOfTheProfile(): void
    {
        $this->setUpTestCase();
        $this->seedProfileImage();
        $removeLink = $this->extractActionLink($this->getProfileShowPage(), 'removeImage');
        $cache = $this->cachePublicViews();

        $this->requestAsFrontendUser(new InternalRequest($removeLink));

        $this->assertSame([], $this->getStoredFiles(), 'The profile image was not removed.');
        $this->assertPublicViewsOfTheProfileWereFlushed($cache);
    }
}
