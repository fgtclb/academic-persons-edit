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
use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactRecords;
use FGTCLB\AcademicPersonsEdit\Service\DocumentRecords;
use FGTCLB\AcademicPersonsEdit\Service\EditorEndpoint;
use FGTCLB\AcademicPersonsEdit\Service\EditorWrites;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\ManagedRecordLocks;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateRequestService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * The JSON endpoints of the profile editor for the addresses, email addresses
 * and phone numbers of a contract: opening, creating, updating, hiding,
 * deleting and sorting one. Addressed with the controller name
 * `ContractContact` through the profile editing plugin.
 *
 * @phpstan-import-type ContractContactRecord from ContractContactRecords
 *
 * @internal This controller is intentionally internal to `EXT:academic_persons_edit`
 *           and is not part of the public API.
 */
final class ContractContactController extends ActionController
{
    public function __construct(
        private readonly ProfileUpdateRequestService $profileUpdateRequestService,
        private readonly AcademicPersonsSettings $academicPersonsSettings,
        private readonly ManagedRecordLocks $managedRecordLocks,
        private readonly DocumentRecords $documentRecords,
        private readonly ContractContactRecords $contractContactRecords,
        private readonly ContractContactFields $contractContactFields,
        private readonly ListSortingService $listSortingService,
        private readonly EditorEndpoint $editorEndpoint,
        private readonly EditorWrites $editorWrites,
    ) {}

    /**
     * Every action of this controller answers as JSON and is only sent by the
     * editor script, so each one demands the header the editor sends, and none
     * goes through the HTML access denied flow of the plugin: a visitor who is
     * not logged in is answered by the action with a JSON 401.
     */
    public function initializeAction(): void
    {
        $this->editorEndpoint->assertRequestWasSentByTheEditor($this->request);
    }

