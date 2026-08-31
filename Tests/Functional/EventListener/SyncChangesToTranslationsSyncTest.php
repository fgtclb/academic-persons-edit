<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Characterisation of the happy path of {@see SyncChangesToTranslations} (ACE-105
 * preparation): with `profile.allowedLanguages = '1'` the listener synchronises the
 * default language profile into site language 1 through the real
 * `RecordSynchronizer`.
 *
 * One coarse assertion on the translated profile row is deliberate - the deep record
 * level behaviour of `RecordSynchronizer` (inline children, `l10n_mode=exclude`
 * columns, update vs. create) is covered by the synchroniser's own tests, not here.
 *
 * No `$GLOBALS['TYPO3_REQUEST']` is set when the listener runs, so this test also
 * pins the `SiteFinder::getSiteByPageId()` fallback: the site is determined from the
 * profile's pid (2, below root page 1), exactly the situation of a CLI invocation of
 * the `academic:profiles:*` commands.
 *
 * The test runs in its own PHP process on purpose:
 * `ProfileTranslator::getAllowedLanguageIds()` caches its result in a method-local
 * `static` variable, so the first call in a PHP process fixes the value for every
 * later call of the whole functional suite run, whatever the instance configuration
 * of the test that makes it. Process isolation is the only way this test and
 * {@see SyncChangesToTranslationsGateTest} can both observe their own extension
 * configuration.
 */
final class SyncChangesToTranslationsSyncTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = [
            'EXTENSIONS' => [
                'academic_persons_edit' => [
                    'profile' => [
                        'allowedLanguages' => '1',
                    ],
                ],
            ],
        ];
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/defaultLanguageProfile.csv');
        $this->writeSiteConfiguration(
            identifier: 'test-site',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: 'https://www.acme.com/'),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
                $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
            ],
        );
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function listenerCreatesTranslatedProfileRowForConfiguredLanguage(): void
    {
        // Guards the precondition this test depends on: this process has not primed the
        // `static` cache in `ProfileTranslator::getAllowedLanguageIds()` with another
        // configuration. A failure here means the process isolation is broken, not the
        // listener.
        $this->assertSame([1], $this->get(ProfileTranslator::class)->getAllowedLanguageIds());

        unset($GLOBALS['TYPO3_REQUEST']);
        $profile = $this->get(ProfileRepository::class)->findByUid(1);
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertFalse($profile->getIsTranslation());

        $this->get(SyncChangesToTranslations::class)(new AfterProfileUpdateEvent($profile));

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery('SELECT * FROM tx_academicpersons_domain_model_profile ORDER BY uid')
            ->fetchAllAssociative();
        $this->assertCount(2, $rows, 'The listener creates exactly one translated profile row.');
        $translatedRow = $rows[1];
        $this->assertSame(1, (int)$translatedRow['sys_language_uid']);
        $this->assertSame(1, (int)$translatedRow['l10n_parent']);
        $this->assertSame(1, (int)$translatedRow['l10n_source']);
        $this->assertSame(2, (int)$translatedRow['pid']);
        $this->assertSame('Scrooge', $translatedRow['first_name']);
        $this->assertSame('Duck', $translatedRow['last_name']);
    }
}
