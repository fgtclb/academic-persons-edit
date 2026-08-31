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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Characterisation of the extension configuration gate of
 * {@see SyncChangesToTranslations} (ACE-105 preparation): with the shipped default
 * (`profile.allowedLanguages` empty, synchronised from `ext_conf_template.txt` in
 * `setUp()`), invoking the listener for a persisted default language profile creates
 * no translated rows - `ProfileTranslator::getAllowedLanguageIds()` yields `[]`, the
 * resulting `SynchronizerContext` carries no target languages, and
 * `RecordSynchronizer::synchronize()` finds nothing to loop over. The full real
 * service chain runs; the observation is the database.
 *
 * The test runs in its own PHP process on purpose:
 * `ProfileTranslator::getAllowedLanguageIds()` caches its result in a method-local
 * `static` variable, so the first call in a PHP process fixes the value for every
 * later call of the whole functional suite run, whatever the instance configuration
 * of the test that makes it. Process isolation is the only way this test and
 * {@see SyncChangesToTranslationsSyncTest} can both observe their own extension
 * configuration.
 */
final class SyncChangesToTranslationsGateTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->synchronizeExtConfTemplateWithLocalConfigurationOfAllExtensions();
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
    public function listenerCreatesNoTranslationsWithShippedDefaultConfiguration(): void
    {
        $this->assertSame([], $this->get(ProfileTranslator::class)->getAllowedLanguageIds());

        unset($GLOBALS['TYPO3_REQUEST']);
        $profile = $this->get(ProfileRepository::class)->findByUid(1);
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertFalse($profile->getIsTranslation());

        $this->get(SyncChangesToTranslations::class)(new AfterProfileUpdateEvent($profile));

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->select(['uid', 'sys_language_uid'], 'tx_academicpersons_domain_model_profile')
            ->fetchAllAssociative();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int)$rows[0]['sys_language_uid']);
    }
}
