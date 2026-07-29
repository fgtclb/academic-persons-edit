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
use FGTCLB\AcademicBase\Extbase\Property\TypeConverter\FileUploadConverter;
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
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\Validate;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class ProfileController extends AbstractActionController
{
    public function __construct(
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly ProfileFormDataFactoryInterface $profileFormDataFactory,
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
        $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
        $cancelUrl = $this->uriBuilder->reset()->uriFor(
            'list',
        );
        $this->userSessionService->saveRefererToSession($this->request);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
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
            'profile' => $profile,
            'profileFormData' => $this->profileFormDataFactory->createFromProfile($pluginControllerActionContext, $profile),
            'genderOptions' => $this->getAvailableGenderSelectItems(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('profile')->validations,
        ]);
        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'profileFormData',
        'validator' => ProfileFormDataValidator::class,
    ])]
    public function updateAction(Profile $profile, ProfileFormData $profileFormData): ResponseInterface
    {
        $this->profileRepository->update(
            $this->profileFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('profile'),
                $profile,
                $profileFormData,
            ),
        );

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
            // The relation is dropped first, for two reasons: deleting the file alone leaves
            // the reference count on the profile record pointing at a reference that no longer
            // exists, and the file can only be checked for other usages once this profile does
            // not reference it any more.
            $this->persistenceManager->remove($image);
            $this->persistenceManager->persistAll();
            $this->resetProfileImageReferenceCount($profile);

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
        $this->persistenceManager->persistAll();
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
     * Writes the reference count of the removed profile image back to the profile record.
     *
     * This deliberately does not go through `$profile->setImage(null)` and the repository:
     * TYPO3 v12 maps a `null` property to SQL `NULL`, while the column is `NOT NULL`, so
     * persisting the model would fail. TYPO3 v13 writes the count instead.
     */
    private function resetProfileImageReferenceCount(Profile $profile): void
    {
        $profileUid = $profile->getUid();
        if ($profileUid === null) {
            return;
        }
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->update(
                'tx_academicpersons_domain_model_profile',
                ['image' => 0],
                ['uid' => $profileUid],
                ['image' => Connection::PARAM_INT, 'uid' => Connection::PARAM_INT],
            );
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
