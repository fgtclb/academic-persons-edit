<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Profile;

use Fgtclb\AcademicPersonsEdit\Domain\Model\FrontendUser;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;
use Fgtclb\AcademicPersonsEdit\Event\AfterProfileUpdateEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

abstract class AbstractProfileFactory implements ProfileFactoryInterface
{
    protected PersistenceManagerInterface $persistenceManager;

    protected ExtensionConfiguration $extensionConfiguration;

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        ExtensionConfiguration $extensionConfiguration,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->persistenceManager = $persistenceManager;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function shouldCreateProfileForUser(FrontendUserAuthentication $frontendUserAuthentication): bool
    {
        $userAspect = new UserAspect($frontendUserAuthentication);

        try {
            $configuration = $this->extensionConfiguration->get('academic_persons_edit');
            $shouldCreateProfile = (bool)($configuration['profile']['autoCreateProfiles'] ?? false);
            $userGroupListToCreateProfileFor = (string)($configuration['profile']['createProfileForUserGroups'] ?? '');
        } catch (ExtensionConfigurationExtensionNotConfiguredException) {
            $shouldCreateProfile = false;
            $userGroupListToCreateProfileFor = '';
        } catch (ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }

        if (empty($userGroupListToCreateProfileFor)) {
            return $shouldCreateProfile;
        }

        $userGroupId = $userAspect->getGroupIds();
        $userGroupIdsToCreateProfileFor = GeneralUtility::intExplode(',', $userGroupListToCreateProfileFor);
        $userIsInUserGroup = count(array_intersect($userGroupId, $userGroupIdsToCreateProfileFor)) > 0;

        return $shouldCreateProfile && $userIsInUserGroup;
    }

    public function createProfileForUser(FrontendUserAuthentication $frontendUserAuthentication): ?int
    {
        /** @var array<string, int|string|null>|null $userData */
        $userData = $frontendUserAuthentication->user;
        if ($userData === null) {
            return null;
        }

        /** @var FrontendUser|null $frontendUser */
        $frontendUser = $this->persistenceManager->getObjectByIdentifier($userData['uid'], FrontendUser::class);
        if (!$frontendUser instanceof FrontendUser) {
            return null;
        }

        $profileForDefaultLanguage = $this->createProfileFromFrontendUser($userData);
        $profileForDefaultLanguage->setPid((int)$userData['pid']);
        $profileForDefaultLanguage->getFrontendUsers()->attach($frontendUser);
        $this->persistenceManager->add($profileForDefaultLanguage);

        $this->persistenceManager->persistAll();

        $afterProfileUpdatedEvent = new AfterProfileUpdateEvent($profileForDefaultLanguage);
        $this->eventDispatcher->dispatch($afterProfileUpdatedEvent);

        return $profileForDefaultLanguage->getUid();
    }

    /**
     * @param array<string, int|string|null> $frontendUserData
     */
    abstract protected function createProfileFromFrontendUser(array $frontendUserData): Profile;
}
