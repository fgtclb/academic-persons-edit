<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\EventListener;

use FGTCLB\AcademicPersons\Domain\Model\Dto\Syncronizer\SynchronizerContext;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Service\RecordSynchronizerInterface;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * @todo This event reacts on an PSR-14 event dispatched in FE, BE and CLI(BE) context AND relies on a global
 *       request object ($GLOBALS['TYPO3_REQUEST']) providing attribute `site` and the expectation limits it
 *       to a frontend request. Overall this is a bad design and fails, because the PSR-14 event will also
 *       dispatched in CLI context (cli command) AND eventually in BE context when project using DataHandler
 *       hooks dispatching that event again. That means, the whole working chain with the event, this listener
 *       needs to be made context unaware and hard global expectations on request must fall.
 */
final class SyncChangesToTranslations
{
    public function __construct(
        private readonly ProfileTranslator $profileTranslator,
        private readonly SiteFinder $siteFinder,
        private readonly RecordSynchronizerInterface $recordSyncronizer,
    ) {}

    public function __invoke(AfterProfileUpdateEvent $event): void
    {
        $profile = $event->getProfile();
        if ($profile->getUid() === null) {
            // Not persisted or invalid profile. Skip.
            return;
        }
        if ($profile->getPid() === null) {
            // Invalid profile pid. Skip.
            return;
        }
        if ($profile->getIsTranslation() === true) {
            // Already n translation sync mode. Skip.
            return;
        }
        // @todo Site should be part of the event, determine it dynamically for now.
        $site = $this->getSite($profile->getPid());
        if ($site === null) {
            // No site found, nothing to do.
            return;
        }

        $context = SynchronizerContext::create(
            recordSyncronizer: $this->recordSyncronizer,
            site: $site,
            allowedLanguageIds: $this->profileTranslator->getAllowedLanguageIds(),
            tableName: 'tx_academicpersons_domain_model_profile',
            uid: $profile->getUid(),
        );
        $this->recordSyncronizer->synchronize($context);
    }

    /**
     * @param int<0, max> $pid
     * @todo The site object should be passed when dispatching {@see AfterProfileUpdateEvent} as part of the event,
     *       so listener do not have the need to determine it on their own.
     */
    private function getSite(int $pid): ?Site
    {
        // First, try to get Site from global request
        $site = ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('site');
        // Second, take NullSite as not set, which indicates backend usage without a selected page in the page tree,
        // and may be wrong anyway.
        $site = $site instanceof NullSite ? null : $site;
        // No site yet, get the related site config for `$pid`.
        try {
            $site ??= $this->siteFinder->getSiteByPageId($pid);
        } catch (PageNotFoundException|SiteNotFoundException) {
            // Site could not determined.
            $site = null;
        }
        return $site;
    }
}
