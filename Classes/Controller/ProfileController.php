<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicBase\Extbase\Property\TypeConverter\FileUploadConverter;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
            'genderOptions' => $this->getAvailableGenderSelectItems(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('profile')->validations,
        ]);
        return $this->htmlResponse();
    }

    public function updateAction(
        Profile $profile,
        ProfileFormData $profileFormData
    ): ResponseInterface {
        $this->profileRepository->update(
            $this->profileFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('profile'),
                $profile,
                $profileFormData,
            ),
        );

        $this->addTranslatedSuccessMessage('profiles.update.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['profile' => $profile]);
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
        $profileUid = 0;
        $body = $this->request->getParsedBody();
        if (is_array($body)) {
            $profileUid = (int)($body['tx_academicpersonsedit_profileediting']['profile']['__identity'] ?? 0);
        }
        GeneralUtility::makeInstance(FileUploadConverter::class)
            ->setArgumentTypeConverterConfiguration(
                $this->arguments,
                'profile',
                'image',
                [
                    FileUploadConverter::CONFIGURATION_UPLOAD_FOLDER => $this->settings['editForm']['profileImage']['targetFolder'] ?? null,
                    FileUploadConverter::CONFIGURATION_VALIDATION_FILESIZE_MAXIMUM =>  $this->settings['editForm']['profileImage']['validation']['maxFileSize'] ?? null,
                    FileUploadConverter::CONFIGURATION_VALIDATION_MIME_TYPE_ALLOWED_MIME_TYPES => $this->settings['editForm']['profileImage']['validation']['allowedMimeTypes'] ?? null,
                    FileUploadConverter::CONFIGURATION_TARGET_FILE_NAME_WITHOUT_EXTENSION => $this->buildProfileImageNameWithoutExtension($profileUid),
                ]
            );
    }

    public function addImageAction(Profile $profile): ResponseInterface
    {
        $this->profileRepository->update($profile);
        $this->persistenceManager->persistAll();
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    public function removeImageAction(Profile $profile): ResponseInterface
    {
        $image = $profile->getImage();
        if ($image !== null) {
            $imageFile = $image->getOriginalResource()->getOriginalFile();
            $imageFile->getStorage()->deleteFile($imageFile);
        }
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
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

    /**
     * @return array<int<0, max>, array{
     *      label: string,
     *      labelTranslationIdentifier: string,
     *      value: string,
     *  }>
     * @todo Evaluating TCA in frontend for available options is a hard task to do correctly requiring to execute
     *       TCA item proc functions and so on. It also does not account for eventually FormEngine nodes processing
     *       additional stuff. Current implementation takes only directly added TCA items into account to show them
     *       as valid select options.
     * @todo Use TcaSchema for TYPO3 v13, either as dual version OR when dropping TYPO3 v12 support.
     */
    private function getAvailableGenderSelectItems(): array
    {
        $items = [];
        foreach ($GLOBALS['TCA']['tx_academicpersons_domain_model_profile']['columns']['gender']['config']['items'] ?? [] as $item) {
            $itemValue = (string)($item['value'] ?? '');
            if ($itemValue === '') {
                // Skip empty string values, handled with `<f:form.select prependOptionLabel="---" />`
                // in the fluid template.
                continue;
            }
            $labelIdentifier = (string)($item['label'] ?? '');
            $items[] = [
                'label' => ($this->localizationUtility->translate(
                    $labelIdentifier,
                    'persons_edit',
                ) ?? $labelIdentifier) ?: $labelIdentifier,
                'labelTranslationIdentifier' => $labelIdentifier,
                'value' => $itemValue,
            ];
        }
        return $items;
    }
}
