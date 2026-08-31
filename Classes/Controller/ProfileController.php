<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\FileUploadConfiguration;
use TYPO3\CMS\Extbase\Validation\Validator\FileSizeValidator;
use TYPO3\CMS\Extbase\Validation\Validator\MimeTypeValidator;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class ProfileController extends AbstractActionController
{
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly ProfileFormDataFactoryInterface $profileFormDataFactory,
        private readonly ResourceFactory $resourceFactory,
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
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profiles' => $profiles,
        ]);

        return $this->htmlResponse();
    }

    public function showAction(Profile $profile): ResponseInterface
    {
        $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
        $cancelUrl = $this->uriBuilder->reset()->uriFor(
            'list',
        );
        $this->userSessionService->saveRefererToSession($this->request);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'profileFormData' => $this->profileFormDataFactory->createFromProfile($pluginControllerActionContext, $profile),
            'cancelUrl' => $cancelUrl,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(Profile $profile): ResponseInterface
    {
        $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'profileFormData' => $this->profileFormDataFactory->createFromProfile($pluginControllerActionContext, $profile),
            'genderOptions' => $this->getAvailableGenderSelectItems(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('profile')->validations,
        ]);
        return $this->htmlResponse();
    }

    public function initializeUpdateAction(): void
    {
        $this->addArgumentValidator('profileFormData', ProfileFormDataValidator::class);
    }

    public function updateAction(Profile $profile, ProfileFormData $profileFormData): ResponseInterface
    {
        $this->profileRepository->update(
            $this->profileFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('profile'),
                $profile,
                $profileFormData,
            ),
        );
        $this->persistAndDispatchProfileUpdate($profile);

        $this->addTranslatedSuccessMessage('profile.update.success');

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
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function initializeAddImageAction(): void
    {
        $this->configureImageFileUpload();
    }

    public function addImageAction(Profile $profile): ResponseInterface
    {
        // The file handling service already stored the uploaded file and rewired the profile
        // image property to it, so the replaced file can only be determined from the state
        // still persisted at this point - which the update below overwrites.
        $replacedImageFile = $this->getPersistedProfileImageFile($profile);

        $this->profileRepository->update($profile);
        $this->persistAndDispatchProfileUpdate($profile);

        $this->deleteReplacedProfileImageFile($replacedImageFile, $profile);

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    public function removeImageAction(Profile $profile): ResponseInterface
    {
        $image = $profile->getImage();
        if ($image !== null) {
            $imageFile = $image->getOriginalResource()->getOriginalFile();
            // The relation is dropped first, for two reasons: deleting the file alone leaves
            // the reference count on the profile record pointing at a reference that no longer
            // exists, and the file can only be checked for other usages once this profile does
            // not reference it any more.
            $profile->setImage(null);
            $this->profileRepository->update($profile);
            $this->persistenceManager->remove($image);
            $this->persistAndDispatchProfileUpdate($profile);

            if ($this->countFileReferences($imageFile) === 0) {
                $imageFile->getStorage()->deleteFile($imageFile);
            }
        }
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    public function toggleSkipSyncAction(Profile $profile): ResponseInterface
    {
        $profile->setSkipSync(!$profile->getSkipSync());
        $this->profileRepository->update($profile);
        // `skip_sync` gates the fe_users to profile data synchronisation, not the
        // translation synchronisation - toggling it is a profile change like any other.
        $this->persistAndDispatchProfileUpdate($profile);
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    /**
     * Configures the native Extbase file upload handling for the profile image.
     *
     * The configuration is built here instead of using the `#[FileUpload]` attribute, because
     * upload folder and both validation limits are integrator configuration read from TypoScript
     * at runtime, which a static attribute cannot provide - and the attribute would have to be
     * placed on the `Profile` persistence model of `EXT:academic_persons`.
     */
    private function configureImageFileUpload(): void
    {
        $profileArgument = $this->arguments->getArgument('profile');

        $fileUploadConfiguration = (new FileUploadConfiguration('image'))
            // The profile holds a single image, but the limit is validated against the already
            // referenced file plus the upload. Allowing two therefore means "replace", which is
            // what this form does - the file handling service repoints the existing reference to
            // the uploaded file, and `addImageAction()` cleans the replaced file up afterwards.
            // Registering a file deletion instead would delete the replaced file unconditionally,
            // even when another record still references it.
            ->setMaxFiles(2)
            ->setUploadFolder(
                (string)($this->settings['editForm']['profileImage']['targetFolder'] ?? '1:/user_upload/')
            );

        $fileSizeValidator = GeneralUtility::makeInstance(FileSizeValidator::class);
        $fileSizeValidator->setOptions([
            'maximum' => (string)($this->settings['editForm']['profileImage']['validation']['maxFileSize'] ?? PHP_INT_MAX . 'B'),
        ]);
        $fileUploadConfiguration->addValidator($fileSizeValidator);

        // An empty list means "no mime type restriction". `MimeTypeValidator` throws
        // for an empty `allowedMimeTypes` option, so it is only added when configured.
        $allowedMimeTypes = GeneralUtility::trimExplode(
            ',',
            (string)($this->settings['editForm']['profileImage']['validation']['allowedMimeTypes'] ?? ''),
            true
        );
        if ($allowedMimeTypes !== []) {
            $mimeTypeValidator = GeneralUtility::makeInstance(MimeTypeValidator::class);
            $mimeTypeValidator->setOptions(['allowedMimeTypes' => $allowedMimeTypes]);
            $fileUploadConfiguration->addValidator($mimeTypeValidator);
        }

        $profileArgument->getFileHandlingServiceConfiguration()
            ->addFileUploadConfiguration($fileUploadConfiguration);
        // The upload is handled by the file handling service, not by the property mapper.
        $profileArgument->getPropertyMappingConfiguration()->skipProperties('image');
    }

    /**
     * Returns the file currently referenced as profile image according to the database.
     *
     * Reading the persisted state instead of the mapped object is intentional: the in-memory
     * profile already carries the newly uploaded file when an upload action is processed.
     */
    private function getPersistedProfileImageFile(Profile $profile): ?File
    {
        $profileUid = $profile->getUid();
        if ($profileUid === null) {
            return null;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $fileUid = (int)$queryBuilder
            ->select('uid_local')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'tablenames',
                    $queryBuilder->createNamedParameter('tx_academicpersons_domain_model_profile')
                ),
                $queryBuilder->expr()->eq(
                    'fieldname',
                    $queryBuilder->createNamedParameter('image')
                ),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($profileUid, Connection::PARAM_INT)
                ),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        if ($fileUid <= 0) {
            return null;
        }

        try {
            return $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return null;
        }
    }

    /**
     * Removes the file a profile image upload replaced.
     *
     * The native file upload handling generates the stored file name and therefore always adds
     * a new file instead of overwriting the previous one, so it has to be cleaned up explicitly
     * to avoid orphaned files piling up in the upload folder with every re-upload.
     */
    private function deleteReplacedProfileImageFile(?File $replacedImageFile, Profile $profile): void
    {
        if ($replacedImageFile === null) {
            return;
        }
        $currentImageFile = $profile->getImage()?->getOriginalResource()->getOriginalFile();
        if ($currentImageFile !== null && $currentImageFile->getUid() === $replacedImageFile->getUid()) {
            // The upload did not result in a new file, nothing was replaced.
            return;
        }
        if ($this->countFileReferences($replacedImageFile) > 0) {
            // Still referenced elsewhere, for example by a content element or another record.
            return;
        }
        $replacedImageFile->getStorage()->deleteFile($replacedImageFile);
    }

    private function countFileReferences(File $file): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return (int)$queryBuilder
            ->count('uid')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($file->getUid(), Connection::PARAM_INT)
                ),
            )
            ->executeQuery()
            ->fetchOne();
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
