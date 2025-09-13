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
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Repository\EmailRepository;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\EmailFormDataValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\Validate;

/**
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
final class EmailAddressController extends AbstractActionController
{
    public function __construct(
        private readonly EmailFactory $emailAddressFactory,
        private readonly EmailRepository $emailAddressRepository,
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
            'emailAddresses' => $contract->getEmailAddresses(),
        ]);
        return $this->htmlResponse();
    }

    public function showAction(Email $emailAddress): ResponseInterface
    {
        $this->userSessionService->saveRefererToSession($this->request);

        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $emailAddress->getContract()?->getProfile(),
            'contract' => $emailAddress->getContract(),
            'emailAddress' => $emailAddress,
        ]);
        return $this->htmlResponse();
    }

    // =================================================================================================================
    //  Handle creation of new entity
    // =================================================================================================================

    public function newAction(Contract $contract): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $contract->getProfile(),
            'availableTypes' => $this->getAvailableTypes(),
            'contract' => $contract,
            'emailAddressFormData' => new EmailFormData(),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('emailAddress')->validations,
        ]);
        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'emailAddressFormData',
        'validator' => EmailFormDataValidator::class,
    ])]
    public function createAction(Contract $contract, EmailFormData $emailAddressFormData): ResponseInterface
    {
        $emailAddress = $this->emailAddressFactory->createFromFormData(
            $this->academicPersonsSettings->getValidationSetWithFallback('emailAddress'),
            $contract,
            $emailAddressFormData,
        );
        $maxSortingValue = 0;
        foreach ($contract->getEmailAddresses() as $existingEmailAddress) {
            $maxSortingValue = max($maxSortingValue, $existingEmailAddress->getSorting());
        }
        // Use next available sorting value
        $maxSortingValue += 1;
        $emailAddress->setSorting($maxSortingValue);
        $this->emailAddressRepository->add($emailAddress);
        $this->persistenceManager->persistAll();

        $this->addTranslatedSuccessMessage('emailAddress.success.create.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['emailAddress' => $emailAddress]);
    }

    // =================================================================================================================
    // Handle entity changes like displaying edit form and edit persistence.
    // =================================================================================================================

    public function editAction(Email $emailAddress): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $emailAddress->getContract()?->getProfile(),
            'availableTypes' => $this->getAvailableTypes(),
            'contract' => $emailAddress->getContract(),
            'emailAddress' => $emailAddress,
            'emailAddressFormData' => EmailFormData::createFromEmail($emailAddress),
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
            'validations' => $this->academicPersonsSettings->getValidationSetWithFallback('emailAddress')->validations,
        ]);
        return $this->htmlResponse();
    }

    #[Validate([
        'param' => 'emailAddressFormData',
        'validator' => EmailFormDataValidator::class,
    ])]
    public function updateAction(
        Email $emailAddress,
        EmailFormData $emailAddressFormData
    ): ResponseInterface {
        $this->emailAddressRepository->update(
            $this->emailAddressFactory->updateFromFormData(
                $this->academicPersonsSettings->getValidationSetWithFallback('emailAddress'),
                $emailAddress,
                $emailAddressFormData,
            ),
        );

        $this->addTranslatedSuccessMessage('emailAddress.success.update.done');

        if ($this->request->hasArgument('submit')
            && $this->request->getArgument('submit') === 'save-and-close'
        ) {
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }
        return $this->createFormPersistencePrgRedirect('edit', ['emailAddress' => $emailAddress]);
    }

    public function sortAction(Email $emailAddress, string $sortDirection): ResponseInterface
    {
        $contract = $emailAddress->getContract();
        if ($contract === null) {
            // @todo Needs to be handled properly.
            throw new \RuntimeException(
                'Could not get contract.',
                1752939173,
            );
        }

        if (!in_array($sortDirection, ['up', 'down'])
            || $contract->getEmailAddresses()->count() <= 1
        ) {
            $this->addTranslatedErrorMessage('emailAddress.sort.error.notPossible');
            return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
        }

        // Convert contracts to array
        $emailAddressArray = [];
        foreach ($contract->getEmailAddresses() as $contractEmailAddress) {
            $emailAddressArray[] = $contractEmailAddress;
        }

        // Revert array, if sort direction is down
        if ($sortDirection === 'down') {
            $emailAddressArray = array_reverse($emailAddressArray);
        }

        // Switch sorting values
        $prevEmailAddress = null;
        foreach ($emailAddressArray as $currentEmailAddress) {
            if ($emailAddress != $currentEmailAddress) {
                $prevEmailAddress = $currentEmailAddress;
            } else {
                // Only switch sorting if the selected contract is not the first one in the array
                // (normally the sorting options for this case should be hidden in the Fluid template)
                if ($prevEmailAddress !== null) {
                    $prevSorting = $prevEmailAddress->getSorting();
                    $prevEmailAddress->setSorting($currentEmailAddress->getSorting());
                    $currentEmailAddress->setSorting($prevSorting);

                    $this->emailAddressRepository->update($prevEmailAddress);
                    $this->emailAddressRepository->update($currentEmailAddress);

                    $this->persistenceManager->persistAll();
                    $this->addTranslatedSuccessMessage('emailAddress.sort.success.done');
                }
                break;
            }
        }

        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    // =================================================================================================================
    // Handle destructive actions like deleting records
    // =================================================================================================================

    public function confirmDeleteAction(Email $emailAddress): ResponseInterface
    {
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'profile' => $emailAddress->getContract()?->getProfile(),
            'contract' => $emailAddress->getContract(),
            'emailAddress' => $emailAddress,
            'cancelUrl' => $this->userSessionService->loadRefererFromSession($this->request),
        ]);
        return $this->htmlResponse();
    }

    public function deleteAction(Email $emailAddress): ResponseInterface
    {
        $this->emailAddressRepository->remove($emailAddress);
        $this->addTranslatedSuccessMessage('emailAddress.success.delete.done');
        return new RedirectResponse($this->userSessionService->loadRefererFromSession($this->request), 303);
    }

    /**
     * @return array<int<0, max>, array{
     *      label: string,
     *      value: string,
     *  }>
     * @todo Evaluating TCA in frontend for available options is a hard task to do correctly requiring to execute
     *       TCA item proc functions and so on. It also does not account for eventually FormEngine nodes processing
     *       additional stuff. Current implementation calls the itemProcFunc with a minimal set as context data, but
     *       cannot simulate all the stuff provided by FormEngine.
     * @todo Use TcaSchema for TYPO3 v13, either as dual version OR when dropping TYPO3 v12 support.
     */
    private function getAvailableTypes(): array
    {
        $tableName = 'tx_academicpersons_domain_model_email';
        $fieldName = 'type';
        $items = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['items'] ?? [];
        $itemProcFunc = (string)($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['itemsProcFunc'] ?? '');
        if ($itemProcFunc !== '') {
            $items = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['items'] ?? [];
            $processorParameters = [
                'items' => &$items,
                'config' => $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'],
                'table' => $tableName,
                'field' => $fieldName,
            ];
            GeneralUtility::callUserFunction($itemProcFunc, $processorParameters, $this);
            $items = $processorParameters['items'];
        }
        $returnItems = [];
        foreach ($items as $item) {
            $itemValue = (string)($item['value'] ?? '');
            if ($itemValue === '') {
                // Skip empty string values, handled with `<f:form.select prependOptionLabel="---" />`
                // in the fluid template.
                continue;
            }
            $labelIdentifier = (string)($item['label'] ?? '');
            $returnItems[] = [
                'label' => ($this->localizationUtility->translate(
                    $labelIdentifier,
                    'persons_edit',
                ) ?? $labelIdentifier) ?: $labelIdentifier,
                'value' => $itemValue,
            ];
        }
        return $returnItems;
    }
}