    /**
     * Returns the field schema and current values for one Contract contact record.
     *
     * The Contract is resolved through the editable Profile before a contact is
     * loaded. This prevents record UIDs from another Profile or Contract from
     * being viewed or changed through the endpoint.
     */
    public function contractContactFormAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'mode'],
                ['contract', 'section', 'mode'],
            );
            $mode = $this->editorEndpoint->getRequiredDocumentMode($data);
            $recordUid = $this->editorEndpoint->getOptionalPositiveInteger($data, 'record');
            if (($mode === 'add') !== ($recordUid === null)) {
                $this->editorEndpoint->throwJsonError(
                    'invalid_payload',
                    400,
                    'The contact record does not match the requested mode.',
                );
            }
            $this->assertContractContactActionAllowed($mode);
            $record = $recordUid === null
                ? null
                : $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($recordUid !== null && $record === null) {
                $this->editorEndpoint->throwJsonError('contract_contact_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedContractContactActionAllowed($section, $record, $managedProperties, $mode);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'record' => $record?->getUid(),
                'title' => $this->contractContactFields->getContractContactSingularLabel($this->request, $section),
                'fields' => $this->contractContactFields->getContractContactFieldDefinitions($this->request, $section, $record, $managedProperties),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Loading a Contract contact form failed.', $exception);
        }
    }

    /**
     * Creates an address, email address or phone number below an editable Contract.
     */
    public function createContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'fields'],
                ['contract', 'section', 'fields'],
            );
            $this->assertContractContactActionAllowed('add');
            $fields = $this->editorEndpoint->getSubmittedDocumentFields($data);
            $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                $this->request,
                $section,
                $fields,
                true,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::CreateContractContact,
                $section->identifier,
                fields: array_intersect_key($fields, $normalizedFields),
                contract: $contract,
            );
            if ($replacedFields !== null) {
                $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                    $this->request,
                    $section,
                    $replacedFields,
                    true,
                );
            }
            $record = $this->contractContactRecords->createContractContactRecord($contract, $section, $normalizedFields);
            $record->setSorting($this->contractContactRecords->getNextContractContactSortingValue($contract, $section));
            $record->setPid((int)$contract->getPid());
            $this->contractContactRecords->addContractContactRecord($record);
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->contractContactFields->serializeContractContactItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Creating a Contract contact failed.', $exception);
        }
    }

    /**
     * Updates an existing address, email address or phone number below a Contract.
     */
    public function updateContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'fields'],
                ['contract', 'section', 'record', 'fields'],
            );
            $this->assertContractContactActionAllowed('edit');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('contract_contact_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedContractContactActionAllowed($section, $record, $managedProperties, 'edit');
            $fields = $this->editorEndpoint->getSubmittedDocumentFields($data);
            $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                $this->request,
                $section,
                $fields,
                false,
                $managedProperties,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::UpdateContractContact,
                $section->identifier,
                $record,
                array_intersect_key($fields, $normalizedFields),
                $contract,
            );
            if ($replacedFields !== null) {
                $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                    $this->request,
                    $section,
                    $replacedFields,
                    false,
                    $managedProperties,
                );
            }
            $this->contractContactRecords->updateContractContactRecord($section, $record, $normalizedFields, $managedProperties);
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->contractContactFields->serializeContractContactItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Updating a Contract contact failed.', $exception);
        }
    }

    /**
     * Deletes one address, email address or phone number below a Contract.
     */
    public function deleteContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record'],
                ['contract', 'section', 'record'],
            );
            $this->assertContractContactActionAllowed('delete');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('contract_contact_not_found', 404);
            }
            $this->assertManagedContractContactActionAllowed(
                $section,
                $record,
                $this->managedRecordLocks->getManagedProperties($record),
                'delete',
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::DeleteContractContact,
                $section->identifier,
                $record,
                contract: $contract,
            );
            $this->contractContactRecords->removeContractContactRecord($record);
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'deleted' => $recordUid,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Deleting a Contract contact failed.', $exception);
        }
    }

    /**
     * Shows or hides one address, email address or phone number in the frontend.
     *
     * `hidden` is the TCA `enablecolumns.disabled` column of the three contact
     * tables - the same flag the previous editor toggled for exactly these three
     * record kinds. It is an edit of the record as far as the allow-list of the
     * `contracts` section is concerned, so `edit` governs it; a contract or
     * profile-information row has an action of its own for it, `hide`, in
     * {@see DocumentController::toggleDocumentVisibilityAction()}.
     */
    public function toggleContractContactVisibilityAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'hidden'],
                ['contract', 'section', 'record', 'hidden'],
            );
            $this->assertContractContactActionAllowed('edit');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('contract_contact_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::ToggleContractContactVisibility,
                $section->identifier,
                $record,
                ['hidden' => $hidden],
                $contract,
            );
            $record->setHidden($hidden);
            $this->contractContactRecords->updateContractContactVisibility($record);
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->contractContactFields->serializeContractContactItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Changing the visibility of a Contract contact failed.', $exception);
        }
    }

    /**
     * Moves one Contract contact up or down within its configured section.
     */
    public function sortContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'direction'],
                ['contract', 'section', 'record', 'direction'],
            );
            $this->assertContractContactActionAllowed('sort');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('contract_contact_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::SortContractContact,
                $section->identifier,
                $record,
                ['direction' => $direction],
                $contract,
            );
            $process = $this->listSortingService->sort(
                $this->contractContactRecords->getContractContactRecords($contract, $section),
                $recordUid,
                $direction === 'up' ? ListSortingMode::UP : ListSortingMode::DOWN,
            );
            if ($process->changed) {
                $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'changed' => $process->changed,
                'order' => array_values(array_map(
                    static fn(AbstractEntity $record): int => (int)$record->getUid(),
                    $process->items,
                )),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Sorting Contract contacts failed.', $exception);
        }
    }

    /**
     * Validates the current request and resolves the Profile, Contract and
     * configured contact section.
     *
     * @return array{0: Profile, 1: Contract, 2: ContractContactSection, 3: array<string, mixed>}
     */
    private function getContractContactRequest(): array
    {
        $requestResult = $this->profileUpdateRequestService->validate($this->request);
        if (!$requestResult->isValid()) {
            $this->editorEndpoint->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->editorEndpoint->throwJsonError('internal_server_error', 500);
        }
        $data = $payload->getData();
        $contractUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'contract');
        $contractSection = $this->academicPersonsSettings->getDocumentSection('contracts');
        if ($contractSection === null || !$contractSection->isContractSection()) {
            $this->editorEndpoint->throwJsonError('unknown_document_section', 404);
        }
        $contract = $this->documentRecords->findDocumentRecord($profile, $contractSection, $contractUid);
        if (!$contract instanceof Contract) {
            $this->editorEndpoint->throwJsonError('document_not_found', 404);
        }
        $sectionIdentifier = $data['section'] ?? null;
        if (!is_string($sectionIdentifier) || $sectionIdentifier === '') {
            $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'A Contract contact section is required.');
        }
        $section = $this->academicPersonsSettings->getContractContactSection($sectionIdentifier);
        if ($section === null || !in_array(
            $section->identifier,
            ['physicalAddresses', 'emailAddresses', 'phoneNumbers'],
            true,
        )) {
            $this->editorEndpoint->throwJsonError('unknown_contract_contact_section', 404);
        }
        return [$profile, $contract, $section, $data];
    }

    /**
     * Verifies that the requested action is allowed for the contacts of a Contract.
     *
     * The contacts are edited inside the Contract editor and have no section of
     * their own, so they follow the `actions` allow-list and the `readOnly` flag
     * of the `contracts` document section - the same allow-list the rendered
     * buttons come from, mapped exactly as
     * {@see DocumentController::assertDocumentActionAllowed()} maps it: `add` is
     * creation, `sort` is drag sorting, everything else is the action of that name.
     *
     * @param string $action The action name to check: "view", "add", "edit",
     *                       "delete" or "sort".
     * @throws \RuntimeException Thrown when the action is not permitted, which
     *                           results in a JSON error response.
     */
    private function assertContractContactActionAllowed(string $action): void
    {
        $contractSection = $this->academicPersonsSettings->getDocumentSection('contracts');
        $allowed = $contractSection !== null && match ($action) {
            'add' => $contractSection->allowsCreate(),
            'sort' => $contractSection->allowsDragSorting(),
            default => $contractSection->allowsAction($action),
        };
        if (!$allowed) {
            $this->editorEndpoint->throwJsonError(
                'contract_contact_action_not_allowed',
                403,
                'This action is not allowed for Contract contacts.',
            );
        }
    }

    /**
     * Refuses the delete of a contact the synchronisation manages, and its edit
     * once every editable field of it is managed. Hiding and sorting are not
     * checked here: the synchronisation writes neither.
     *
     * @param ContractContactRecord|null $record
     * @param list<string> $managedProperties
     */
    private function assertManagedContractContactActionAllowed(
        ContractContactSection $section,
        Address|Email|PhoneNumber|null $record,
        array $managedProperties,
        string $action,
    ): void {
        if (in_array($action, $this->managedRecordLocks->getLockedContactActions($section, $record, $managedProperties), true)) {
            $this->editorEndpoint->throwJsonError(
                'contract_contact_action_not_allowed',
                403,
                'This action is not allowed for a synchronised Contract contact.',
            );
        }
    }
}
