<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\EventListener;

use FGTCLB\AcademicPersons\Domain\Model\Dto\Syncronizer\SynchronizerContext;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
use FGTCLB\AcademicPersons\Service\RecordSynchronizerInterface;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Synchronises the translations of an announced default-language profile.
 *
 * The site is the one the event carries. Only a dispatcher that passes none - the
 * commands, and code written for an earlier version - leaves it to the lookup of
 * this listener, which still prefers the site of the global request. A DataHandler
 * save passes none only when the profile's page belongs to no site, and is not
 * synchronised then: the site of a backend request is the one of the page selected
 * in the page tree, not the profile's.
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
        $site = $event->getSite();
        if ($site === null && !in_array($event->getOrigin(), [ProfileUpdateOrigin::Backend, ProfileUpdateOrigin::Import], true)) {
            $site = $this->getSite($profile->getPid());
        }
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
     * The fallback for an event without a site.
     *
     * @param int<0, max> $pid
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
