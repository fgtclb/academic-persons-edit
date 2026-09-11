<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Profile\AbstractProfileFactory;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * ACE-610: the profile the `academic:profiles:create` command announces is the object
 * {@see AbstractProfileFactory::createProfileForUser()} just built in PHP and persisted -
 * it never went through the Extbase `DataMapper`, so its `_localizedUid` is still `null`
 * while `uid` is set. {@see SyncChangesToTranslations} gates on
 * `Profile::getIsTranslation()`, which read that state as "is a translation" and returned
 * early, so a created profile was never translated no matter how `profile.allowedLanguages`
 * was configured.
 *
 * The profile is built and persisted the way the factory does it rather than by running the
 * command, so the test pins the listener against that object state and nothing else.
 * {@see SyncChangesToTranslationsSyncTest} covers the same listener for a profile read back
 * through the repository.
 */
final class SyncChangesToTranslationsCreatedProfileTest extends AbstractAcademicPersonsEditTestCase
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/emptyStorage.csv');
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
    public function listenerCreatesTranslatedProfileRowForAJustCreatedProfile(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $profile = new Profile();
        $profile->setPid(2);
        $profile
            ->setFirstName('Scrooge')
            ->setLastName('Duck');
        $persistenceManager = $this->get(PersistenceManagerInterface::class);
        $persistenceManager->add($profile);
        $persistenceManager->persistAll();
        $this->assertNotNull($profile->getUid());

        $this->get(SyncChangesToTranslations::class)(new AfterProfileUpdateEvent($profile));

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery('SELECT * FROM tx_academicpersons_domain_model_profile ORDER BY uid')
            ->fetchAllAssociative();
        $this->assertCount(2, $rows, 'The listener creates exactly one translated profile row.');
        $translatedRow = $rows[1];
        $this->assertSame(1, (int)$translatedRow['sys_language_uid']);
        $this->assertSame($profile->getUid(), (int)$translatedRow['l10n_parent']);
        $this->assertSame($profile->getUid(), (int)$translatedRow['l10n_source']);
        $this->assertSame(2, (int)$translatedRow['pid']);
        $this->assertSame('Scrooge', $translatedRow['first_name']);
        $this->assertSame('Duck', $translatedRow['last_name']);
    }
}
