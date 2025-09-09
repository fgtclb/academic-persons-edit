<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\FunctionTypeRepository;
use FGTCLB\AcademicPersons\Domain\Repository\LocationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\OrganisationalUnitRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class ContractController extends AbstractActionController
{
    public function __construct(
        private readonly ContractFactory $contractFactory,
        private readonly ContractRepository $contractRepository,
        private readonly FunctionTypeRepository $functionTypeRepository,
        private readonly OrganisationalUnitRepository $organisationalUnitRepository,
        private readonly LocationRepository $locationRepository,
    ) {}

    // =================================================================================================================
    // Handle readonly display like list forms and detail view
    // =================================================================================================================

    public function listAction(Profile $profile): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
        ]);
        return $this->htmlResponse();
    }

    public function showAction(Contract $contract): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'contract' => $contract,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Profile $profile, ?ContractFormData $contractFormData = null): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
            'contractFormData' => $contractFormData ?? new ContractFormData(),
            'functionTypes' => $this->functionTypeRepository->findAll(),
            'organisationalUnits' => $this->organisationalUnitRepository->findAll(),
            'locations' => $this->locationRepository->findAll(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('contract')->validations,
        ]);
        return $this->htmlResponse();
    }

    public function createAction(Profile $profile, ContractFormData $contractFormData): ResponseInterface
    {
        $contract = $this->contractFactory->createFromFormData(
            $this->academicPersonsSettings->getValidationSetWithFallback('contract'),
            $profile,
            $contractFormData,
        );
        $maxSortingValue = 0;
        foreach ($profile->getContracts() as $existingContract) {
            $maxSortingValue = max($maxSortingValue, $existingContract->getSorting());
        }
        // Use next available sorting value
        $maxSortingValue += 1;
        $contract->setSorting($maxSortingValue);
        $this->contractRepository->add($contract);
        $this->persistenceManager->persistAll();

        $this->addTranslatedSuccessMessage('contracts.success.create.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['contract' => $contract]);
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(Contract $contract): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'contractFormData' => ContractFormData::createFromContract($contract),
            'functionTypes' => $this->functionTypeRepository->findAll(),
            'organisationalUnits' => $this->organisationalUnitRepository->findAll(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('contract')->validations,
        ]);
        return $this->htmlResponse();
    }

    public function updateAction(Contract $contract, ContractFormData $contractFormData): ResponseInterface
    {
        $this->contractRepository->update(
            $this->contractFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('contract'),
                $contract,
                $contractFormData,
            ),
        );

        $this->addTranslatedSuccessMessage('contracts.success.update.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['contract' => $contract]);
    }

    public function sortAction(Contract $contract, string $sortDirection): ResponseInterface
    {
        $profile = $contract->getProfile();
        if ($profile === null) {
            // @todo Needs to be handled properly.
            throw new \RuntimeException(
                'Contract does not have a profile.',
                1752936133,
            );
        }

        if (!in_array($sortDirection, ['up', 'down'])
            || $profile->getContracts()->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('contracts.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        // Convert contracts to array
        $contractsArray = [];
        foreach ($profile->getContracts() as $profileContract) {
            $contractsArray[$profileContract->getUid()] = $profileContract;
        }

        // Revert array, if sort direction is down
        if ($sortDirection === 'down') {
            $contractsArray = array_reverse($contractsArray, true);
        }

        // Switch sorting values
        $prevContract = null;
        foreach ($contractsArray as $currentContract) {
            if ($contract != $currentContract) {
                $prevContract = $currentContract;
            } else {
                // Only switch sorting if the selected contract is not the first one in the array
                // (normally the sorting options for this case should be hidden in the Fluid template)
                if ($prevContract !== null) {
                    $prevSorting = $prevContract->getSorting();
                    $prevContract->setSorting($currentContract->getSorting());
                    $currentContract->setSorting($prevSorting);

                    $this->contractRepository->update($prevContract);
                    $this->contractRepository->update($currentContract);

                    $this->persistenceManager->persistAll();
                    $this->addTranslatedSuccessMessage('contracts.sort.success.done');
                }
                break;
            }
        }

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }

    // =================================================================================================================
    // Handle destructive actions like deleting records
    // =================================================================================================================

    public function confirmDeleteAction(Contract $contract): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'contract' => $contract,
        ]);
        return $this->htmlResponse();
    }

    public function deleteAction(Contract $contract): ResponseInterface
    {
        $this->contractRepository->remove($contract);
        $this->addTranslatedSuccessMessage('contracts.success.delete.done');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }
}
