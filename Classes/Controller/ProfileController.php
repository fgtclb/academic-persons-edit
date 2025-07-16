<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Property\TypeConverter\ProfileImageUploadConverter;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class ProfileController extends AbstractActionController
{
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
    ) {}

    // =================================================================================================================
    // Handle readonly display like list forms and detail view
    // =================================================================================================================

    public function listAction(): ResponseInterface
    {
        $profiles = $this->profileRepository->findByFrontendUser(
            $this->context->getPropertyFromAspect('frontend.user', 'id')
        );

        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profiles' => $profiles,
        ]);

        return $this->htmlResponse();
    }

    public function showAction(Profile $profile): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
        ]);

        return $this->htmlResponse();
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(Profile $profile): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
            'profileFormData' => ProfileFormData::createFromProfile($profile),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->settingsRegistry->getValidationsForFrontend('profile'),
        ]);

        return $this->htmlResponse();
    }

    public function updateAction(
        Profile $profile,
        ProfileFormData $profileFormData
    ): ResponseInterface {
        $this->profileRepository->update(
            $this->profileFactory->updateFromFormData($profile, $profileFormData)
        );

        $this->addTranslatedSuccessMessage('profiles.update.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['profile' => $profile]);
    }

    // =================================================================================================================
    //  Handle entity translation
    // =================================================================================================================

    /*
    public function translateAction(int $profileUid, int $languageUid): ResponseInterface
    {
        $this->profileTranslator->translateTo($profileUid, $languageUid);

        return $this->redirectToProfileEditResponse();
    }
    */

    // =================================================================================================================
    //  Handle entity image operations
    // =================================================================================================================

    public function editImageAction(Profile $profile): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);

        return $this->htmlResponse();
    }

    public function initializeAddImageAction(): void
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

    public function addImageAction(Profile $profile): ResponseInterface
    {
        $this->profileRepository->update($profile);
        $this->persistenceManager->persistAll();

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }

    public function removeImageAction(Profile $profile): ResponseInterface
    {
        $image = $profile->getImage();
        if ($image !== null) {
            $imageFile = $image->getOriginalResource()->getOriginalFile();
            $imageFile->getStorage()->deleteFile($imageFile);
        }

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
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
