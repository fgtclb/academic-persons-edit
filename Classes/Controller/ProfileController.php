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
use Fgtclb\AcademicPersons\Types\EmailAddressTypes;
use Fgtclb\AcademicPersons\Types\PhoneNumberTypes;
use Fgtclb\AcademicPersons\Types\PhysicalAddressTypes;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\AddressRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\LocationRepository;
use Fgtclb\AcademicPersonsEdit\Domain\Repository\ProfileRepository;
use Fgtclb\AcademicPersonsEdit\Event\AfterProfileUpdateEvent;
use Fgtclb\AcademicPersonsEdit\Exception\AccessDeniedException;
use Fgtclb\AcademicPersonsEdit\Profile\ProfileTranslator;
use Fgtclb\AcademicPersonsEdit\Property\TypeConverter\ProfileImageUploadConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
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

    private Context $context;

    private ProfileRepository $profileRepository;

    private PersistenceManagerInterface $persistenceManager;

    private AddressRepository $addressRepository;

    private ProfileTranslator $profileTranslator;

    private LocationRepository $locationRepository;

    public function __construct(
        Context $context,
        ProfileRepository $profileRepository,
        PersistenceManagerInterface $persistenceManager,
        AddressRepository $addressRepository,
        ProfileTranslator $profileTranslator,
        LocationRepository $locationRepository
    ) {
        $this->context = $context;
        $this->profileRepository = $profileRepository;
        $this->persistenceManager = $persistenceManager;
        $this->addressRepository = $addressRepository;
        $this->profileTranslator = $profileTranslator;
        $this->locationRepository = $locationRepository;
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

    public function showProfileSwitchAction(): ResponseInterface
    {
        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);

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
    public function showProfileEditingFormAction(Profile $profile = null): ResponseInterface
    {
        if ($profile === null) {
            $activeProfileUid = (int)$this->context->getPropertyFromAspect('frontend.profile', 'activeProfileUid', []);
            /** @var \Fgtclb\AcademicPersons\Domain\Model\Profile|null $profile */
            $profile = $this->profileRepository->findByUid($activeProfileUid);
        }

        $profileUids = $this->context->getPropertyFromAspect('frontend.profile', 'allProfileUids', []);
        if ($profile === null || !in_array($profile->getUid(), $profileUids)) {
            GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                $this->request,
                'No profile assigned to user'
            );
            die();
        }

        try {
            $this->checkProfileEditAccess((int)$profile->getUid());
        } catch (AccessDeniedException) {
            return new Response(null, 403);
        }

        $availableAddresses = [];
        foreach ($profile->getContracts() as $contract) {
            $availableAddresses[$contract->getUid()] = $this->addressRepository->getAddressFromOrganisation(
                $contract->getEmployeeType(),
                $contract->getOrganisationalLevel1(),
                $contract->getOrganisationalLevel2(),
                $contract->getOrganisationalLevel3()
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
            return new Response(null, 403);
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
