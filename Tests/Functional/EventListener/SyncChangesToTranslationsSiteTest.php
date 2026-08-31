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
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Characterisation of the site determination of {@see SyncChangesToTranslations}
 * (ACE-105 preparation): a profile on a pid that no site configuration covers makes
 * the listener return silently - no exception, no synchronisation. Together with the
 * listener's own `@todo` this pins the behaviour ACE-485 has to preserve or
 * deliberately change when the site becomes part of the event.
 *
 * The counterpart - the `SiteFinder::getSiteByPageId()` fallback *finding* a site
 * when `$GLOBALS['TYPO3_REQUEST']` is not set - is pinned by
 * {@see SyncChangesToTranslationsSyncTest}, which runs the full happy path without a
 * request object.
 *
 * The recording synchronizer double exists for the same reason as in
 * {@see SyncChangesToTranslationsSkipGuardsTest}: the listener returns before
 * creating a {@see SynchronizerContext}, so "skipped" is observable as
 * "synchronize() never called" (and `ProfileTranslator` stays untouched).
 */
final class SyncChangesToTranslationsSiteTest extends AbstractAcademicPersonsEditTestCase
{
    #[Test]
    public function eventForProfileOnPidWithoutSiteConfigurationIsSilentlySkipped(): void
    {
        // The fixture pages exist, but no site configuration is written in this test
        // class, so `SiteFinder::getSiteByPageId(2)` throws `SiteNotFoundException`
        // internally - which the listener swallows.
        $this->importCSVDataSet(__DIR__ . '/Fixtures/defaultLanguageProfile.csv');
        $recordingSynchronizer = new class () implements RecordSynchronizerInterface {
            /** @var list<SynchronizerContext> */
            public array $contexts = [];

            public function synchronize(SynchronizerContext $context): void
            {
                $this->contexts[] = $context;
            }
        };
        $listener = new SyncChangesToTranslations(
            profileTranslator: $this->get(ProfileTranslator::class),
            siteFinder: $this->get(SiteFinder::class),
            recordSyncronizer: $recordingSynchronizer,
        );

        unset($GLOBALS['TYPO3_REQUEST']);
        $profile = $this->get(ProfileRepository::class)->findByUid(1);
        $this->assertInstanceOf(Profile::class, $profile);

        $listener(new AfterProfileUpdateEvent($profile));

        $this->assertSame([], $recordingSynchronizer->contexts);
    }
}
