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
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldDefinitions;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldNormalizer;
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
 * The JSON endpoints of the profile editor for the contracts and profile
 * information entries of a profile: opening, creating, updating, hiding,
 * deleting and sorting one. Addressed with the controller name `Document`
 * through the profile editing plugin.
 *
 * @internal This controller is intentionally internal to `EXT:academic_persons_edit`
 *           and is not part of the public API.
 */
final class DocumentController extends ActionController
{
    public function __construct(
        private readonly ProfileUpdateRequestService $profileUpdateRequestService,
        private readonly AcademicPersonsSettings $academicPersonsSettings,
        private readonly ManagedRecordLocks $managedRecordLocks,
        private readonly DocumentRecords $documentRecords,
        private readonly DocumentFieldDefinitions $documentFieldDefinitions,
        private readonly DocumentFieldNormalizer $documentFieldNormalizer,
        private readonly ContractContactFields $contractContactFields,
        private readonly ContractFactory $contractFactory,
        private readonly ContractRepository $contractRepository,
        private readonly ProfileInformationFactory $profileInformationFactory,
        private readonly ProfileInformationRepository $profileInformationRepository,
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
     * Returns the field schema and current values for a structured document section.
     *
     * The request must contain a valid section identifier and a matching mode
     * ("add", "view", "edit" or "delete"). In add mode, no record UID is allowed;
     * in all other modes, a positive record UID must reference an existing
     * contract/profile information entry for the current profile.
     *
     * The JSON response contains the profile ID, section identifier, kind and the
     * full field definitions used by the frontend modal for creating or editing the
     * selected document record.
     *
     * @return ResponseInterface JSON response with the current form definition and record data.
     */
    public function documentFormAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $this->editorEndpoint->assertDocumentPayload($data, ['section', 'record', 'mode'], ['section', 'mode']);
            $mode = $this->editorEndpoint->getRequiredDocumentMode($data);
            $recordUid = $this->editorEndpoint->getOptionalPositiveInteger($data, 'record');
            if (($mode === 'add') !== ($recordUid === null)) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The document record does not match the requested mode.');
            }
            $this->assertDocumentActionAllowed($section, $mode);
            $record = $recordUid === null
                ? null
                : $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($recordUid !== null && $record === null) {
                $this->editorEndpoint->throwJsonError('document_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedDocumentActionAllowed($section, $record, $managedProperties, $mode);
            $response = [
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'kind' => $section->isContractSection() ? 'contract' : 'profileInformation',
                'record' => $record?->getUid(),
                'fields' => $this->documentFieldDefinitions->getDocumentFieldDefinitions($this->request, $section, $record, $managedProperties),
            ];
            if ($record instanceof Contract) {
                $response['contactSections'] = $this->contractContactFields->getContractContactSections($this->request, $record);
            }
            return new JsonResponse($response);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Loading a structured document form failed.', $exception);
        }
    }

