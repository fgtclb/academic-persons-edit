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
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileInformationFormDataValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class ProfileInformationController extends AbstractActionController
{
    public function __construct(
        private readonly ProfileInformationFactory $profileInformationFactory,
        private readonly ProfileInformationRepository $profileInformationRepository,
        private readonly string $profileInformationFormDataClassName = ProfileInformationFormData::class,
    ) {}

    // =================================================================================================================
    // Handle readonly display like list forms and detail view
    // =================================================================================================================

    /**
     * @param non-empty-string $type
     */
    public function listAction(Profile $profile, string $type): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'type' => $type,
            'profileInformations' => $profile->_getProperty($type),
        ]);
        return $this->htmlResponse();
    }

    public function showAction(ProfileInformation $profileInformation): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profileInformation->getProfile(),
            'type' => $profileInformation->getType(),
            'profileInformation' => $profileInformation,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Profile $profile, string $type): ResponseInterface
    {
        $mappedType = $this->academicPersonsSettings->getDocumentSection($type)?->type ?? '';
        $profileInformationFormData = $this->profileInformationFormDataClassName::createEmptyForType($mappedType);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'type' => $type,
            'profileInformationFormData' => $profileInformationFormData,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getDocumentValidationSetByType($mappedType)->validations,
        ]);

        return $this->htmlResponse();
    }

    public function initializeCreateAction(): void
    {
        $this->addArgumentValidator('profileInformationFormData', ProfileInformationFormDataValidator::class);
    }

    public function initializeUpdateAction(): void
    {
        $this->addArgumentValidator('profileInformationFormData', ProfileInformationFormDataValidator::class);
    }

    public function createAction(Profile $profile, ProfileInformationFormData $profileInformationFormData): ResponseInterface
    {
        $profileInformation = $this->profileInformationFactory->createFromFormData(
            $this->academicPersonsSettings->getDocumentValidationSetByType($profileInformationFormData->getType()),
            $profile,
            $profileInformationFormData,
        );

        $sortingItems = $this->profileInformationRepository->findByProfileAndType(
            $profile,
            $profileInformation->getType()
        );
        $maxSortingValue = 0;
        foreach ($sortingItems as $existingProfileInformation) {
            $maxSortingValue = max($maxSortingValue, $existingProfileInformation->getSorting());
        }
        // Use next available sorting value
        $maxSortingValue += 1;
        $profileInformation->setSorting($maxSortingValue);
        $profileInformation->setPid((int)$profile->getPid());
        $this->profileInformationRepository->add($profileInformation);
        $this->persistAndDispatchProfileUpdate($profile);

        $this->addTranslatedSuccessMessage($profileInformation->getType() . '.create.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['profileInformation' => $profileInformation]);
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(ProfileInformation $profileInformation): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profileInformation->getProfile(),
            'profileInformation' => $profileInformation,
            'profileInformationFormData' => $this->profileInformationFormDataClassName::createFromProfileInformation($profileInformation),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getDocumentValidationSetByType($profileInformation->getType())->validations,
        ]);
        return $this->htmlResponse();
    }

    public function updateAction(
        ProfileInformation $profileInformation,
        ProfileInformationFormData $profileInformationFormData,
    ): ResponseInterface {
        $this->profileInformationRepository->update(
            $this->profileInformationFactory->updateFromFormData(
                $this->academicPersonsSettings->getDocumentValidationSetByType($profileInformation->getType()),
                $profileInformation,
                $profileInformationFormData,
            ),
        );
        $this->persistAndDispatchProfileUpdate($profileInformation->getProfile());

        $this->addTranslatedSuccessMessage($profileInformation->getType() . '.update.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['profileInformation' => $profileInformation]);
    }

    /**
     * @todo Implement type aware sorting and activate sorting options in template.
     */
    public function sortAction(ProfileInformation $profileInformation, string $sortDirection): ResponseInterface
    {
        $profile = $profileInformation->getProfile();
        if ($profile === null || $profileInformation->getUid() <= 0) {
            // @todo Needs to be handled properly.
            throw new \RuntimeException(
                'Could not get contract.',
                1752938963,
            );
        }
        $sortingItems = $this->profileInformationRepository->findByProfileAndType(
            $profile,
            $profileInformation->getType()
        );
        $sortMode = ListSortingMode::tryFromDefault($sortDirection);
        if ($sortMode === ListSortingMode::NONE
            || $sortingItems->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('profileInformations.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        $process = $this->sortItems($sortingItems->toArray(), $profileInformation->getUid(), $sortMode);
        if ($process->changed) {
            $this->persistAndDispatchProfileUpdate($profile);
            $this->addTranslatedSuccessMessage($profileInformation->getType() . '.sort.success');
        }
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    // =================================================================================================================
    // Handle destructive actions like deleting records
    // =================================================================================================================

    public function confirmDeleteAction(ProfileInformation $profileInformation): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profileInformation->getProfile(),
            'profileInformation' => $profileInformation,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function deleteAction(ProfileInformation $profileInformation): ResponseInterface
    {
        // Resolved before the removal - the persisted aggregate the listeners see is the
        // one without the record, but the relation is only navigable on the live object.
        $profile = $profileInformation->getProfile();
        $this->profileInformationRepository->remove($profileInformation);
        $this->persistAndDispatchProfileUpdate($profile);
        $this->addTranslatedSuccessMessage($profileInformation->getType() . '.delete.success');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }
}
