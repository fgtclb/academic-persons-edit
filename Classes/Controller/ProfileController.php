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
use Fgtclb\AcademicPersons\Types\EmailAddressTypes;
use Fgtclb\AcademicPersons\Types\PhoneNumberTypes;
use Fgtclb\AcademicPersons\Types\PhysicalAddressTypes;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\AddressRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\LocationRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\ProfileRepository;
use Fgtclb\AcademicPersonsEdit\Event\AddProfileInformationEvent;
use Fgtclb\AcademicPersonsEdit\Event\AfterProfileUpdateEvent;
use Fgtclb\AcademicPersonsEdit\Event\RemoveProfileInformationEvent;
use Fgtclb\AcademicPersonsEdit\Exception\AccessDeniedException;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileTranslator;
use Fgtclb\AcademicPersonsEdit\Property\TypeConverter\ProfileImageUploadConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class ProfileController extends ActionController
{
    public const FLASH_MESSAGE_QUEUE_IDENTIFIER = 'academic_profile';

    public function __construct(
        private readonly Context $context,
        private readonly ProfileRepository $profileRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly AddressRepository $addressRepository,
        private readonly ProfileTranslator $profileTranslator,
        private readonly LocationRepository $locationRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function initializeAction(): void
    {
        if ($this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false) === false) {
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'Authentication needed'
                ),
                1744109477
            );
        }
    }

    public function showProfileSwitchAction(): ResponseInterface
    {
        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);

        // TODO: Don't return empty response if no profiles are assigned to user
        if (empty($profileUids)) {
            return $this->htmlResponse();
        }

        $profiles = $this->profileRepository->findByUids($profileUids);
        $this->view->assignMultiple([
            'profiles' => $profiles,
            'activeProfileUid' => $this->context->getPropertyFromAspect('frontend.profile', 'activeProfileUid', 0),
        ]);

        return $this->htmlResponse();
    }

    /**
     * @Validate(param="profileUid", validator="NumberRangeValidator", options={"minimum": 1})
     */
    public function executeProfileSwitchAction(int $profileUid): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess($profileUid);
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        /** @var AbstractUserAuthentication $frontendUser */
        $frontendUser = $this->getTypo3Request()->getAttribute('frontend.user');
        $frontendUser->setAndSaveSessionData('academic-active-profile-uid', $profileUid);

        return $this->redirectToProfileEditResponse();
    }

    /**
     * @IgnoreValidation("profile")
     */
    public function showProfileEditingFormAction(?Profile $profile = null): ResponseInterface
    {
        if ($profile === null) {
            $activeProfileUid = (int)$this->context->getPropertyFromAspect('frontend.profile', 'activeProfileUid', []);
            /** @var \Fgtclb\AcademicPersons\Domain\Model\Profile|null $profile */
            $profile = $this->profileRepository->findByUid($activeProfileUid);
        }

        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);

        // TODO: To die() is no good way out here, talk to your trusted TYPO3 developer first
        if ($profile === null || !in_array($profile->getUid(), $profileUids)) {
            $this->logger->error(
                'showProfileEditingFormAction has been called without a valid profile.',
                [
                    'uid' => $profile?->getUid(),
                    'allProfileUids' => $profileUids,
                ]
            );
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744109477
            );
        }

        $availableAddresses = [];
        foreach ($profile->getContracts() as $contract) {
            $availableAddresses[$contract->getUid()] = $this->addressRepository->getAddressFromOrganisation(
                $contract->getEmployeeType(),
                $contract->getOrganisationalUnit()
            )->toArray();
        }

        $currentLanguageUid = $this->context->getPropertyFromAspect('language', 'contentId');

        $this->view->assignMultiple([
            'profile' => $profile,
            'currentLanguageUid' => $currentLanguageUid,
            'translationAllowed' => $this->profileTranslator->isTranslationAllowed($currentLanguageUid),
            'availableAddresses' => $availableAddresses,
            'addressTypes' => GeneralUtility::makeInstance(PhysicalAddressTypes::class)->getAll(),
            'emailAddressTypes' => GeneralUtility::makeInstance(EmailAddressTypes::class)->getAll(),
            'phoneNumberTypes' => GeneralUtility::makeInstance(PhoneNumberTypes::class)->getAll(),
            'maxFileUploadsInBytes' =>  GeneralUtility::getBytesFromSizeMeasurement(
                $this->settings['editForm']['profileImage']['validation']['maxFileSize'] ?? ''
            ),
            'availableLocations' => $this->locationRepository->findAll(),
        ]);

        return $this->htmlResponse();
    }

    public function initializeSaveProfileAction(): void
    {
        $targetFolderIdentifier = $this->settings['editForm']['profileImage']['targetFolder'] ?? null;
        $maxFilesize = $this->settings['editForm']['profileImage']['validation']['maxFileSize'] ?? '0kb';
        $allowedImeTypes = $this->settings['editForm']['profileImage']['validation']['allowedMimeTypes'] ?? '';
        $profileImageTypeConverter = GeneralUtility::makeInstance(ProfileImageUploadConverter::class);

        $profileUid = 0;
        $body = $this->request->getParsedBody();
        if (is_array($body)) {
            $profileUid = (int)($body['tx_academicpersonsedit_profileediting']['profile']['__identity'] ?? 0);
        }
        $targetFileName = $this->buildProfileImageNameWithoutExtension($profileUid);

        $this->arguments
            ->getArgument('profile')
            ->getPropertyMappingConfiguration()
            ->forProperty('image')
            ->setTypeConverter($profileImageTypeConverter)
            ->setTypeConverterOptions(
                ProfileImageUploadConverter::class,
                [
                    ProfileImageUploadConverter::CONFIGURATION_TARGET_DIRECTORY_COMBINED_IDENTIFIER => $targetFolderIdentifier,
                    ProfileImageUploadConverter::CONFIGURATION_MAX_UPLOAD_SIZE => $maxFilesize,
                    ProfileImageUploadConverter::CONFIGURATION_ALLOWED_MIME_TYPES => $allowedImeTypes,
                    ProfileImageUploadConverter::CONFIGURATION_TARGET_FILE_NAME_WITHOUT_EXTENSION => $targetFileName,
                ]
            );
    }

    public function saveProfileAction(Profile $profile): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187065
            );
        }

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

    /**
     * @IgnoreValidation("profile")
     */
    public function removeImageAction(Profile $profile): ResponseInterface
    {
        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187076
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187090
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187102
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187112
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744109477
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187172
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187176
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187180
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187186
            );
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
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'No profile assigned to user.'
                ),
                1744187195
            );
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
