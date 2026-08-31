<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Domain\Model\Dto\Syncronizer\SynchronizerContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Service\RecordSynchronizerInterface;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Characterisation of the skip guards at the top of
 * {@see SyncChangesToTranslations::__invoke()} (ACE-105 preparation): a not yet
 * persisted profile, a profile without a pid and a profile that is itself a
 * translation are all silent no-ops.
 *
 * The guards fire before the listener touches `ProfileTranslator` or determines a
 * site, so a recording {@see RecordSynchronizerInterface} double is the observation
 * point: the listener is rebuilt with it, and "the guard held" means "synchronize()
 * was never called". `ProfileTranslator` itself cannot be doubled - the class is
 * `final` and the constructor of the listener type-hints it - but the real instance
 * from the container works because no guarded path reaches it (which also keeps the
 * process-wide `static` cache in `getAllowedLanguageIds()` untouched, see
 * {@see SyncChangesToTranslationsSyncTest} for why that matters).
 */
final class SyncChangesToTranslationsSkipGuardsTest extends AbstractAcademicPersonsEditTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DE' => ['id' => 1, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
    ];

    /**
     * @var RecordSynchronizerInterface&object{contexts: list<SynchronizerContext>}
     */
    private RecordSynchronizerInterface $recordingSynchronizer;

    protected function setUp(): void
    {
        parent::setUp();
        // A site covering the fixture pages is written on purpose: without one the
        // listener would skip through its site determination as well, and a removed
        // guard would go unnoticed. With the site in place the guard under test is
        // the only thing standing between the event and the synchronizer.
        $this->writeSiteConfiguration(
            identifier: 'test-site',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: 'https://www.acme.com/'),
            languages: [
                $this->buildDefaultLanguageConfiguration(identifier: 'EN', base: '/'),
                $this->buildLanguageConfiguration(identifier: 'DE', base: '/de/'),
            ],
        );
        $this->recordingSynchronizer = new class () implements RecordSynchronizerInterface {
            /** @var list<SynchronizerContext> */
            public array $contexts = [];

            public function synchronize(SynchronizerContext $context): void
            {
                $this->contexts[] = $context;
            }
        };
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->instancePath . '/typo3conf/sites', true);
        parent::tearDown();
    }

    private function createListener(): SyncChangesToTranslations
    {
        return new SyncChangesToTranslations(
            profileTranslator: $this->get(ProfileTranslator::class),
            siteFinder: $this->get(SiteFinder::class),
            recordSyncronizer: $this->recordingSynchronizer,
        );
    }

    /**
     * `AbstractProfileFactory::createProfileForUser()` dispatches the event after
     * `persistAll()`, but nothing stops a project from dispatching it with an unsaved
     * profile - the listener treats a missing uid as "not persisted or invalid" and
     * does nothing.
     */
    #[Test]
    public function eventForNotPersistedProfileIsANoOp(): void
    {
        $profile = new Profile();
        $this->assertNull($profile->getUid());

        $this->createListener()(new AfterProfileUpdateEvent($profile));

        $this->assertSame([], $this->recordingSynchronizer->contexts);
    }

    /**
     * A profile with a uid but no pid cannot be resolved to a site, and the listener
     * skips it before trying. Extbase leaves `pid` null on objects that were never
     * persisted or mapped, so this is the second half of the "invalid profile" guard.
     */
    #[Test]
    public function eventForProfileWithoutPidIsANoOp(): void
    {
        $profile = new Profile();
        $profile->_setProperty('uid', 1);
        $this->assertNull($profile->getPid());

        $this->createListener()(new AfterProfileUpdateEvent($profile));

        $this->assertSame([], $this->recordingSynchronizer->contexts);
    }

    /**
     * A profile fetched through a language overlay reports `getIsTranslation() === true`
     * (Extbase sets `_localizedUid` to the uid of the overlaying translation record, and
     * `Profile::getIsTranslation()` compares it against `uid`). Synchronising such a
     * profile would sync a translation into the translations - the guard stops the loop.
     */
    #[Test]
    public function eventForProfileFetchedAsTranslationOverlayIsANoOp(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/translatedProfile.csv');
        $repository = $this->get(ProfileRepository::class);
        $query = $repository->createQuery();
        $query->getQuerySettings()
            ->setRespectStoragePage(false)
            ->setLanguageAspect(new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON));
        // With `OVERLAYS_ON` Extbase selects the *translated* rows directly (language 1
        // records whose `l10n_parent` points at a default language record) and maps them
        // back to the default record during overlay - so the translation record (uid 2)
        // is the one to address; the mapped object then carries uid 1 / _localizedUid 2.
        $query->matching($query->equals('uid', 2));
        /** @var Profile|null $profile */
        $profile = $query->execute()->getFirst();
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertTrue($profile->getIsTranslation());

        $this->createListener()(new AfterProfileUpdateEvent($profile));

        $this->assertSame([], $this->recordingSynchronizer->contexts);
    }
}
