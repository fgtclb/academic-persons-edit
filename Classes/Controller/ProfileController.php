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
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersons\Service\ProfileImageMetadataService;
use FGTCLB\AcademicPersons\Service\ProfileImageRelationWriter;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use FGTCLB\AcademicPersonsEdit\Event\ProfileEditingAction;
use FGTCLB\AcademicPersonsEdit\Service\EditorEndpoint;
use FGTCLB\AcademicPersonsEdit\Service\EditorLabels;
use FGTCLB\AcademicPersonsEdit\Service\EditorWrites;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use FGTCLB\AcademicPersonsEdit\Service\Exception\InvalidProjectProfileFieldException;
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
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\FileUploadConfiguration;
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
 * @internal This controller is intentionally internal to `EXT:academic_persons_edit`
 *           and is not part of the public API.
 */
final class ProfileController extends ActionController
{
    use GetCurrentContentRecordMethodTrait;

    /**
     * The actions of this controller answering as JSON. They validate and answer
     * their own request, so {@see self::initializeAction()} does not send them
     * through the HTML access denied flow of the plugin. The document and contact
     * endpoints are served by {@see DocumentController} and
     * {@see ContractContactController}.
     *
     * @var list<string>
     */
    private const JSON_ACTIONS = [
        'update',
        'updateSkipSync',
        'updateVisibility',
        'deleteImage',
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
        private readonly AcademicPersonsSettings $academicPersonsSettings,
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
        private readonly ManagedRecordLocks $managedRecordLocks,
        private readonly ProjectProfileFields $projectProfileFields,
        private readonly EditorLabels $editorLabels,
        private readonly EditorEndpoint $editorEndpoint,
        private readonly EditorWrites $editorWrites,
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
            $this->editorEndpoint->assertRequestWasSentByTheEditor($this->request);
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
                $this->editorEndpoint->jsonError(
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
            $replacedFields = $this->editorWrites->dispatchBeforeWrite(
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
            $this->editorWrites->persistAndDispatchProfileUpdate(
                $this->request,
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
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile failed.', [
                    'exception' => $exception,
                ]);
            $this->editorEndpoint->throwJsonError(
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
        $this->assertSkipSyncPayload($payload->getData());
        if (!$this->isSpecialFieldWritable('skipSync')) {
            // The switch is the whole request, so a locked switch is refused
            // rather than ignored as a locked field of the profile form is.
            $this->editorEndpoint->throwJsonError(
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
            $replacedFields = $this->editorWrites->dispatchBeforeWrite(
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
            $this->editorWrites->persistAndDispatchProfileUpdate($this->request, $updatedProfile);
            return new JsonResponse([
                'success' => true,
                'profile' => $updatedProfile->getUid(),
                'skipSync' => $updatedProfile->getSkipSync(),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile synchronization flag failed.', [
                    'exception' => $exception,
                ]);
            $this->editorEndpoint->throwJsonError(
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
        if (!$this->isSpecialFieldWritable('hidden')) {
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->throwJsonError(
                'invalid_payload',
                400,
                'The payload must contain exactly one boolean hidden value.',
            );
        }
        // Written through the DataHandler, like the image: a frontend request acting
        // in a non-live workspace would produce a version of the record nobody asked
        // for, see requirePersistedProfileUid().
        if ($this->dataHandlerExecutionContext->isFrontendRequestInWorkspace()) {
            $this->editorEndpoint->throwJsonError(
                'workspace_not_supported',
                409,
                'The visibility of the profile cannot be changed from a workspace preview.',
            );
        }
        try {
            $pluginControllerActionContext = new PluginControllerActionContext($this->request, $this->settings);
            $this->editorWrites->dispatchBeforeWrite(
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
                $this->editorWrites->dispatchProfileUpdate($this->request, $profile);
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'hidden' => $hidden,
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
        } catch (EditorRequestException $exception) {
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Updating the profile visibility failed.', [
                    'exception' => $exception,
                ]);
            $this->editorEndpoint->throwJsonError(
                'internal_server_error',
                500,
                'The visibility of the profile could not be updated.',
            );
        }
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
                $this->editorEndpoint->jsonError('authentication_required', 401),
                1777046204,
            );
        }
        if (!$this->isSpecialFieldWritable('image')) {
            throw new PropagateResponseException(
                $this->editorEndpoint->jsonError('image_not_editable', 403),
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
                $this->editorEndpoint->jsonError('profile_not_editable', 403),
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
                    $this->editorEndpoint->jsonError(
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
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Uploading the profile image failed.', [
                    'exception' => $exception,
                ]);
            throw new PropagateResponseException(
                $this->editorEndpoint->jsonError(
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
        if (!$this->isSpecialFieldWritable('image')) {
            $this->editorEndpoint->throwJsonError('image_not_editable', 403);
        }
        if ($payload->getData() !== []) {
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->answerRefusal($exception);
        } catch (\Throwable $exception) {
            $this->logManager
                ->getLogger(self::class)
                ->error('Deleting the profile image failed.', [
                    'exception' => $exception,
                ]);
            $this->editorEndpoint->throwJsonError(
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
            $this->editorWrites->dispatchBeforeWrite(
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
                $this->editorWrites->dispatchProfileUpdate($this->request, $profile);
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
        $this->editorWrites->dispatchBeforeWrite(
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
            $this->editorWrites->dispatchProfileUpdate($this->request, $profile);
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
            $this->editorEndpoint->throwJsonError(
                'workspace_not_supported',
                409,
                'The profile image cannot be edited from a workspace preview.',
            );
        }
        $profileUid = $this->resolvePersistedProfileUid($profile);
        if ($profileUid === null) {
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->throwJsonError('invalid_project_field', 500, $exception->getMessage());
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
            $this->editorEndpoint->throwJsonError(
                'workspace_not_supported',
                409,
                'The project fields of the profile cannot be edited from a workspace preview.',
            );
        }
        $recordUid = $this->resolvePersistedProfileUid($profile);
        if ($recordUid === null) {
            $this->editorEndpoint->throwJsonError(
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
            $this->editorEndpoint->throwJsonError(
                'invalid_payload',
                400,
                'The payload must contain exactly one boolean skipSync value.',
            );
        }
    }

}
