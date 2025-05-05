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
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Repository\PhoneNumberRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class PhoneNumberController extends AbstractActionController
{
    public function __construct(
        private readonly PhoneNumberFactory $phoneNumberFactory,
        private readonly PhoneNumberRepository $phoneNumberRepository,
    ) {}

    // =================================================================================================================
    // Handle readonly display like list forms and detail view
    // =================================================================================================================

    public function listAction(Contract $contract): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'phoneNumbers' => $contract->getPhoneNumbers(),
        ]);

        return $this->htmlResponse();
    }

    public function showAction(PhoneNumber $phoneNumber): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'profile' => $phoneNumber->getContract()->getProfile(),
            'contract' => $phoneNumber->getContract(),
            'phoneNumber' => $phoneNumber,
        ]);

        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Contract $contract, ?PhoneNumberFormData $phoneNumberFormData = null): ResponseInterface
    {
        $this->view->assignMultiple([
            'profile' => $contract->getProfile(),
            'contract' => $contract,
            'phoneNumberFormData' => $phoneNumberFormData ?? new PhoneNumberFormData(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->settingsRegistry->getValidationsForFrontend('phoneNumber'),
        ]);

        return $this->htmlResponse();
    }

    public function createAction(Contract $contract, PhoneNumberFormData $phoneNumberFormData): ResponseInterface
    {
        $phoneNumber = $this->phoneNumberFactory->createFromFormData(
            $contract,
            $phoneNumberFormData,
        );
        $this->phoneNumberRepository->add($phoneNumber);
        $this->persistenceManager->persistAll();

        $this->addTranslatedSuccessMessage('phoneNumber.success.create.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['phoneNumber' => $phoneNumber]);
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(PhoneNumber $phoneNumber): ResponseInterface
    {
        $this->view->assignMultiple([
            'profile' => $phoneNumber->getContract()->getProfile(),
            'contract' => $phoneNumber->getContract(),
            'phoneNumber' => $phoneNumber,
            'phoneNumberFormData' => PhoneNumberFormData::createFromPhoneNumber($phoneNumber),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->settingsRegistry->getValidationsForFrontend('phoneNumber'),
        ]);

        return $this->htmlResponse();
    }

    public function updateAction(
        PhoneNumber $phoneNumber,
        PhoneNumberFormData $phoneNumberFormData
    ): ResponseInterface {
        $this->phoneNumberRepository->update(
            $this->phoneNumberFactory->updateFromFormData($phoneNumber, $phoneNumberFormData),
        );

        $this->addTranslatedSuccessMessage('phoneNumber.success.update.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        return (new ForwardResponse('edit'))->withArguments(['phoneNumber' => $phoneNumber]);
    }

    public function sortAction(PhoneNumber $phoneNumberFromForm, string $sortDirection): ResponseInterface
    {
        $contract = $phoneNumberFromForm->getContract();

        if (!in_array($sortDirection, ['up', 'down'])
            || $contract->getPhoneNumbers()->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('phoneNumber.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
        }

        // Convert contracts to array
        $phoneNumberArray = [];
        foreach ($contract->getPhoneNumbers() as $phoneNumber) {
            $phoneNumberArray[] = $phoneNumber;
        }

        // Revert array, if sort direction is down
        if ($sortDirection === 'down') {
            $phoneNumberArray = array_reverse($phoneNumberArray);
        }

        // Switch sorting values
        $prevPhoneNumber = null;
        foreach ($phoneNumberArray as $currentPhoneNumber) {
            if ($phoneNumberFromForm != $currentPhoneNumber) {
                $prevPhoneNumber = $currentPhoneNumber;
            } else {
                // Only switch sorting if the selected contract is not the first one in the array
                // (normally the sorting options for this case should be hidden in the Fluid template)
                if ($prevPhoneNumber !== null) {
                    $prevSorting = $prevPhoneNumber->getSorting();
                    $prevPhoneNumber->setSorting($currentPhoneNumber->getSorting());
                    $currentPhoneNumber->setSorting($prevSorting);

                    $this->phoneNumberRepository->update($prevPhoneNumber);
                    $this->phoneNumberRepository->update($currentPhoneNumber);

                    $this->persistenceManager->persistAll();
                    $this->addTranslatedSuccessMessage('phoneNumber.sort.success.done');
                }
                break;
            }
        }

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }

    // =================================================================================================================
    // Handle destructive actions like deleting records
    // =================================================================================================================

    public function confirmDeleteAction(PhoneNumber $phoneNumber): ResponseInterface
    {
        $this->view->assignMultiple([
            'profile' => $phoneNumber->getContract()->getProfile(),
            'contract' => $phoneNumber->getContract(),
            'phoneNumber' => $phoneNumber,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);

        return $this->htmlResponse();
    }

    public function deleteAction(PhoneNumber $phoneNumber): ResponseInterface
    {
        $this->phoneNumberRepository->remove($phoneNumber);
        $this->addTranslatedSuccessMessage('phoneNumber.success.delete.done');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request));
    }
}
