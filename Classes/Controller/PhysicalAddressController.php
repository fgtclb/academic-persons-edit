<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Repository\AddressRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\AddressFormDataValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class PhysicalAddressController extends AbstractActionController
{
    public function __construct(
        private readonly AddressFactory $addressFactory,
        private readonly AddressRepository $addressRepository,
    ) {}

    // =================================================================================================================
    // Handle readonly display like list forms and detail view
    // =================================================================================================================

    public function listAction(Contract $contract): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'physicalAddresses' => $contract->getPhysicalAddresses(),
        ]);

        return $this->htmlResponse();
    }

    public function showAction(Address $physicalAddress): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $physicalAddress->getContract()?->getProfile(),
            'contract' => $physicalAddress->getContract(),
            'physicalAddress' => $physicalAddress,
        ]);

        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Contract $contract, ?AddressFormData $addressFormData = null): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'addressFormData' => $addressFormData ?? new AddressFormData(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->settingsRegistry->getValidationsForFrontend('physicalAddress'),
        ]);

        return $this->htmlResponse();
    }

    public function createAction(Contract $contract, AddressFormData $addressFormData): ResponseInterface
    {
        $physicalAddress = $this->addressFactory->createFromFormData(
            $contract,
            $addressFormData
        );
        $this->addressRepository->add($physicalAddress);
        $this->persistenceManager->persistAll();

        $this->addTranslatedSuccessMessage('physicalAddresses.success.create.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['physicalAddress' => $physicalAddress]);
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(Address $physicalAddress): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $physicalAddress->getContract()?->getProfile(),
            'contract' => $physicalAddress->getContract(),
            'physicalAddress' => $physicalAddress,
            'addressFormData' => AddressFormData::createFromAddress($physicalAddress),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->settingsRegistry->getValidationsForFrontend('physicalAddress'),
        ]);

        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'addressFormData',
        'validator' => AddressFormDataValidator::class,
    ])]
    public function updateAction(
        Address $physicalAddress,
        AddressFormData $addressFormData
    ): ResponseInterface {
        $this->addressRepository->update(
            $this->addressFactory->updateFromFormData($physicalAddress, $addressFormData)
        );

        $this->addTranslatedSuccessMessage('physicalAddress.success.update.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['physicalAddress' => $physicalAddress]);
    }

    public function sortAction(Address $physicalAddressFromForm, string $sortDirection): ResponseInterface
    {
        $contract = $physicalAddressFromForm->getContract();
        if ($contract === null) {
            // @todo Needs to be handled properly.
            throw new \RuntimeException(
                'Could not get contract.',
                1752938846,
            );
        }

        if (!in_array($sortDirection, ['up', 'down'])
            || $contract->getPhysicalAddresses()->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('contracts.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        // Convert contracts to array
        $addressArray = [];
        foreach ($contract->getPhysicalAddresses() as $address) {
            $addressArray[] = $address;
        }

        // Revert array, if sort direction is down
        if ($sortDirection === 'down') {
            $addressArray = array_reverse($addressArray);
        }

        // Switch sorting values
        $prevAddress = null;
        foreach ($addressArray as $currentAddress) {
            if ($physicalAddressFromForm != $currentAddress) {
                $prevAddress = $currentAddress;
            } else {
                // Only switch sorting if the selected contract is not the first one in the array
                // (normally the sorting options for this case should be hidden in the Fluid template)
                if ($prevAddress !== null) {
                    $prevSorting = $prevAddress->getSorting();
                    $prevAddress->setSorting($currentAddress->getSorting());
                    $currentAddress->setSorting($prevSorting);

                    $this->addressRepository->update($prevAddress);
                    $this->addressRepository->update($currentAddress);

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

    public function confirmDeleteAction(Address $physicalAddress): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $physicalAddress->getContract()?->getProfile(),
            'contract' => $physicalAddress->getContract(),
            'physicalAddress' => $physicalAddress,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);

        return $this->htmlResponse();
    }

    public function deleteAction(Address $physicalAddress): ResponseInterface
    {
        $this->addressRepository->remove($physicalAddress);

        $this->addTranslatedSuccessMessage('physicalAddress.success.delete.done');

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }
}
