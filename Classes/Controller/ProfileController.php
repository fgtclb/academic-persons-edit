<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicBase\Controller\GetCurrentContentRecordMethodTrait;
use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContextInterface;
use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Event\ProfileUpdateOrigin;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersons\Service\ProfileImageMetadataService;
use FGTCLB\AcademicPersons\Service\ProfileImageRelationWriter;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use FGTCLB\AcademicPersonsEdit\Event\BeforeProfileEditingWriteEvent;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactRecords;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldDefinitions;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldNormalizer;
use FGTCLB\AcademicPersonsEdit\Service\DocumentRecords;
use FGTCLB\AcademicPersonsEdit\Service\EditorLabels;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidProjectProfileFieldException;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\LocalizedProfileUidResolver;
use FGTCLB\AcademicPersonsEdit\Service\ManagedRecordLocks;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileFieldOptionsService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateRequestService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateValidationService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileVisibilityWriter;
use FGTCLB\AcademicPersonsEdit\Service\ProjectProfileFields;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\FileUploadConfiguration;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Validation\Validator\FileSizeValidator;
use TYPO3\CMS\Extbase\Validation\Validator\MimeTypeValidator;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * Controller for profile editing via JSON-based frontend requests.
 *
 * The controller handles profile actions such as validation,
 * updates and data retrieval without relying on the shared HTML authentication
 * flow used by regular Extbase pages.
 *
 * @phpstan-import-type ContractContactRecord from ContractContactRecords
 *
 * @internal This controller is intentionally internal to `EXT:academic_persons_edit`
 *           and is not part of the public API.
 */
final class ProfileController extends ActionController
{
    use GetCurrentContentRecordMethodTrait;

    /**
     * The actions answering as JSON. They validate and answer their own request,
     * so {@see self::initializeAction()} does not send them through the HTML
     * access denied flow of the plugin.
     *
     * @var list<string>
     */
    private const JSON_ACTIONS = [
        'update',
        'updateSkipSync',
        'updateVisibility',
        'deleteImage',
        'documentForm',
        'createDocument',
        'updateDocument',
        'deleteDocument',
        'sortDocument',
        'toggleDocumentVisibility',
        'contractContactForm',
        'createContractContact',
        'updateContractContact',
        'deleteContractContact',
        'sortContractContact',
        'toggleContractContactVisibility',
    ];

    /**
     * The image formats accepted when the installation configures none. Used for
     * the `accept` attribute of the file input and for the server side validator,
     * so the two can never disagree.
     */
    private const DEFAULT_IMAGE_MIME_TYPES = 'image/jpeg,image/png,image/webp';

    /**
     * The upper bound applied when the installation configures none. A blank
     * setting used to reach `FileSizeValidator` verbatim, which rejects it with
     * `InvalidValidationOptionsException` - so blanking the setting did not
     * remove the limit, it broke every upload with a 500.
     */
    private const DEFAULT_IMAGE_MAX_FILE_SIZE = '2M';

    /**
     * The page type every JSON endpoint of the editor is addressed through. It
     * is spelled here and in `Templates/Profile/Index.html`, which builds the
     * URLs, and it is delivered by the site set
     * `fgtclb/academic-persons-edit-profile-editing` or by the static template.
     */
    private const AJAX_PAGE_TYPE = 1733735;

    public function __construct(
        private readonly Context $context,
        private readonly PersistenceManager $persistenceManager,
        private readonly AcademicPersonsSettings $academicPersonsSettings,
        private readonly ListSortingService $listSortingService,
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly ErrorController $errorController,
        private readonly LogManager $logManager,
        private readonly ResourceFactory $resourceFactory,
        private readonly ProfileImageMetadataService $profileImageMetadataService,
        private readonly ProfileUpdateRequestService $profileUpdateRequestService,
        private readonly ProfileUpdateValidationService $profileUpdateValidationService,
        private readonly LocalizedProfileUidResolver $localizedProfileUidResolver,
        private readonly ProfileVisibilityWriter $profileVisibilityWriter,
        private readonly ProfileImageRelationWriter $profileImageRelationWriter,
        private readonly DataHandlerExecutionContext $dataHandlerExecutionContext,
        private readonly ProfileFieldOptionsService $profileFieldOptionsService,
        private readonly ProfileSectionProvider $profileSectionProvider,
        private readonly ProfileDocumentSectionProvider $profileDocumentSectionProvider,
        private readonly ContractFactory $contractFactory,
        private readonly ContractRepository $contractRepository,
        private readonly ProfileInformationFactory $profileInformationFactory,
        private readonly ProfileInformationRepository $profileInformationRepository,
        private readonly ManagedRecordLocks $managedRecordLocks,
        private readonly ProjectProfileFields $projectProfileFields,
        private readonly EditorLabels $editorLabels,
        private readonly DocumentFieldDefinitions $documentFieldDefinitions,
        private readonly DocumentFieldNormalizer $documentFieldNormalizer,
        private readonly DocumentRecords $documentRecords,
        private readonly ContractContactFields $contractContactFields,
        private readonly ContractContactRecords $contractContactRecords,
    ) {}

