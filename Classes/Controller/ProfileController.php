<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Controller;

use Fgtclb\AcademicPersons\Domain\Model\Address;
use Fgtclb\AcademicPersons\Domain\Model\Contract;
use Fgtclb\AcademicPersons\Domain\Model\Email;
use Fgtclb\AcademicPersons\Domain\Model\PhoneNumber;
use Fgtclb\AcademicPersons\Domain\Model\ProfileInformation;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\FunctionTypeRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\LocationRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\OrganisationalUnitRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\ProfileRepository;
use Fgtclb\AcademicPersonsEdit\Event\AddProfileInformationEvent;
use Fgtclb\AcademicPersonsEdit\Event\AfterProfileUpdateEvent;
use Fgtclb\AcademicPersonsEdit\Event\RemoveProfileInformationEvent;
use Fgtclb\AcademicPersonsEdit\Exception\AccessDeniedException;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileTranslator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class ProfileController extends ActionController
{
    public const FLASH_MESSAGE_QUEUE_IDENTIFIER = 'academic_profile';

    private Context $context;

    private PersistenceManagerInterface $persistenceManager;

    private ProfileRepository $profileRepository;

    private ProfileTranslator $profileTranslator;

    private FunctionTypeRepository $functionTypeRepository;

    private LocationRepository $locationRepository;

    private OrganisationalUnitRepository $organisationalUnitRepository;

    public function __construct(
        Context $context,
        ProfileRepository $profileRepository,
        PersistenceManagerInterface $persistenceManager,
        ProfileTranslator $profileTranslator,
        FunctionTypeRepository $functionTypeRepository,
        LocationRepository $locationRepository,
        OrganisationalUnitRepository $organisationalUnitRepository
    ) {
        $this->context = $context;
        $this->profileRepository = $profileRepository;
        $this->persistenceManager = $persistenceManager;
        $this->profileTranslator = $profileTranslator;
        $this->functionTypeRepository = $functionTypeRepository;
        $this->locationRepository = $locationRepository;
        $this->organisationalUnitRepository = $organisationalUnitRepository;
    }

    public function initializeAction(): void
    {
        if ($this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false) === false) {
            GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                $this->request,
                'Authentication needed'
            );
        }
    }

    public function listProfilesAction(): ResponseInterface
    {
        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);
        $profiles = $this->profileRepository->findByUids($profileUids);

        $this->view->assignMultiple([
            'profiles' => $profiles,
        ]);

        return $this->htmlResponse();
    }

    public function editProfileAction(?Profile $profile = null): ResponseInterface
    {
        if ($profile === null) {
            return new Response(null, 404);
        }

        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $this->view->assignMultiple([
            'profile' => $profile,
        ]);

        return $this->htmlResponse();
    }

    public function showContractAction(?Contract $contract = null): ResponseInterface
    {
        if ($contract === null) {
            return new Response(null, 404);
        }

        $profile = $contract->getProfile();
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $this->view->assignMultiple([
            'contract' => $contract,
        ]);

        return $this->htmlResponse();
    }

    public function editContractAction(?Contract $contract = null): ResponseInterface
    {
        if ($contract === null) {
            return new Response(null, 404);
        }

        $profile = $contract->getProfile();
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $organisationalUnits = $this->organisationalUnitRepository->findAll();
        \TYPO3\CMS\Core\Utility\DebugUtility::debug($organisationalUnits);

        $this->view->assignMultiple([
            'contract' => $contract,
            'locations' => $this->locationRepository->findAll(),
            //'organisationalUnits' => $this->organisationalUnitRepository->findAll(),
            'functionTypes' => $this->functionTypeRepository->findAll(),
        ]);

        return $this->htmlResponse();
    }

    /*
        $profile->setFirstNameAlpha(strtolower(substr($profile->getFirstName(), 0, 1)));
        $profile->setLastNameAlpha(strtolower(substr($profile->getLastName(), 0, 1)));

        $this->profileRepository->update($profile);
        $this->persistenceManager->persistAll();

        $successMessageTitle = LocalizationUtility::translate('flash_message.profile_updated.title', 'AcademicPersonsEdit');
        $successMessageBody = LocalizationUtility::translate('flash_message.profile_updated.message', 'AcademicPersonsEdit');
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            (string)$successMessageBody,
            (string)$successMessageTitle,
            AbstractMessage::OK,
            true
        );
        $this->getFlashMessageQueue(self::FLASH_MESSAGE_QUEUE_IDENTIFIER)->addMessage($flashMessage);

        $afterProfileUpdatedEvent = new AfterProfileUpdateEvent($profile);
        $this->eventDispatcher->dispatch($afterProfileUpdatedEvent);

        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->flushCachesByTags([
            'profile_list_view',
            sprintf('profile_detail_view_%d', $profile->getUid()),
        ]);

        return $this->redirectToProfileEditResponse();
    }
        */

    /**
     * @IgnoreValidation("profile")
     */
    public function removeImageAction(Profile $profile): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $image = $profile->getImage();
        if ($image !== null) {
            $imageFile = $image->getOriginalResource()->getOriginalFile();
            $imageFile->getStorage()->deleteFile($imageFile);
        }

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("contract")
     * @IgnoreValidation("profile")
     */
    public function addPhysicalAddressAction(Profile $profile, Contract $contract): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $address = GeneralUtility::makeInstance(Address::class);
        $contract->getPhysicalAddresses()->attach($address);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("contract")
     * @IgnoreValidation("address")
     * @IgnoreValidation("profile")
     */
    public function removePhysicalAddressAction(Profile $profile, Contract $contract, Address $address): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $contract->getPhysicalAddresses()->detach($address);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     * @IgnoreValidation("contract")
     */
    public function addEmailAddressAction(Profile $profile, Contract $contract): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $emailAddress = GeneralUtility::makeInstance(Email::class);
        $contract->getEmailAddresses()->attach($emailAddress);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     * @IgnoreValidation("contract")
     * @IgnoreValidation("emailAddress")
     */
    public function removeEmailAddressAction(Profile $profile, Contract $contract, Email $emailAddress): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $contract->getEmailAddresses()->detach($emailAddress);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     * @IgnoreValidation("contract")
     */
    public function addPhoneNumberAction(Profile $profile, Contract $contract): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $phoneNumber = GeneralUtility::makeInstance(PhoneNumber::class);
        $contract->getPhoneNumbers()->attach($phoneNumber);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     * @IgnoreValidation("contract")
     * @IgnoreValidation("phoneNumber")
     */
    public function removePhoneNumberAction(Profile $profile, Contract $contract, PhoneNumber $phoneNumber): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $contract->getPhoneNumbers()->detach($phoneNumber);

        $this->persistenceManager->update($contract);

        return $this->redirectToProfileEditResponse();
    }

    public function translateAction(int $profileUid, int $languageUid): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess($profileUid);
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $this->profileTranslator->translateTo($profileUid, $languageUid);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     */
    public function addProfileInformationAction(Profile $profile, string $type): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $profileInformation = GeneralUtility::makeInstance(ProfileInformation::class);
        $profileInformation->setType($type);

        switch ($type) {
            case 'scientific_research':
                $profile->getScientificResearch()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'curriculum_vitae':
                $profile->getVita()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'membership':
                $profile->getMemberships()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'cooperation':
                $profile->getCooperation()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'publication':
                $profile->getPublications()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'lecture':
                $profile->getLectures()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'press_media':
                $profile->getPressMedia()->attach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            default:
                $addProfileInformationEvent = new AddProfileInformationEvent($profile, $profileInformation);
                $this->eventDispatcher->dispatch($addProfileInformationEvent);
        }

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     * @IgnoreValidation("profileInformation")
     */
    public function removeProfileInformationAction(Profile $profile, ProfileInformation $profileInformation): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        switch ($profileInformation->getType()) {
            case 'scientific_research':
                $profile->getScientificResearch()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'curriculum_vitae':
                $profile->getVita()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'membership':
                $profile->getMemberships()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'cooperation':
                $profile->getCooperation()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'publication':
                $profile->getPublications()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'lecture':
                $profile->getLectures()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            case 'press_media':
                $profile->getPressMedia()->detach($profileInformation);
                $this->persistenceManager->update($profile);
                break;
            default:
                $removeProfileInformationEvent = new RemoveProfileInformationEvent($profile, $profileInformation);
                $this->eventDispatcher->dispatch($removeProfileInformationEvent);
        }

        return $this->redirectToProfileEditResponse();
    }

    private function redirectToProfileEditResponse(): ResponseInterface
    {
        $redirectUri = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->build();

        return new RedirectResponse($redirectUri, 303);
    }

    private function getTypo3Request(): ServerRequestInterface
    {
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            throw new \RuntimeException('Missing key "TYPO3_REQUEST" in $GLOBALS.', 1689923296);
        }
        return $GLOBALS['TYPO3_REQUEST'];
    }

    /**
     * @throws AccessDeniedException
     */
    private function checkProfileEditAccess(int $profileUid): void
    {
        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);

        if (!in_array($profileUid, $profileUids, true)) {
            throw new AccessDeniedException('User not allowed to edit this profile.', 1695046903);
        }
    }

    private function buildProfileImageNameWithoutExtension(int $profileUid): string
    {
        /** @var Profile|null $profile */
        $profile = $this->profileRepository->findByUid($profileUid);
        if ($profile === null) {
            return '';
        }

        return sprintf(
            '%s-%s-%d',
            $profile->getFirstName(),
            $profile->getLastName(),
            $profileUid
        );
    }
}
