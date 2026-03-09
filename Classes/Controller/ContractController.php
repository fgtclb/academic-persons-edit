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
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ContractFormDataValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Extbase\Annotation\Validate;

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
        $cancelUrl = $this->uriBuilder->reset()->uriFor(
            'show',
            ['profile' => $contract->getProfile()],
            'Profile'
        );

        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'cancelUrl' => $cancelUrl,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Profile $profile): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $profile,
            'contractFormData' => new ContractFormData(),
            'functionTypes' => $this->functionTypeRepository->findAll(),
            'organisationalUnits' => $this->organisationalUnitRepository->findAll(),
            'locations' => $this->locationRepository->findAll(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('contract')->validations,
        ]);
        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'contractFormData',
        'validator' => ContractFormDataValidator::class,
    ])]
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

        $this->addTranslatedSuccessMessage('contract.create.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['contract' => $contract]);
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
            'locations' => $this->locationRepository->findAll(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('contract')->validations,
        ]);
        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'contractFormData',
        'validator' => ContractFormDataValidator::class,
    ])]
    public function updateAction(Contract $contract, ContractFormData $contractFormData): ResponseInterface
    {
        $this->contractRepository->update(
            $this->contractFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('contract'),
                $contract,
                $contractFormData,
            ),
        );

        $this->addTranslatedSuccessMessage('contract.update.success');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }
        return $this->createFormPersistencePrgRedirect('edit', ['contract' => $contract]);
    }

    public function sortAction(Contract $contract, string $sortDirection): ResponseInterface
    {
        $profile = $contract->getProfile();
        if ($profile === null || $contract->getUid() <= 0) {
            // @todo Needs to be handled properly.
            throw new \RuntimeException(
                'Contract does not have a profile.',
                1752936133,
            );
        }
        $sortMode = ListSortingMode::tryFromDefault($sortDirection);
        if ($sortMode === ListSortingMode::NONE
            || $profile->getContracts()->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('contracts.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        $process = $this->sortItems($profile->getContracts()->toArray(), $contract->getUid(), $sortMode);
        if ($process->changed) {
            $this->addTranslatedSuccessMessage('contract.sort.success');
        }
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
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
        $this->addTranslatedSuccessMessage('contract.delete.success');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }
}