    /**
     * Initializes the controller without the HTML auth flow for JSON endpoints.
     *
     * These actions validate and answer their own requests as machine-readable
     * payloads, so they must bypass the extbase parent initialization to return
     * their own 401/422/403 responses instead of the generic HTML error page.
     */
    public function initializeAction(): void
    {
        $actionName = $this->request->getControllerActionName();
        if (in_array($actionName, self::JSON_ACTIONS, true) || $actionName === 'uploadImage') {
            $this->assertRequestWasSentByTheEditor();
        }
        if (in_array($actionName, self::JSON_ACTIONS, true)) {
            return;
        }
        if ($this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false) === false) {
            throw new PropagateResponseException(
                $this->errorController->accessDeniedAction(
                    $this->request,
                    'Authentication needed'
                ),
                1744109477
            );
        }
    }

    /**
     * Every writing endpoint requires the `X-Requested-With: XMLHttpRequest` header
     * the editor sends on each of its requests.
     *
     * The JSON endpoints are already protected by their content type - a foreign
     * page cannot send `application/json` without a CORS preflight - but the image
     * upload is `multipart/form-data`, which is a simple request any page can submit
     * with a plain form. A custom header cannot be set cross-origin without that
     * preflight either, so requiring one closes the upload without relying on the
     * `SameSite` attribute of the session cookie, which an installation may change.
     * It is demanded on all of them so that one rule covers the whole surface.
     *
     * @throws PropagateResponseException When the header is absent.
     */
    private function assertRequestWasSentByTheEditor(): void
    {
        if ($this->request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            throw new PropagateResponseException(
                $this->jsonError(
                    'invalid_request',
                    400,
                    'The request must carry the "X-Requested-With: XMLHttpRequest" header.',
                ),
                1777046203,
            );
        }
    }

    /**
     * Converts validation failures from the image upload flow into a JSON error response.
     *
     * This keeps upload-related form validation machine-readable instead of falling
     * back to the generic HTML error handling from the parent controller.
     *
     * @return ResponseInterface The rendered response for the current error state.
     */
    protected function errorAction(): ResponseInterface
    {
        if ($this->request->getControllerActionName() === 'uploadImage') {
            throw new PropagateResponseException(
                $this->jsonError(
                    'validation_failed',
                    422,
                    $this->getFlattenedValidationErrorMessage(),
                ),
                1776760202,
            );
        }
        return parent::errorAction();
    }

    // =================================================================================================================
    // Assigned profile list and initial display of the selected profile
    // =================================================================================================================

    /**
     * Renders the list of profiles assigned to the currently authenticated frontend user.
     *
     * The method loads the user-related profiles, resolves the current site for
     * language labels and exposes the prepared data to the view template.
     *
     * @return ResponseInterface The rendered HTML response for the profile list.
     */
    public function listAction(): ResponseInterface
    {
        $profiles = $this->profileRepository->findByFrontendUserIncludingHidden(
            (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0),
        );
        $site = $this->request->getAttribute('site');
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profiles' => $profiles,
            'profileListItems' => $this->createProfileListItems(
                $profiles,
                $site instanceof Site ? $site : null,
            ),
        ]);
        return $this->htmlResponse();
    }

    /**
     * Displays the editable profile view for a given user profile ID.
     *
     * This action fetches the editable profile data, checks for existence,
     * and assigns necessary data structures to the view for rendering.
     *
     * A profile the authenticated user is not assigned to is answered with the
     * access denied response of the site rather than with a rendered page carrying
     * a 403 status: on TYPO3 v13 the status of an Extbase plugin response never
     * reaches the frontend response - `Bootstrap::handleFrontendRequest()` passes
     * it to `header()` and returns the body alone - so a `withStatus(403)` here
     * would be answered with 200 on that version. The propagated response is the
     * one both supported versions honour, and it is the same one
     * {@see self::initializeAction()} raises for a visitor who
     * is not logged in at all.
     *
     * @param int $profileUid The unique ID of the profile to edit.
     * @return ResponseInterface The HTML response containing the editable profile view.
     * @throws PropagateResponseException If the profile is not assigned to the authenticated user.
     */
    public function indexAction(int $profileUid): ResponseInterface
    {
        $profile = $this->profileUpdateRequestService->findEditableProfile($profileUid);
        if ($profile === null) {
            throw new PropagateResponseException(
                $this->errorController->accessDeniedAction(
                    $this->request,
                    'Profile not editable',
                ),
                1777046201,
            );
        }
        $managedProperties = $this->managedRecordLocks->getManagedProperties($profile);
        // A column the editor must not use fails the page with the exception naming it.
        $projectFields = $this->projectProfileFields->getFields();
        $this->projectProfileFields->assertUsable($projectFields);
        $projectRecordUid = $projectFields === [] ? null : $this->resolvePersistedProfileUid($profile);
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'profileSections' => $this->profileSectionProvider->getSections(
                $managedProperties,
                $projectRecordUid === null ? [] : $this->projectProfileFields->readValues($projectRecordUid, $projectFields),
            ),
            'specialFields' => $this->profileSectionProvider->getSpecialFields($managedProperties),
            'profileFieldOptions' => $this->profileFieldOptionsService->getOptionsByField($this->request),
            'documentSections' => $this->profileDocumentSectionProvider->getSections($profile),
            'imageAllowedMimeTypes' => $this->resolveImageAllowedMimeTypes(),
            'editorLanguage' => $this->resolveEditorLanguage(),
            'ajaxPageTypeConfigured' => $this->ajaxPageTypeIsConfigured(),
        ]);
        return $this->htmlResponse();
    }

    /**
     * Whether this site renders a `PAGE` object for the page type the editor
     * addresses its endpoints through.
     *
     * Every write of the editor goes to `&type=1733735`, which the extension
     * declares in its own TypoScript. A site package that copied that
     * TypoScript instead of including the site set - which the override manual
     * invites - has the editor and not the page type, so the browser gets the
     * site's error page where it expects JSON. That failure is client side and
     * says nothing an integrator can act on, which is why it is detected while
     * the editor is rendered: the log entry names the cause and the visitor is
     * told the editor cannot save instead of finding out on the first change.
     *
     * The object is looked up by its `typeNum` rather than by its name, because
     * the name is the extension's and the page type is the contract. The
     * `hasSetup()` guard is defence in depth rather than a case that occurs
     * here: `ext_localconf.php` passes the same action list as the cacheable
     * and as the non-cacheable argument of `configurePlugin()`, so every action
     * of this plugin is a `USER_INT`, and a page carrying it always forces the
     * full TypoScript setup - on a cache hit as well, because the cached page
     * then carries uncached content elements. Where a setup is missing
     * nonetheless, the check answers "configured": a request it cannot judge
     * must not raise an alert.
     */
    private function ajaxPageTypeIsConfigured(): bool
    {
        $frontendTypoScript = $this->request->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript || !$frontendTypoScript->hasSetup()) {
            return true;
        }
        $setup = $frontendTypoScript->getSetupArray();
        foreach ($setup as $objectName => $objectType) {
            if ($objectType !== 'PAGE') {
                continue;
            }
            $configuration = $setup[$objectName . '.'] ?? null;
            if (is_array($configuration) && (int)($configuration['typeNum'] ?? 0) === self::AJAX_PAGE_TYPE) {
                return true;
            }
        }
        $this->logManager->getLogger(self::class)->error(
            'No TypoScript PAGE object with typeNum={type} is configured for this site, so every write of the'
            . ' profile editor is answered with the site\'s error page instead of JSON. Include the site set'
            . ' "fgtclb/academic-persons-edit-profile-editing" or the static template of the extension, or add'
            . ' the "academicPersonsProfileEditingAjax" PAGE object to your own TypoScript.',
            ['type' => self::AJAX_PAGE_TYPE],
        );
        return false;
    }

    /**
     * The language code the rich text editor localises its own user interface
     * with: the primary subtag of the site language's locale, which is how
     * CKEditor 5 names its translation bundles.
     */
    private function resolveEditorLanguage(): string
    {
        $siteLanguage = $this->request->getAttribute('language');
        if (!$siteLanguage instanceof SiteLanguage) {
            return '';
        }
        return strtolower(explode('-', str_replace('_', '-', $siteLanguage->getLocale()->getLanguageCode()))[0]);
    }

    /**
     * The maximum upload size, falling back to the default when the setting is
     * missing or blank - the validator rejects an empty option rather than
     * treating it as "no limit".
     */
    private function resolveImageMaxFileSize(): string
    {
        $maxFileSize = $this->settings['editForm']['profileImage']['validation']['maxFileSize'] ?? null;
        if (!is_string($maxFileSize) || trim($maxFileSize) === '') {
            return self::DEFAULT_IMAGE_MAX_FILE_SIZE;
        }
        return trim($maxFileSize);
    }

    /**
     * Resolves the allowed MIME types for profile image uploads.
     *
     * Falls back to the default browser-safe image formats when the settings are
     * missing, empty or not configured as a string.
     *
     * @return string A comma-separated list of allowed MIME types.
     */
    private function resolveImageAllowedMimeTypes(): string
    {
        $mimeTypes = $this->settings['editForm']['profileImage']['validation']['allowedMimeTypes'] ?? null;
        if (!is_string($mimeTypes) || trim($mimeTypes) === '') {
            return self::DEFAULT_IMAGE_MIME_TYPES;
        }
        return $mimeTypes;
    }

    /**
     * Builds the display list for the profile overview.
     *
     * Each entry contains the profile entity and the localized language label that
     * should be shown alongside it in the frontend list. Profiles without a
     * valid language mapping fall back to the generic "all languages" or
     * translated "unknown" label.
     *
     * @param iterable<Profile> $profiles The profiles assigned to the current frontend user.
     * @param Site|null $site The current site used to resolve language labels.
     * @return list<array{profile: Profile, language: string}> The normalized list items for the template.
     */
    private function createProfileListItems(iterable $profiles, ?Site $site): array
    {
        $languageLabels = [];
        foreach ($site?->getAllLanguages() ?? [] as $siteLanguage) {
            $languageLabels[$siteLanguage->getLanguageId()] = $siteLanguage->getTitle();
        }
        $items = [];
        foreach ($profiles as $profile) {
            if (!$profile instanceof Profile) {
                continue;
            }
            $languageUid = $profile->getLanguageUid();
            if ($languageUid === -1) {
                $language = $this->editorLabels->translate(
                    $this->request,
                    'list.language.all',
                ) ?? 'All languages';
            } else {
                $language = $languageLabels[$languageUid]
                    ?? $this->editorLabels->translate(
                        $this->request,
                        'list.language.unknown',
                        [$languageUid],
                    )
                    ?? sprintf('Language %d', $languageUid);
            }
            $items[] = [
                'profile' => $profile,
                'language' => $language,
            ];
        }
        return $items;
    }

    // =================================================================================================================
    // Handle entity profile operations
    // =================================================================================================================

    /**
     * Updates one or more profile fields through the JSON API.
     *
     * The request payload contains the profile identifier and a partial field map.
     * Missing properties keep the current value, empty strings clear the value,
     * and concrete values replace it. The method validates the payload and form
     * data before persisting the updated entity and returning the normalized data.
     *
     * The values of project fields, columns a site package declared in the persons
     * settings, are validated in the same pass and written through the DataHandler
     * once the Extbase write is done, to the row of the edited language. They are
     * written before the update is announced, so the listeners of the announcement
     * see them, and the answer carries the values as they were stored.
     *
     * @return ResponseInterface A JSON response with the updated profile UID and normalized values.
     */
    public function updateAction(): ResponseInterface
    {
        $requestResult = $this->profileUpdateRequestService->validate(
            $this->request
        );
        if (!$requestResult->isValid()) {
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        try {
            $managedProperties = $this->managedRecordLocks->getManagedProperties($profile);
            $this->assertSubmittedProjectFieldsAreUsable($payload);
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $profileFormData = $this->createValidatedProfileFormData(
                $pluginControllerActionContext,
                $profile,
                $payload,
                $managedProperties,
                'The submitted profile data is invalid.',
            );
            $replacedFields = $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::UpdateProfile,
                fields: array_filter(
                    $payload->getData(),
                    static fn(string $propertyName): bool => $profileFormData->hasPropertyOverride($propertyName),
                    ARRAY_FILTER_USE_KEY,
                ),
            );
            if ($replacedFields !== null) {
                $payload = new ProfileUpdatePayload($payload->getProfileUid(), $replacedFields);
                $this->assertSubmittedProjectFieldsAreUsable($payload);
                $profileFormData = $this->createValidatedProfileFormData(
                    $pluginControllerActionContext,
                    $profile,
                    $payload,
                    $managedProperties,
                    'The submitted profile data is invalid.',
                );
            }
            $projectRecordUid = $this->resolveProjectFieldRecord($profile, $profileFormData);
            $updatedProfile = $this->profileFactory->updateFromFormData(
                $this->academicPersonsSettings->getProfileUpdateValidationSet(),
                $profile,
                $profileFormData,
                $managedProperties,
            );
            $this->profileRepository->update($updatedProfile);
            $this->persistAndDispatchProfileUpdate(
                $updatedProfile,
                $projectRecordUid === null
                    ? null
                    : fn() => $this->projectProfileFields->writeValues($projectRecordUid, $profileFormData->getCustomValues()),
            );
            $data = $this->profileUpdateValidationService->getNormalizedData(
                $profileFormData,
                $payload,
                $managedProperties,
            );
            if ($projectRecordUid !== null) {
                // The DataHandler applies the TCA of a column, a `max` for example, so
                // the answer carries what it stored rather than what was submitted.
                $data = array_replace($data, $this->projectProfileFields->readValues(
                    $projectRecordUid,
                    array_intersect_key($this->projectProfileFields->getFields(), $profileFormData->getCustomValues()),
                ));
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $updatedProfile->getUid(),
                'data' => $data,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile failed.', [
                    'exception' => $exception,
                ]);
            $this->throwJsonError(
                'internal_server_error',
                500,
                'The profile could not be updated.',
            );
        }
    }

    /**
     * Updates the profile synchronization flag through its dedicated JSON endpoint.
     *
     * The endpoint accepts exactly one boolean field, skipSync, so the checkbox
     * can be persisted independently from other profile data without submitting
     * unrelated values. It validates the payload before storing the updated flag
     * and returning the persisted result.
     *
     * @return ResponseInterface A JSON response containing the updated profile UID and synchronization state.
     */
    public function updateSkipSyncAction(): ResponseInterface
    {
        $requestResult = $this->profileUpdateRequestService->validate(
            $this->request,
        );
        if (!$requestResult->isValid()) {
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        $this->assertSkipSyncPayload($payload->getData());
        if (!$this->isSpecialFieldWritable('skipSync')) {
            // The switch is the whole request, so a locked switch is refused
            // rather than ignored as a locked field of the profile form is.
            $this->throwJsonError(
                'invalid_profile_data',
                422,
                'Unknown profile property "skipSync".',
            );
        }
        try {
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $profileFormData = $this->createValidatedProfileFormData(
                $pluginControllerActionContext,
                $profile,
                $payload,
                [],
                'The submitted synchronization setting is invalid.',
            );
            $replacedFields = $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::UpdateSkipSync,
                fields: $payload->getData(),
            );
            if ($replacedFields !== null) {
                $this->assertSkipSyncPayload($replacedFields);
                $profileFormData = $this->createValidatedProfileFormData(
                    $pluginControllerActionContext,
                    $profile,
                    new ProfileUpdatePayload($payload->getProfileUid(), $replacedFields),
                    [],
                    'The submitted synchronization setting is invalid.',
                );
            }
            $updatedProfile = $this->profileFactory->updateFromFormData(
                $this->academicPersonsSettings->getProfileUpdateValidationSet(),
                $profile,
                $profileFormData,
            );
            $this->profileRepository->update($updatedProfile);
            $this->persistAndDispatchProfileUpdate($updatedProfile);
            return new JsonResponse([
                'success' => true,
                'profile' => $updatedProfile->getUid(),
                'skipSync' => $updatedProfile->getSkipSync(),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile synchronization flag failed.', [
                    'exception' => $exception,
                ]);
            $this->throwJsonError(
                'internal_server_error',
                500,
                'The synchronization setting could not be updated.',
            );
        }
    }

    /**
     * Shows or hides the profile through its dedicated JSON endpoint, the owner's
     * "Show my profile publicly" switch.
     *
     * The endpoint accepts exactly one boolean field, hidden, the value of the
     * profile's `hidden` column, which the switch displays inverted. It is refused
     * with a 403 when the installation made the switch read-only, disabled it or
     * removed it, so that a profile an editor hid stays hidden. The value is written
     * for the default-language record and reaches every translation, see
     * {@see ProfileVisibilityWriter}. The owner keeps reaching the profile either way:
     * the editor finds the owner's profiles including hidden ones.
     *
     * @return ResponseInterface A JSON response containing the profile UID and whether it is hidden now.
     */
    public function updateVisibilityAction(): ResponseInterface
    {
        $requestResult = $this->profileUpdateRequestService->validate(
            $this->request,
        );
        if (!$requestResult->isValid()) {
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        if (!$this->isSpecialFieldWritable('hidden')) {
            $this->throwJsonError(
                'visibility_not_editable',
                403,
                'The visibility of this profile cannot be changed here.',
            );
        }
        $data = $payload->getData();
        if (
            array_keys($data) !== ['hidden']
            || !is_bool($data['hidden'])
        ) {
            $this->throwJsonError(
                'invalid_payload',
                400,
                'The payload must contain exactly one boolean hidden value.',
            );
        }
        // Written through the DataHandler, like the image: a frontend request acting
        // in a non-live workspace would produce a version of the record nobody asked
        // for, see requirePersistedProfileUid().
        if ($this->dataHandlerExecutionContext->isFrontendRequestInWorkspace()) {
            $this->throwJsonError(
                'workspace_not_supported',
                409,
                'The visibility of the profile cannot be changed from a workspace preview.',
            );
        }
        try {
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::UpdateVisibility,
                fields: ['hidden' => $data['hidden']],
            );
            // The uid of an Extbase language overlay is the one of its default-language
            // record, which is the record the value belongs to.
            $hidden = $this->profileVisibilityWriter->write(
                (int)$profile->getUid(),
                $data['hidden'],
            );
            // The object follows the stored state, so that a listener of the update
            // reads it. It is marked clean: the DataHandler wrote the value, and the
            // persistence of Extbase must not write it again at the end of the request.
            $profile->setHidden($hidden);
            $profile->_memorizeCleanState('hidden');
            if (!$profile->getIsTranslation()) {
                $this->dispatchProfileUpdate($profile);
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'hidden' => $hidden,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile visibility failed.', [
                    'exception' => $exception,
                ]);
            $this->throwJsonError(
                'internal_server_error',
                500,
                'The visibility of the profile could not be updated.',
            );
        }
    }

    // =================================================================================================================
    // Handle structured document sections through the profile editing JSON API
    // =================================================================================================================

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
            $this->assertDocumentPayload($data, ['section', 'record', 'mode'], ['section', 'mode']);
            $mode = $this->getRequiredDocumentMode($data);
            $recordUid = $this->getOptionalPositiveInteger($data, 'record');
            if (($mode === 'add') !== ($recordUid === null)) {
                $this->throwJsonError('invalid_payload', 400, 'The document record does not match the requested mode.');
            }
            $this->assertDocumentActionAllowed($section, $mode);
            $record = $recordUid === null
                ? null
                : $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($recordUid !== null && $record === null) {
                $this->throwJsonError('document_not_found', 404);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Loading a structured document form failed.', $exception);
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
            $this->assertDocumentPayload($data, ['section', 'fields'], ['section', 'fields']);
            $this->assertDocumentActionAllowed($section, 'add');
            $fields = $this->getSubmittedDocumentFields($data);
            $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
                $this->request,
                $section,
                $fields,
                true,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Creating a structured document failed.', $exception);
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
            $this->assertDocumentPayload(
                $data,
                ['section', 'record', 'fields'],
                ['section', 'record', 'fields'],
            );
            $this->assertDocumentActionAllowed($section, 'edit');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedDocumentActionAllowed($section, $record, $managedProperties, 'edit');
            $fields = $this->getSubmittedDocumentFields($data);
            $normalizedFields = $this->documentFieldNormalizer->normalizeAndValidateDocumentFields(
                $this->request,
                $section,
                $fields,
                false,
                $managedProperties,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Updating a structured document failed.', $exception);
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
            $this->assertDocumentPayload($data, ['section', 'record', 'hidden'], ['section', 'record', 'hidden']);
            $this->assertDocumentActionAllowed($section, 'hide');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->documentFieldDefinitions->serializeDocumentItem($this->request, $section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Changing the visibility of a structured document failed.', $exception);
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
            $this->assertDocumentPayload($data, ['section', 'record'], ['section', 'record']);
            $this->assertDocumentActionAllowed($section, 'delete');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $this->assertManagedDocumentActionAllowed(
                $section,
                $record,
                $this->managedRecordLocks->getManagedProperties($record),
                'delete',
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'deleted' => $recordUid,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Deleting a structured document failed.', $exception);
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
                $this->assertDocumentPayload($data, ['section', 'order'], ['section', 'order']);
                $this->assertDocumentActionAllowed($section, 'reorder');
                $records = $this->documentRecords->getDocumentRecords($profile, $section);
                $order = $this->getSubmittedDocumentOrder($data);
                $this->documentRecords->assertCompleteDocumentOrder($records, $order);
                $this->dispatchBeforeWrite(
                    $pluginControllerActionContext,
                    $profile,
                    ProfileEditingAction::SortDocument,
                    $section->identifier,
                    fields: ['order' => $order],
                );
                $process = $this->documentRecords->reorderDocumentRecords($records, $order);
                if ($process['changed']) {
                    $this->persistAndDispatchProfileUpdate($profile);
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
            $this->assertDocumentPayload($data, ['section', 'record', 'direction'], ['section', 'record', 'direction']);
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->documentRecords->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $this->assertDocumentActionAllowed($section, $direction);
            $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::SortDocument,
                $section->identifier,
                $record,
                ['direction' => $direction],
            );
            $records = $this->documentRecords->getDocumentRecords($profile, $section);
            $process = $this->sortItems(
                $records,
                $recordUid,
                $direction === 'up' ? ListSortingMode::UP : ListSortingMode::DOWN,
            );
            if ($process->changed) {
                $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Sorting a structured document failed.', $exception);
        }
    }

    // =============================================================================================================
    // Handle Contract contact records through the profile editing JSON API
    // =============================================================================================================

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
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'mode'],
                ['contract', 'section', 'mode'],
            );
            $mode = $this->getRequiredDocumentMode($data);
            $recordUid = $this->getOptionalPositiveInteger($data, 'record');
            if (($mode === 'add') !== ($recordUid === null)) {
                $this->throwJsonError(
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
                $this->throwJsonError('contract_contact_not_found', 404);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Loading a Contract contact form failed.', $exception);
        }
    }

    /**
     * Creates an address, email address or phone number below an editable Contract.
     */
    public function createContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'fields'],
                ['contract', 'section', 'fields'],
            );
            $this->assertContractContactActionAllowed('add');
            $fields = $this->getSubmittedDocumentFields($data);
            $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                $this->request,
                $section,
                $fields,
                true,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Creating a Contract contact failed.', $exception);
        }
    }

    /**
     * Updates an existing address, email address or phone number below a Contract.
     */
    public function updateContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'fields'],
                ['contract', 'section', 'record', 'fields'],
            );
            $this->assertContractContactActionAllowed('edit');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $managedProperties = $this->managedRecordLocks->getManagedProperties($record);
            $this->assertManagedContractContactActionAllowed($section, $record, $managedProperties, 'edit');
            $fields = $this->getSubmittedDocumentFields($data);
            $normalizedFields = $this->contractContactFields->normalizeAndValidateContractContactFields(
                $this->request,
                $section,
                $fields,
                false,
                $managedProperties,
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $replacedFields = $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Updating a Contract contact failed.', $exception);
        }
    }

    /**
     * Deletes one address, email address or phone number below a Contract.
     */
    public function deleteContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record'],
                ['contract', 'section', 'record'],
            );
            $this->assertContractContactActionAllowed('delete');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $this->assertManagedContractContactActionAllowed(
                $section,
                $record,
                $this->managedRecordLocks->getManagedProperties($record),
                'delete',
            );
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::DeleteContractContact,
                $section->identifier,
                $record,
                contract: $contract,
            );
            $this->contractContactRecords->removeContractContactRecord($record);
            $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Deleting a Contract contact failed.', $exception);
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
     * `toggleDocumentVisibilityAction()`.
     */
    public function toggleContractContactVisibilityAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'hidden'],
                ['contract', 'section', 'record', 'hidden'],
            );
            $this->assertContractContactActionAllowed('edit');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
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
            $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Changing the visibility of a Contract contact failed.', $exception);
        }
    }

    /**
     * Moves one Contract contact up or down within its configured section.
     */
    public function sortContractContactAction(): ResponseInterface
    {
        try {
            [$profile, $contract, $section, $data] = $this->getContractContactRequest();
            $this->assertDocumentPayload(
                $data,
                ['contract', 'section', 'record', 'direction'],
                ['contract', 'section', 'record', 'direction'],
            );
            $this->assertContractContactActionAllowed('sort');
            $recordUid = $this->getRequiredPositiveInteger($data, 'record');
            $record = $this->contractContactRecords->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::SortContractContact,
                $section->identifier,
                $record,
                ['direction' => $direction],
                $contract,
            );
            $process = $this->sortItems(
                $this->contractContactRecords->getContractContactRecords($contract, $section),
                $recordUid,
                $direction === 'up' ? ListSortingMode::UP : ListSortingMode::DOWN,
            );
            if ($process->changed) {
                $this->persistAndDispatchProfileUpdate($profile);
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
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->handleDocumentFailure('Sorting Contract contacts failed.', $exception);
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
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        $data = $payload->getData();
        $contractUid = $this->getRequiredPositiveInteger($data, 'contract');
        $contractSection = $this->academicPersonsSettings->getDocumentSection('contracts');
        if ($contractSection === null || !$contractSection->isContractSection()) {
            $this->throwJsonError('unknown_document_section', 404);
        }
        $contract = $this->documentRecords->findDocumentRecord($profile, $contractSection, $contractUid);
        if (!$contract instanceof Contract) {
            $this->throwJsonError('document_not_found', 404);
        }
        $sectionIdentifier = $data['section'] ?? null;
        if (!is_string($sectionIdentifier) || $sectionIdentifier === '') {
            $this->throwJsonError('invalid_payload', 400, 'A Contract contact section is required.');
        }
        $section = $this->academicPersonsSettings->getContractContactSection($sectionIdentifier);
        if ($section === null || !in_array(
            $section->identifier,
            ['physicalAddresses', 'emailAddresses', 'phoneNumbers'],
            true,
        )) {
            $this->throwJsonError('unknown_contract_contact_section', 404);
        }
        return [$profile, $contract, $section, $data];
    }

    /**
     * Verifies that the requested action is allowed for the contacts of a Contract.
     *
     * The contacts are edited inside the Contract editor and have no section of
     * their own, so they follow the `actions` allow-list and the `readOnly` flag
     * of the `contracts` document section - the same allow-list the rendered
     * buttons come from, mapped exactly as {@see assertDocumentActionAllowed()}
     * maps it: `add` is creation, `sort` is drag sorting, everything else is the
     * action of that name.
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
            $this->throwJsonError(
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
            $this->throwJsonError(
                'contract_contact_action_not_allowed',
                403,
                'This action is not allowed for a synchronised Contract contact.',
            );
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
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        $data = $payload->getData();
        $sectionIdentifier = $data['section'] ?? null;
        if (!is_string($sectionIdentifier) || $sectionIdentifier === '') {
            $this->throwJsonError('invalid_payload', 400, 'A document section is required.');
        }
        $section = $this->academicPersonsSettings->getDocumentSection($sectionIdentifier);
        if ($section === null) {
            $this->throwJsonError('unknown_document_section', 404);
        }
        return [$profile, $section, $data];
    }

    /**
     * Validates the document payload and ensures that only allowed keys are present
     * and all required keys are available.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param list<string> $allowedKeys A list of allowed property names.
     * @param list<string> $requiredKeys A list of property names that must be present.
     */
    private function assertDocumentPayload(array $data, array $allowedKeys, array $requiredKeys): void
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
                $this->throwJsonError('invalid_payload', 400, 'The document payload contains an unknown property.');
            }
        }
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $data)) {
                $this->throwJsonError('invalid_payload', 400, 'The document payload is incomplete.');
            }
        }
    }

    /**
     * Validates that a required property exists and is a positive integer.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param string $property The property name to read.
     * @return int<1, max> The validated positive integer value.
     */
    private function getRequiredPositiveInteger(array $data, string $property): int
    {
        $value = $data[$property] ?? null;
        if (!is_int($value) || $value <= 0) {
            $this->throwJsonError('invalid_payload', 400, sprintf('%s must be a positive integer.', $property));
        }
        return $value;
    }

    /**
     * Gets an optional positive integer property from the payload.
     *
     * Returns null when the property is missing, null, or zero.
     *
     * @param array<string, mixed> $data The payload to validate.
     * @param string $property The property name to read.
     * @return int|null The validated positive integer value or null when not set.
     */
    private function getOptionalPositiveInteger(array $data, string $property): ?int
    {
        if (!array_key_exists($property, $data) || $data[$property] === null || $data[$property] === 0) {
            return null;
        }
        return $this->getRequiredPositiveInteger($data, $property);
    }

    /**
     * Gets the required document mode from the payload.
     *
     * Valid modes are: "add", "view", "edit", and "delete".
     *
     * @param array<string, mixed> $data The payload to validate.
     * @return 'add'|'view'|'edit'|'delete' The validated document mode.
     */
    private function getRequiredDocumentMode(array $data): string
    {
        $mode = $data['mode'] ?? null;
        if (!is_string($mode) || !in_array($mode, ['add', 'view', 'edit', 'delete'], true)) {
            $this->throwJsonError('invalid_payload', 400, 'The document mode is invalid.');
        }
        return $mode;
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
            $this->throwJsonError('document_action_not_allowed', 403, 'This action is not allowed for the document section.');
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
            $this->throwJsonError(
                'document_action_not_allowed',
                403,
                'This action is not allowed for a synchronised record.',
            );
        }
    }

    /**
     * Validates and returns the submitted document field payload.
     *
     * @param array<string, mixed> $data The raw request payload.
     * @return array<string, mixed> The validated document fields as an associative array.
     * @throws \RuntimeException Thrown when the payload is invalid or field names are not strings,
     *                           which results in a JSON error response.
     */
    private function getSubmittedDocumentFields(array $data): array
    {
        $fields = $data['fields'] ?? null;
        if (!is_array($fields)) {
            $this->throwJsonError('invalid_payload', 400, 'Document fields must be a JSON object.');
        }
        foreach (array_keys($fields) as $fieldName) {
            if (!is_string($fieldName)) {
                $this->throwJsonError('invalid_payload', 400, 'Document field names must be strings.');
            }
        }
        return $fields;
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
            $this->throwJsonError('invalid_payload', 400, 'The document order must be a JSON list.');
        }
        foreach ($order as $recordUid) {
            if (!is_int($recordUid) || $recordUid <= 0) {
                $this->throwJsonError('invalid_payload', 400, 'Every document order value must be a positive integer.');
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

    /**
     * Logs a failed document operation and terminates the request with a JSON error response.
     *
     * @param string $logMessage Message to log for diagnosing the failure
     * @param \Throwable $exception The exception that caused the document operation to fail
     */
    private function handleDocumentFailure(string $logMessage, \Throwable $exception): never
    {
        $this->logManager
            ->getLogger(self::class)
            ->error($logMessage, ['exception' => $exception]);
        $this->throwJsonError(
            'internal_server_error',
            500,
            'The document operation could not be completed.',
        );
    }

    /**
     * Creates a JSON error response payload for failed requests.
     *
     * @param string $error Machine-readable error identifier.
     * @param int $statusCode HTTP status code to return in the response.
     * @param string|null $message Optional human-readable message describing the error.
     * @param array<string, list<string>> $errors Optional field-specific validation errors keyed by field name.
     * @return JsonResponse The generated JSON error response.
     */
    private function jsonError(
        string $error,
        int $statusCode,
        ?string $message = null,
        array $errors = [],
    ): JsonResponse {
        $body = [
            'success' => false,
            'error' => $error,
        ];
        if ($message !== null) {
            $body['message'] = $message;
        }
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        return new JsonResponse($body, $statusCode);
    }

    /**
     * Throws a JSON error response as a propagated exception.
     *
     * This method creates a JSON error payload and wraps it in a
     * PropagateResponseException so the framework can handle the response
     * without continuing the normal controller flow.
     *
     * @param string $error The machine-readable error code.
     * @param int $statusCode The HTTP status code to send with the response.
     * @param string|null $message An optional human-readable error message.
     * @param array<string, list<string>> $errors Optional field-specific validation errors keyed by field name.
     * @return never This method does not return normally because it always throws.
     * @throws PropagateResponseException Thrown when the JSON error response should be propagated.
     */
    private function throwJsonError(
        string $error,
        int $statusCode,
        ?string $message = null,
        array $errors = [],
    ): never {
        throw new PropagateResponseException(
            $this->jsonError($error, $statusCode, $message, $errors),
            1776760205,
        );
    }

    /**
     * Answers a request a service of the editor refused with the JSON error the
     * refusal describes, exactly as {@see self::throwJsonError()} answers one the
     * controller refused itself.
     */
    private function answerRefusal(EditorRequestException $exception): never
    {
        $this->throwJsonError(
            $exception->error,
            $exception->statusCode,
            $exception->responseMessage,
            $exception->fieldErrors,
        );
    }

    // =================================================================================================================
    //  Handle entity image operations
    // =================================================================================================================
    /**
     * Initializes the upload process for a profile image.
     *
     * Validates that the image field is writable and that the referenced profile
     * can be edited by the current user before configuring the file upload.
     * This check is performed before Extbase maps the uploaded file to avoid
     * creating unreferenced files in the file abstraction layer for unauthorized
     * requests.
     *
     * @throws PropagateResponseException If the image field is not writable or the
     *         profile is not editable for the current request.
     */
    public function initializeUploadImageAction(): void
    {
        // The order is the one `ProfileUpdateRequestService::validate()` uses for the
        // JSON actions: authentication, then configuration, then ownership. An
        // anonymous caller never gets this far today - `initializeAction()` answers
        // it with the site's access denied response, because `uploadImage` is not in
        // `JSON_ACTIONS` and therefore does not take that method's early return - but
        // this action must not depend on that to stay closed.
        if ($this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false) !== true) {
            throw new PropagateResponseException(
                $this->jsonError('authentication_required', 401),
                1777046204,
            );
        }
        if (!$this->isSpecialFieldWritable('image')) {
            throw new PropagateResponseException(
                $this->jsonError('image_not_editable', 403),
                1776760206,
            );
        }
        $profileArgument = $this->request->hasArgument('profile')
            ? $this->request->getArgument('profile')
            : null;
        $profileUid = is_array($profileArgument)
            ? (int)($profileArgument['__identity'] ?? 0)
            : (int)$profileArgument;
        // This check happens before Extbase maps the upload. An unauthorized
        // request therefore cannot create an unreferenced file in FAL.
        if ($this->profileUpdateRequestService->findEditableProfile($profileUid) === null) {
            throw new PropagateResponseException(
                $this->jsonError('profile_not_editable', 403),
                1776760201,
            );
        }
        $this->configureImageFileUpload();
    }

    /**
     * Uploads a new profile image for the given profile.
     *
     * The method validates that a new file was submitted, stores the uploaded
     * image in the configured FAL storage, and returns the updated image
     * metadata as JSON.
     *
     * @param Profile $profile The profile whose image should be updated.
     * @return ResponseInterface JSON response containing the upload result and
     *                          image metadata.
     * @throws PropagateResponseException If the upload is missing, invalid or not
     *                                   permitted.
     */
    public function uploadImageAction(Profile $profile): ResponseInterface
    {
        try {
            $replacedImageFile = $this->getPersistedProfileImageFile($profile);
            if (!$this->hasNewProfileImage($profile, $replacedImageFile)) {
                throw new PropagateResponseException(
                    $this->jsonError(
                        'image_upload_missing',
                        422,
                        'No new profile image was received.',
                    ),
                    1776760203,
                );
            }
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $imageMetadata = $this->persistUploadedProfileImage($profile, $pluginControllerActionContext);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'hasImage' => true,
                'imageAlternative' => $imageMetadata['alternative'] ?? '',
                'imageTitle' => $imageMetadata['title'] ?? '',
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Uploading the profile image failed.', [
                    'exception' => $exception,
                ]);
            throw new PropagateResponseException(
                $this->jsonError(
                    'internal_server_error',
                    500,
                    'The profile image could not be uploaded.',
                ),
                1776760204,
            );
        }
    }

    /**
     * Deletes the current profile image for the validated profile.
     *
     * Validates the incoming request payload, ensures the image field is writable,
     * and removes the existing profile image if no profile fields were submitted.
     *
     * @return ResponseInterface JSON response indicating whether the image was deleted
     * @throws PropagateResponseException When the request is invalid and a JSON error
     *         response should be propagated
     */
    public function deleteImageAction(): ResponseInterface
    {
        $requestResult = $this->profileUpdateRequestService->validate(
            $this->request,
        );
        if (!$requestResult->isValid()) {
            $this->throwJsonError(
                $requestResult->getError() ?? 'invalid_request',
                $requestResult->getStatusCode(),
            );
        }
        $payload = $requestResult->getPayload();
        $profile = $requestResult->getProfile();
        if ($payload === null || $profile === null) {
            $this->throwJsonError('internal_server_error', 500);
        }
        if (!$this->isSpecialFieldWritable('image')) {
            $this->throwJsonError('image_not_editable', 403);
        }
        if ($payload->getData() !== []) {
            $this->throwJsonError(
                'invalid_payload',
                400,
                'The image deletion payload must not contain profile fields.',
            );
        }
        try {
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $deleted = $this->deleteProfileImage($profile, $pluginControllerActionContext);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'deleted' => $deleted,
                'hasImage' => false,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Deleting the profile image failed.', [
                    'exception' => $exception,
                ]);
            $this->throwJsonError(
                'internal_server_error',
                500,
                'The profile image could not be deleted.',
            );
        }
    }

    /**
     * Checks whether the submitted profile image differs from the persisted image.
     *
     * @param Profile $profile The profile currently being processed.
     * @param File|null $persistedImageFile The previously persisted image file, if available.
     * @return bool True if a new image was submitted or the persisted image is different, otherwise false.
     */
    private function hasNewProfileImage(Profile $profile, ?File $persistedImageFile): bool
    {
        $submittedImageFile = $this->getSubmittedProfileImageFile($profile);
        if ($submittedImageFile === null) {
            return false;
        }
        return $persistedImageFile === null
            || $submittedImageFile->getUid() !== $persistedImageFile->getUid();
    }

    /**
     * Checks whether the given special field is editable.
     *
     * @param string $identifier The identifier of the special field to check.
     * @return bool True if the field exists and is not marked as read-only or disabled, otherwise false.
     */
    private function isSpecialFieldWritable(string $identifier): bool
    {
        $field = $this->academicPersonsSettings->getSpecialField($identifier);
        return $field !== null
            && !$field->validation->readOnly
            && !$field->validation->disabled;
    }

    /**
     * Persists the uploaded profile image and updates the associated metadata.
     *
     * @return array<string, string> The metadata written for the uploaded profile image.
     */
    private function persistUploadedProfileImage(Profile $profile, PluginControllerActionContextInterface $pluginControllerActionContext): array
    {
        $uploadedImageFile = $this->getSubmittedProfileImageFile($profile);
        if ($uploadedImageFile === null) {
            throw new \UnexpectedValueException('The uploaded profile image is unavailable.');
        }
        try {
            // Inside the try on purpose: Extbase has stored the upload in the file
            // storage by the time the action runs, so every refusal from here on
            // has to take the file with it - the catch below is what does that.
            $persistedProfileUid = $this->requirePersistedProfileUid($profile);
            $this->dispatchBeforeWrite(
                $pluginControllerActionContext,
                $profile,
                ProfileEditingAction::UploadImage,
                record: $profile->getImage(),
            );
            $replacedFileUids = $this->profileImageRelationWriter->replace(
                $persistedProfileUid,
                $uploadedImageFile,
            );
            // The file is new to the installation and the `sys_file_metadata` record
            // the indexer wrote for it is empty. Nothing else fills it - the profile
            // name follows the reference row, not the file - while `alternative` and
            // `title` are what an installation running EXT:filemetadata or
            // `fgtclb/file-required-attributes` reports as missing required fields.
            $this->profileImageMetadataService->initializeFileMetadata(
                $uploadedImageFile,
                $persistedProfileUid,
                $this->request,
            );
            if (!$profile->getIsTranslation()) {
                $this->dispatchProfileUpdate($profile);
            }
            $imageMetadata = $this->profileImageMetadataService->updateForProfileUid(
                $persistedProfileUid,
                $this->request,
            );
            if ($imageMetadata === null) {
                throw new \UnexpectedValueException('The uploaded profile image is unavailable.');
            }
            $this->profileImageRelationWriter->deleteUnreferencedFiles($replacedFileUids, $uploadedImageFile->getUid());
            return $imageMetadata;
        } catch (\Throwable $exception) {
            if ($this->profileImageRelationWriter->countFileReferences($uploadedImageFile) === 0) {
                $uploadedImageFile->getStorage()->deleteFile($uploadedImageFile);
            }
            throw $exception;
        }
    }

    /**
     * Returns the file produced by Extbase's upload handling for the current request.
     *
     * @param Profile $profile The profile currently being processed.
     * @return File|null The newly uploaded file, if one was mapped.
     */
    private function getSubmittedProfileImageFile(Profile $profile): ?File
    {
        return $profile->getImage()?->getOriginalResource()->getOriginalFile();
    }

    /**
     * Deletes the currently assigned image of a profile and removes the file from storage
     * when it is no longer referenced by any other record.
     *
     * The image relation is cleared before the file is removed so that stale references
     * do not remain on the profile record and other usages can be checked correctly.
     *
     * The removal is announced the same way the upload announces itself, and for the
     * same reason: the write goes through the DataHandler, not through the Extbase
     * persistence, so there is nothing to flush - only the {@see AfterProfileUpdateEvent}
     * the translation synchronisation and the slug generation listen to. It is dispatched
     * last, once the relation is gone and the file cleanup has run, so a synchronisation
     * never carries a reference that is about to be deleted into a translation. A request
     * that removed nothing changed nothing and announces nothing.
     *
     * @param Profile $profile The profile whose image should be deleted.
     * @return bool True if an image was deleted, otherwise false if no image was assigned.
     */
    private function deleteProfileImage(Profile $profile, PluginControllerActionContextInterface $pluginControllerActionContext): bool
    {
        $persistedProfileUid = $this->requirePersistedProfileUid($profile);
        $this->dispatchBeforeWrite(
            $pluginControllerActionContext,
            $profile,
            ProfileEditingAction::DeleteImage,
            record: $profile->getImage(),
        );
        $removedFileUids = $this->profileImageRelationWriter->remove($persistedProfileUid);
        if ($removedFileUids === []) {
            return false;
        }
        $this->profileImageRelationWriter->deleteUnreferencedFiles($removedFileUids);
        if (!$profile->getIsTranslation()) {
            $this->dispatchProfileUpdate($profile);
        }
        return true;
    }

    /**
     * Configures the upload handling for the profile image field.
     *
     * Sets the upload folder, maximum number of files, validates file size and
     * MIME types according to the configured settings, and registers the upload
     * configuration on the profile argument while skipping the image property in
     * the property mapping process.
     */
    private function configureImageFileUpload(): void
    {
        $profileArgument = $this->arguments->getArgument('profile');
        $fileUploadConfiguration = (new FileUploadConfiguration('image'))
            // Two, not one: FileHandlingServiceConfiguration::validateFileOperations()
            // counts uploaded + existing - deleted, so replacing the single image of a
            // profile that already has one needs room for both during the request.
            ->setMaxFiles(2)
            ->setUploadFolder(
                (string)($this->settings['editForm']['profileImage']['targetFolder'] ?? '1:/user_upload/')
            );
        $fileSizeValidator = $this->validatorResolver->createValidator(FileSizeValidator::class, [
            'maximum' => $this->resolveImageMaxFileSize(),
        ]);
        if (!$fileSizeValidator instanceof FileSizeValidator) {
            throw new \UnexpectedValueException('The file size validator is unavailable.');
        }
        $fileUploadConfiguration->addValidator($fileSizeValidator);
        // Always validated, and against the same list the file input advertises: a
        // blank setting means the default, never "accept anything".
        $allowedMimeTypes = GeneralUtility::trimExplode(
            ',',
            $this->resolveImageAllowedMimeTypes(),
            true
        );
        $mimeTypeValidator = $this->validatorResolver->createValidator(
            MimeTypeValidator::class,
            ['allowedMimeTypes' => $allowedMimeTypes],
        );
        if (!$mimeTypeValidator instanceof MimeTypeValidator) {
            throw new \UnexpectedValueException('The MIME type validator is unavailable.');
        }
        $fileUploadConfiguration->addValidator($mimeTypeValidator);
        $profileArgument->getFileHandlingServiceConfiguration()
            ->addFileUploadConfiguration($fileUploadConfiguration);
        $profileArgument->getPropertyMappingConfiguration()->skipProperties('image');
    }

    /**
     * Returns the file currently referenced as the profile image in the persisted database state.
     *
     * Reading the persisted state instead of the mapped object is intentional: the in-memory
     * profile already carries a newly uploaded file during upload processing and therefore does
     * not necessarily reflect the original stored file reference. The lookup goes through
     * {@see ProfileImageRelationWriter::findImageReference()}, the one ordered query of the
     * column, so this sees the reference the frontend renders.
     *
     * @param Profile $profile The profile whose persisted image reference should be resolved.
     * @return File|null The referenced file object or null if no persisted profile image exists.
     */
    private function getPersistedProfileImageFile(Profile $profile): ?File
    {
        $profileUid = $this->resolvePersistedProfileUid($profile);
        if ($profileUid === null) {
            return null;
        }
        $reference = $this->profileImageRelationWriter->findImageReference($profileUid);
        if ($reference === null) {
            return null;
        }
        try {
            return $this->resourceFactory->getFileObject($reference['uid_local']);
        } catch (FileDoesNotExistException) {
            return null;
        }
    }

    /**
     * Resolves an Extbase language overlay to the uid of the persisted profile row a
     * write of the current site language has to address, or null when there is none
     * the visitor may write - see {@see LocalizedProfileUidResolver}.
     */
    private function resolvePersistedProfileUid(Profile $profile): ?int
    {
        $profileUid = (int)($profile->getUid() ?? 0);
        $siteLanguage = $this->request->getAttribute('language');
        $languageId = $siteLanguage instanceof SiteLanguage ? $siteLanguage->getLanguageId() : 0;
        return $this->localizedProfileUidResolver->resolve($profileUid, $languageId);
    }

    /**
     * The persisted profile row of the current site language, or a 404 when the
     * record the editor addresses is gone or hidden in that language.
     */
    private function requirePersistedProfileUid(Profile $profile): int
    {
        // The image relation is written through the DataHandler, and a frontend
        // request acting in a non-live workspace would produce workspace versions
        // of records the visitor never sees published. The record synchronization
        // refuses that case (ACE-492); the editor refuses it too rather than
        // writing something nobody asked for.
        if ($this->dataHandlerExecutionContext->isFrontendRequestInWorkspace()) {
            $this->throwJsonError(
                'workspace_not_supported',
                409,
                'The profile image cannot be edited from a workspace preview.',
            );
        }
        $profileUid = $this->resolvePersistedProfileUid($profile);
        if ($profileUid === null) {
            $this->throwJsonError(
                'profile_not_found',
                404,
                'The profile does not exist in the requested language.',
            );
        }
        return $profileUid;
    }

    private function getCurrentContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->request->getAttribute('currentContentObject');
    }

    /**
     * Offers a write the editor accepted to the listeners of
     * {@see BeforeProfileEditingWriteEvent}, right before anything of it is stored.
     *
     * A refused write is answered with 422 `write_refused` and the listener's reason.
     * Fields a listener replaced are returned for the caller to validate again, exactly
     * as submitted ones, so that replaced fields can neither carry an invalid or
     * unsanitised value nor write a field the configuration or the synchronisation
     * locks.
     *
     * @param array<string, mixed> $fields The values of the write in the shape the editor submits them.
     * @return array<string, mixed>|null The replaced fields, or null when no listener replaced them.
     */
    private function dispatchBeforeWrite(
        PluginControllerActionContextInterface $pluginControllerActionContext,
        Profile $profile,
        ProfileEditingAction $action,
        ?string $sectionIdentifier = null,
        ?AbstractEntity $record = null,
        array $fields = [],
        ?Contract $contract = null,
    ): ?array {
        $event = new BeforeProfileEditingWriteEvent(
            $profile,
            $action,
            $sectionIdentifier,
            $record,
            $fields,
            $pluginControllerActionContext,
            $contract,
        );
        $this->eventDispatcher->dispatch($event);
        if ($event->isRefused()) {
            $this->throwJsonError('write_refused', 422, $event->getReason());
        }
        return $event->getFields() !== $fields ? $event->getFields() : null;
    }

    /**
     * Builds the form data of a profile write from the payload and validates it, or
     * answers the request with the error.
     *
     * @param list<string> $managedProperties
     */
    private function createValidatedProfileFormData(
        PluginControllerActionContext $pluginControllerActionContext,
        Profile $profile,
        ProfileUpdatePayload $payload,
        array $managedProperties,
        string $validationMessage,
    ): ProfileFormData {
        try {
            $profileFormData = $this->profileUpdateValidationService->createFormData(
                $pluginControllerActionContext,
                $profile,
                $payload,
                $managedProperties,
            );
        } catch (\UnexpectedValueException $exception) {
            // Only this call describes the submitted payload in its message, so
            // only this call may relay one. Anything the persisting code of the
            // action raises is logged and answered as an internal error instead.
            $this->throwJsonError(
                'invalid_profile_data',
                422,
                $exception->getMessage(),
            );
        }
        //@todo: Edit validator for links
        $validationResult = $this->profileUpdateValidationService->validate(
            $profileFormData,
        );
        if ($validationResult->hasErrors()) {
            $errors = [];
            foreach ($validationResult->getFlattenedErrors() as $propertyPath => $propertyErrors) {
                foreach ($propertyErrors as $propertyError) {
                    $errors[$propertyPath][] = $propertyError->getMessage();
                }
            }
            $this->throwJsonError(
                'validation_failed',
                422,
                $validationMessage,
                $errors,
            );
        }
        return $profileFormData;
    }

    /**
     * Checks the project fields a profile update names before anything else of it:
     * a column the editor must not use is answered with 500 and the message naming
     * it, as it is a mistake of the installation rather than of the request, and no
     * validation of its value may answer first.
     */
    private function assertSubmittedProjectFieldsAreUsable(ProfileUpdatePayload $payload): void
    {
        try {
            $this->projectProfileFields->assertUsable(
                array_intersect_key($this->projectProfileFields->getFields(), $payload->getData()),
            );
        } catch (InvalidProjectProfileFieldException $exception) {
            $this->logManager->getLogger(self::class)->error($exception->getMessage(), ['exception' => $exception]);
            $this->throwJsonError('invalid_project_field', 500, $exception->getMessage());
        }
    }

    /**
     * Resolves the row the values of the project fields of a profile update go to,
     * before anything is written.
     *
     * @return int|null The uid of the row of the edited language, or null when the
     *                  update carries no project field value to write.
     */
    private function resolveProjectFieldRecord(Profile $profile, ProfileFormData $profileFormData): ?int
    {
        if ($profileFormData->getCustomValues() === []) {
            return null;
        }
        // Written through the DataHandler, like the visibility: a frontend request acting
        // in a non-live workspace would produce a version of the record nobody asked for.
        if ($this->dataHandlerExecutionContext->isFrontendRequestInWorkspace()) {
            $this->throwJsonError(
                'workspace_not_supported',
                409,
                'The project fields of the profile cannot be edited from a workspace preview.',
            );
        }
        $recordUid = $this->resolvePersistedProfileUid($profile);
        if ($recordUid === null) {
            $this->throwJsonError(
                'profile_not_found',
                404,
                'The profile does not exist in the requested language.',
            );
        }
        return $recordUid;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertSkipSyncPayload(array $data): void
    {
        if (
            array_keys($data) !== ['skipSync']
            || !is_bool($data['skipSync'])
        ) {
            $this->throwJsonError(
                'invalid_payload',
                400,
                'The payload must contain exactly one boolean skipSync value.',
            );
        }
    }

    /**
     * Announces a change of the editor, with the site of the request the change was
     * made in.
     */
    private function dispatchProfileUpdate(Profile $profile): void
    {
        $site = $this->request->getAttribute('site');
        $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent(
            $profile,
            $site instanceof Site ? $site : null,
            ProfileUpdateOrigin::FrontendEditing,
        ));
    }

    /**
     * Persists all pending changes before announcing the updated profile aggregate.
     *
     * @param (\Closure(): void)|null $beforeAnnouncement a write of its own, run after the
     *                                                     Extbase write and before the
     *                                                     announcement, whose result the
     *                                                     listeners have to see
     */
    private function persistAndDispatchProfileUpdate(?Profile $profile, ?\Closure $beforeAnnouncement = null): void
    {
        $this->persistenceManager->persistAll();
        if ($beforeAnnouncement !== null) {
            $beforeAnnouncement();
        }
        if ($profile === null || $profile->getUid() === null || $profile->getIsTranslation()) {
            return;
        }
        $this->dispatchProfileUpdate($profile);
    }

    /**
     * @param AbstractEntity[] $items
     * @param int<1,max> $targetUid
     */
    private function sortItems(
        array $items,
        int $targetUid,
        ListSortingMode $mode,
    ): ListSortingProcess {
        return $this->listSortingService->sort($items, $targetUid, $mode);
    }
}
