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
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
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
            'profile' => $profileInformation->getProfile(),
            'type' => $profileInformation->getType(),
            'profileInformation' => $profileInformation,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Profile $profile, string $type, ?ProfileInformationFormData $profileInformationFormData = null): ResponseInterface
    {
        $mappedType = $this->academicPersonsSettings->getProfileInformationType($type)?->type ?? '';
        $profileInformationFormData ??= ProfileInformationFormData::createEmptyForType($mappedType);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
            'type' => $type,
            'profileInformationFormData' => $profileInformationFormData,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('profileInformation')->validations,
        ]);

        return $this->htmlResponse();
    }

    public function createAction(Profile $profile, ProfileInformationFormData $profileInformationFormData): ResponseInterface
    {
        $profileInformation = $this->profileInformationFactory->createFromFormData(
            $this->academicPersonsSettings->getValidationSetWithFallback('profileInformation'),
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
        $this->persistenceManager->persistAll();

        $this->addTranslatedSuccessMessage('profileInformation.success.create.done');

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
            'profile' => $profileInformation->getProfile(),
            'profileInformation' => $profileInformation,
            'profileInformationFormData' => ProfileInformationFormData::createFromProfileInformation($profileInformation),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('profileInformation')->validations,
        ]);
        return $this->htmlResponse();
    }

    public function updateAction(
        ProfileInformation $profileInformation,
        ProfileInformationFormData $profileInformationFormData,
    ): ResponseInterface {
        $this->profileInformationRepository->update(
            $this->profileInformationFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('profileInformation'),
                $profileInformation,
                $profileInformationFormData,
            ),
        );

        $this->addTranslatedSuccessMessage('profileInformation.success.update.done');

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
        if ($profile === null) {
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

        if (!in_array($sortDirection, ['up', 'down'])
            || $sortingItems->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('profileInformations.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }

        // Convert profile informations to array
        $sortingItemsArray = [];
        foreach ($sortingItems as $item) {
            $sortingItemsArray[] = $item;
        }

        // Revert array, if sort direction is down
        if ($sortDirection === 'down') {
            $sortingItemsArray = array_reverse($sortingItemsArray);
        }

        // Switch sorting values
        $prevItem = null;
        foreach ($sortingItemsArray as $currentItem) {
            if ($profileInformation != $currentItem) {
                $prevItem = $currentItem;
            } else {
                // Only switch sorting if the selected contract is not the first one in the array
                // (normally the sorting options for this case should be hidden in the Fluid template)
                if ($prevItem !== null) {
                    $prevSorting = $prevItem->getSorting();
                    $prevItem->setSorting($currentItem->getSorting());
                    $currentItem->setSorting($prevSorting);

                    $this->profileInformationRepository->update($prevItem);
                    $this->profileInformationRepository->update($currentItem);

                    $this->persistenceManager->persistAll();
                    $this->addTranslatedSuccessMessage('contracts.sort.success.done');
                }
                break;
            }
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
            'profile' => $profileInformation->getProfile(),
            'profileInformation' => $profileInformation,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function deleteAction(ProfileInformation $profileInformation): ResponseInterface
    {
        $this->profileInformationRepository->remove($profileInformation);
        $this->addTranslatedSuccessMessage('profileInformation.success.delete.done');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }
}