    /**
     * Creates a new structured document record for the given profile section.
     *
     * Validates the submitted payload, normalizes and validates the document fields,
     * creates the matching record type (contract or profile information), assigns
     * sorting and PID values, persists it, and returns the serialized result as JSON.
     *
     * @return ResponseInterface JSON response containing the created document item.
     * @throws PropagateResponseException If the response should be propagated without wrapping.
     * @throws \Throwable If the creation fails, the error is handled as a document failure.
     */
    public function createDocumentAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $this->editorEndpoint->assertDocumentPayload($data, ['section', 'fields'], ['section', 'fields']);
            $this->assertDocumentActionAllowed($section, 'add');
            $fields = $this->editorEndpoint->getSubmittedDocumentFields($data);
            $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
                $this->request,
                $section,
                $fields,
                true,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::CreateDocument,
                $section->identifier,
                fields: array_intersect_key($fields, $normalizedFields),
            );
            if ($replacedFields !== null) {
                $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
                    $this->request,
                    $section,
                    $replacedFields,
                    true,
                );
            }
            if ($section->isContractSection()) {
                $record = $this->contractFactory->createFromFormData(
                    $section->validationSet,
                    $profile,
                    $this->createContractFormData($normalizedFields),
                );
                $record->setSorting($this->documentRecords->getNextSortingValue($this->documentRecords->getDocumentRecords($profile, $section)));
                $record->setPid((int)$profile->getPid());
                $this->contractRepository->add($record);
            } else {
                $record = $this->profileInformationFactory->createFromFormData(
                    $section->validationSet,
                    $profile,
                    $this->createProfileInformationFormData($section, $normalizedFields),
                );
                $record->setSorting($this->documentRecords->getNextSortingValue($this->documentRecords->getDocumentRecords($profile, $section)));
                $record->setPid((int)$profile->getPid());
                $this->profileInformationRepository->add($record);
            }
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Creating a structured document failed.', $exception);
        }
    }

    /**
     * Updates an existing structured document item for a profile section.
     *
     * Validates the submitted payload, resolves the target record, normalizes and
     * validates the changed fields, and persists the updated values for either a
     * contract or a profile information record.
     *
     * @return ResponseInterface JSON response containing the updated document item.
     * @throws PropagateResponseException If a response is intentionally propagated.
     * @throws \Throwable If validation or persistence fails while updating the record.
     */
    public function updateDocumentAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $this->editorEndpoint->assertDocumentPayload(
                $data,
                ['section', 'record', 'fields'],
                ['section', 'record', 'fields'],
            );
            $this->assertDocumentActionAllowed($section, 'edit');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('document_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedDocumentActionAllowed($section, $record, $managedProperties, 'edit');
            $fields = $this->editorEndpoint->getSubmittedDocumentFields($data);
            $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
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
                ProfileEditingAction::UpdateDocument,
                $section->identifier,
                $record,
                array_intersect_key($fields, $normalizedFields),
            );
            if ($replacedFields !== null) {
                $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
                    $this->request,
                    $section,
                    $replacedFields,
                    false,
                    $managedProperties,
                );
            }
            if ($record instanceof Contract) {
                $this->contractRepository->update(
                    $this->contractFactory->updateFromFormData(
                        $section->validationSet,
                        $record,
                        $this->createContractFormData($normalizedFields),
                        $managedProperties,
                    ),
                );
            } else {
                $this->profileInformationRepository->update(
                    $this->profileInformationFactory->updateFromFormData(
                        $section->validationSet,
                        $record,
                        $this->createProfileInformationFormData($section, $normalizedFields),
                    ),
                );
            }
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Updating a structured document failed.', $exception);
        }
    }

    /**
     * Shows or hides one contract or profile information record in the frontend.
     *
     * `hidden` is the TCA `enablecolumns.disabled` column of the two tables. The
     * flag is sent explicitly rather than toggled, so a request that arrives
     * twice stores the state the visitor saw. The `hide` action of the section
     * governs it, configurable like every other action of the list.
     */
    public function toggleDocumentVisibilityAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $this->editorEndpoint->assertDocumentPayload($data, ['section', 'record', 'hidden'], ['section', 'record', 'hidden']);
            $this->assertDocumentActionAllowed($section, 'hide');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('document_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::ToggleDocumentVisibility,
                $section->identifier,
                $record,
                ['hidden' => $hidden],
            );
            $record->setHidden($hidden);
            if ($record instanceof Contract) {
                $this->contractRepository->update($record);
            } else {
                $this->profileInformationRepository->update($record);
            }
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Changing the visibility of a structured document failed.', $exception);
        }
    }

    /**
     * Deletes a single record from a structured document section.
     *
     * Validates the provided payload, ensures the section allows the delete
     * action, loads the requested record, and removes it from the matching
     * repository.
     *
     * @return ResponseInterface JSON response with the delete result.
     *
     * @throws PropagateResponseException If an error response should be
     *      propagated without being wrapped.
     * @throws \Throwable If the delete operation fails unexpectedly.
     */
    public function deleteDocumentAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $this->editorEndpoint->assertDocumentPayload($data, ['section', 'record'], ['section', 'record']);
            $this->assertDocumentActionAllowed($section, 'delete');
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('document_not_found', 404);
            }
            $this->assertManagedDocumentActionAllowed(
                $section,
                $record,
                $this->managedRecordLocks->getManagedProperties($record),
                'delete',
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::DeleteDocument,
                $section->identifier,
                $record,
            );
            if ($record instanceof Contract) {
                $this->contractRepository->remove($record);
            } else {
                $this->profileInformationRepository->remove($record);
            }
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'deleted' => $recordUid,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Deleting a structured document failed.', $exception);
        }
    }

    /**
     * Sorts the records of a structured document section.
     *
     * Supports reordering by a submitted complete order list as well as
     * moving a single record one step up or down within the section.
     *
     * @return ResponseInterface JSON response with the updated order and change state.
     * @throws PropagateResponseException When the request should be handled by the caller.
     * @throws \Throwable When the sorting operation fails unexpectedly.
     */
    public function sortDocumentAction(): ResponseInterface
    {
        try {
            [$profile, $section, $data] = $this->getDocumentRequest();
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            if (array_key_exists('order', $data)) {
                $this->editorEndpoint->assertDocumentPayload($data, ['section', 'order'], ['section', 'order']);
                $this->assertDocumentActionAllowed($section, 'reorder');
                $records = $this->documentRecords->getDocumentRecords($profile, $section);
                $order = $this->getSubmittedDocumentOrder($data);
                $this->documentRecords->assertCompleteDocumentOrder($records, $order);
                $this->editorWrites->dispatchBeforeWrite(
                    $pluginControllerActionContext,
                    $profile,
                    ProfileEditingAction::SortDocument,
                    $section->identifier,
                    fields: ['order' => $order],
                );
                $process = $this->documentRecords->reorderDocumentRecords($records, $order);
                if ($process['changed']) {
                    $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
                }
                return new JsonResponse([
                    'success' => true,
                    'profile' => $profile->getUid(),
                    'section' => $section->identifier,
                    'changed' => $process['changed'],
                    'order' => array_values(array_map(
                        static fn(Contract|ProfileInformation $record): int => (int)$record->getUid(),
                        $process['records'],
                    )),
                ]);
            }
            $this->editorEndpoint->assertDocumentPayload($data, ['section', 'record', 'direction'], ['section', 'record', 'direction']);
            $recordUid = $this->editorEndpoint->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->editorEndpoint->throwJsonError('document_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $this->assertDocumentActionAllowed($section, $direction);
            $this->editorWrites->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::SortDocument,
                $section->identifier,
                $record,
                ['direction' => $direction],
            );
            $records = $this->documentRecords->getDocumentRecords($profile, $section);
            $process = $this->listSortingService->sort(
                $records,
                $recordUid,
                $direction === 'up' ? ListSortingMode::UP : ListSortingMode::DOWN,
            );
            if ($process->changed) {
                $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $profile);
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
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
            $this->editorEndpoint->handleDocumentFailure(self::class, 'Sorting a structured document failed.', $exception);
        }
    }

    /**
     * Validates the current request payload and resolves the profile and document section.
     *
     * @return array{0: Profile, 1: DocumentSection, 2: array<string, mixed>}
     *   The validated profile, the resolved document section, and the request payload data.
     *
     * @throws \TYPO3\CMS\Core\Http\PropagateResponseException If the request validation pipeline propagates an exception.
     */
    private function getDocumentRequest(): array
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
        $sectionIdentifier = $data['section'] ?? null;
        if (!is_string($sectionIdentifier) || $sectionIdentifier === '') {
            $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'A document section is required.');
        }
        $section = $this->academicPersonsSettings->getDocumentSection($sectionIdentifier);
        if ($section === null) {
            $this->editorEndpoint->throwJsonError('unknown_document_section', 404);
        }
        return [$profile, $section, $data];
    }

    /**
     * Verifies that the requested action is allowed for the given document section.
     *
     * Some actions are mapped to specific section capabilities, such as creating
     * a new item or drag-and-drop reordering.
     *
     * @param DocumentSection $section The document section to validate.
     * @param string $action The action name to check, for example "add", "reorder",
     *                       or any action supported by the section.
     * @throws \RuntimeException Thrown when the action is not permitted for the section,
     *                           which results in a JSON error response.
     */
    private function assertDocumentActionAllowed(DocumentSection $section, string $action): void
    {
        $allowed = match ($action) {
            'add' => $section->allowsCreate(),
            'reorder' => $section->allowsDragSorting(),
            default => $section->allowsAction($action),
        };
        if (!$allowed) {
            $this->editorEndpoint->throwJsonError('document_action_not_allowed', 403, 'This action is not allowed for the document section.');
        }
    }

    /**
     * Refuses the delete of a contract the synchronisation manages, and its edit
     * once every editable field of it is managed. Profile information has no
     * import identifier and therefore nothing managed.
     *
     * @param list<string> $managedProperties
     */
    private function assertManagedDocumentActionAllowed(
        DocumentSection $section,
        Contract|ProfileInformation|null $record,
        array $managedProperties,
        string $action,
    ): void {
        if (in_array($action, $this->managedRecordLocks->getLockedDocumentActions($section, $record, $managedProperties), true)) {
            $this->editorEndpoint->throwJsonError(
                'document_action_not_allowed',
                403,
                'This action is not allowed for a synchronised record.',
            );
        }
    }

    /**
     * Validates and returns the submitted document order payload.
     *
     * @param array<string, mixed> $data The raw request payload.
     * @return list<int> The validated order as a list of positive integer record UIDs.
     * @throws \RuntimeException Thrown when the payload is invalid, which results in a JSON error response.
     */
    private function getSubmittedDocumentOrder(array $data): array
    {
        $order = $data['order'] ?? null;
        if (!is_array($order) || !array_is_list($order)) {
            $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'The document order must be a JSON list.');
        }
        foreach ($order as $recordUid) {
            if (!is_int($recordUid) || $recordUid <= 0) {
                $this->editorEndpoint->throwJsonError('invalid_payload', 400, 'Every document order value must be a positive integer.');
            }
        }
        return $order;
    }

    /**
     * Creates a contract form data object and applies the provided field overrides.
     *
     * @param array<string, mixed> $fields The raw form fields to override on the contract form.
     * @return ContractFormData The initialized contract form data instance.
     */
    private function createContractFormData(array $fields): ContractFormData
    {
        $formData = new ContractFormData();
        $this->applyDocumentFormOverrides($formData, $fields);
        return $formData;
    }

    /**
     * Creates a profile information form data object and applies the provided field overrides.
     *
     * @param DocumentSection $section The document section used to determine the profile information type.
     * @param array<string, mixed> $fields The raw form fields to override on the profile information form.
     * @return ProfileInformationFormData The initialized profile information form data instance.
     */
    private function createProfileInformationFormData(
        DocumentSection $section,
        array $fields,
    ): ProfileInformationFormData {
        $formData = ProfileInformationFormData::createEmptyForType($section->type);
        $formData->setPropertyOverride('type', $section->type);
        $this->applyDocumentFormOverrides($formData, $fields);
        return $formData;
    }

    /**
     * Applies the provided field overrides to the form data instance.
     *
     * @param AbstractFormData $formData The target form data object receiving the overrides.
     * @param array<string, mixed> $fields The raw form fields keyed by property name with their override values.
     */
    private function applyDocumentFormOverrides(AbstractFormData $formData, array $fields): void
    {
        foreach ($fields as $name => $value) {
            $formData->setPropertyOverride($name, $value);
        }
    }
}
