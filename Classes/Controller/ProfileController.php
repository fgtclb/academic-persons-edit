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
use FGTCLB\AcademicBase\Settings\Validation;
use FGTCLB\AcademicPersons\Domain\Model\Address;
use FGTCLB\AcademicPersons\Domain\Model\Contract;
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\FunctionType;
use FGTCLB\AcademicPersons\Domain\Model\Location;
use FGTCLB\AcademicPersons\Domain\Model\OrganisationalUnit;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Domain\Repository\AddressRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\EmailRepository;
use FGTCLB\AcademicPersons\Domain\Repository\FunctionTypeRepository;
use FGTCLB\AcademicPersons\Domain\Repository\LocationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\OrganisationalUnitRepository;
use FGTCLB\AcademicPersons\Domain\Repository\PhoneNumberRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersons\Service\ProfileImageMetadataService;
use FGTCLB\AcademicPersons\Service\ProfileImageRelationWriter;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersons\Settings\ContractContactField;
use FGTCLB\AcademicPersons\Settings\ContractContactSection;
use FGTCLB\AcademicPersons\Settings\ContractField;
use FGTCLB\AcademicPersons\Settings\DocumentSection;
use FGTCLB\AcademicPersons\Types\EmailAddressTypes;
use FGTCLB\AcademicPersons\Types\PhoneNumberTypes;
use FGTCLB\AcademicPersons\Types\PhysicalAddressTypes;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\LocalizedProfileUidResolver;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileFieldOptionsService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerInterface;
use FGTCLB\AcademicPersonsEdit\Service\ProfileSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateRequestService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateValidationService;
use FGTCLB\AcademicPersonsEdit\Service\RichTextCharacterCounter;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\Locale;
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
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\FileSizeValidator;
use TYPO3\CMS\Extbase\Validation\Validator\MimeTypeValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * Controller for profile editing via JSON-based frontend requests.
 *
 * The controller handles profile actions such as validation,
 * updates and data retrieval without relying on the shared HTML authentication
 * flow used by regular Extbase pages.
 *
 * @phpstan-type DocumentFieldDefinition array{
 *      name: string,
 *      label: string,
 *      type: string,
 *      required: bool,
 *      readOnly: bool,
 *      disabled: bool,
 *      richText: bool,
 *      characterLimit: int,
 *      autocomplete: string,
 *      placeholder: string,
 *      helptext: string,
 *      columnClass: string,
 *      compactCheckbox: bool,
 *      min: int|null,
 *      max: int|null,
 *      step: int|null,
 *      value: mixed,
 *      displayValue: string,
 *      options: list<array{value: int|string, label: string}>
 *  }
 * @phpstan-type ContractContactRecord Address|Email|PhoneNumber
 * @phpstan-type ContractContactItem array{
 *      uid: int,
 *      sorting: int,
 *      hidden: bool,
 *      values: array<string, mixed>,
 *      display: array<string, string>,
 *      summary: list<array{label: string, value: string}>
 *  }
 * @phpstan-type ContractContactSectionDefinition array{
 *      identifier: string,
 *      label: string,
 *      singularLabel: string,
 *      items: list<ContractContactItem>
 *  }
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

    /**
     * The bounds of the three timeline year fields. They are the `range` of
     * the TCA columns of `tx_academicpersons_domain_model_profile_information`
     * and reach the browser as the `min` and `max` of the number control.
     */
    private const YEAR_MIN = 0;
    private const YEAR_MAX = 9999;

    /**
     * The two contract dates are plain text controls, not `<input type="date">`.
     *
     * The native control was tried and reverted: it forces the locale of the
     * browser rather than the one of the site, it cannot be styled with the
     * rest of the editor, and its calendar is not the date picker this editor
     * is meant to get. A picker is a feature of its own and is deliberately
     * not shipped yet - until it is, the control is the one the previous
     * editor had: a text input showing `d.m.Y` behind the hint below.
     *
     * `DOCUMENT_DATE_FORMAT` is what the control shows and what an editor
     * types. `DOCUMENT_DATE_ISO_FORMAT` is what the endpoint answered and
     * accepted while the control was a native one; it stays accepted, so a
     * client written against that shape keeps working.
     */
    private const DOCUMENT_DATE_FORMAT = 'd.m.Y';
    private const DOCUMENT_DATE_ISO_FORMAT = 'Y-m-d';
    private const DOCUMENT_DATE_PLACEHOLDER = 'dd.mm.yyyy';

    public function __construct(
        private readonly Context $context,
        private readonly PersistenceManager $persistenceManager,
        private readonly AcademicPersonsSettings $academicPersonsSettings,
        private readonly ListSortingService $listSortingService,
        private readonly ProfileFactory $profileFactory,
        private readonly ProfileRepository $profileRepository,
        private readonly CountryProvider $countryProvider,
        private readonly ErrorController $errorController,
        private readonly LogManager $logManager,
        private readonly ResourceFactory $resourceFactory,
        private readonly ProfileImageMetadataService $profileImageMetadataService,
        private readonly ProfileUpdateRequestService $profileUpdateRequestService,
        private readonly ProfileUpdateValidationService $profileUpdateValidationService,
        private readonly LocalizedProfileUidResolver $localizedProfileUidResolver,
        private readonly ProfileImageRelationWriter $profileImageRelationWriter,
        private readonly DataHandlerExecutionContext $dataHandlerExecutionContext,
        private readonly ProfileFieldOptionsService $profileFieldOptionsService,
        private readonly ProfileSectionProvider $profileSectionProvider,
        private readonly ProfileDocumentSectionProvider $profileDocumentSectionProvider,
        private readonly ContractFactory $contractFactory,
        private readonly ContractRepository $contractRepository,
        private readonly AddressFactory $addressFactory,
        private readonly AddressRepository $addressRepository,
        private readonly EmailFactory $emailFactory,
        private readonly EmailRepository $emailRepository,
        private readonly PhoneNumberFactory $phoneNumberFactory,
        private readonly PhoneNumberRepository $phoneNumberRepository,
        private readonly PhysicalAddressTypes $physicalAddressTypes,
        private readonly EmailAddressTypes $emailAddressTypes,
        private readonly PhoneNumberTypes $phoneNumberTypes,
        private readonly ProfileInformationFactory $profileInformationFactory,
        private readonly ProfileInformationRepository $profileInformationRepository,
        private readonly FunctionTypeRepository $functionTypeRepository,
        private readonly OrganisationalUnitRepository $organisationalUnitRepository,
        private readonly LocationRepository $locationRepository,
        private readonly ProfileRichTextSanitizerInterface $profileRichTextSanitizer,
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
        $profiles = $this->profileRepository->findByFrontendUser(
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
        $this->view->assignMultiple([
            'data' => $this->getCurrentContentObjectRenderer()?->data,
            'record' => $this->getCurrentContentRecord($this->getCurrentContentObjectRenderer()),
            'profile' => $profile,
            'profileSections' => $this->profileSectionProvider->getSections(),
            'specialFields' => $this->profileSectionProvider->getSpecialFields(),
            'profileFieldOptions' => $this->profileFieldOptionsService->getOptionsByField(),
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
     * The locale a rendered date is formatted in: the site language's own, so the
     * editor and the public detail view of the same profile agree.
     */
    private function resolveSiteLocale(): Locale
    {
        $siteLanguage = $this->request->getAttribute('language');
        return $siteLanguage instanceof SiteLanguage ? $siteLanguage->getLocale() : new Locale();
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
                $language = LocalizationUtility::translate(
                    'list.language.all',
                    'academic_persons_edit',
                ) ?? 'All languages';
            } else {
                $language = $languageLabels[$languageUid]
                    ?? LocalizationUtility::translate(
                        'list.language.unknown',
                        'academic_persons_edit',
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
            $pluginControllerActionContext = new PluginControllerActionContext(
                $this->request,
                $this->settings,
            );
            try {
                $profileFormData = $this->profileUpdateValidationService->createFormData(
                    $pluginControllerActionContext,
                    $profile,
                    $payload,
                );
            } catch (\UnexpectedValueException $exception) {
                // Only this call describes the submitted payload in its message, so
                // only this call may relay one. Anything the persisting code below
                // raises is logged and answered as an internal error instead.
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
                    'The submitted profile data is invalid.',
                    $errors,
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
                'data' => $this->profileUpdateValidationService->getNormalizedData(
                    $profileFormData,
                    $payload,
                ),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
        $data = $payload->getData();
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
        try {
            $pluginControllerActionContext = new PluginControllerActionContext(
                $this->request,
                $this->settings,
            );
            try {
                $profileFormData = $this->profileUpdateValidationService->createFormData(
                    $pluginControllerActionContext,
                    $profile,
                    $payload,
                );
            } catch (\UnexpectedValueException $exception) {
                // Only this call describes the submitted payload in its message, so
                // only this call may relay one. Anything the persisting code below
                // raises is logged and answered as an internal error instead.
                $this->throwJsonError(
                    'invalid_profile_data',
                    422,
                    $exception->getMessage(),
                );
            }
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
                    'The submitted synchronization setting is invalid.',
                    $errors,
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
                : $this->findDocumentRecord($profile, $section, $recordUid);
            if ($recordUid !== null && $record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $response = [
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'kind' => $section->isContractSection() ? 'contract' : 'profileInformation',
                'record' => $record?->getUid(),
                'fields' => $this->getDocumentFieldDefinitions($section, $record),
            ];
            if ($record instanceof Contract) {
                $response['contactSections'] = $this->getContractContactSections($record);
            }
            return new JsonResponse($response);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $normalizedFields = $this->normalizeAndValidateDocumentFields(
                $section,
                $fields,
                true,
            );
            if ($section->isContractSection()) {
                $record = $this->contractFactory->createFromFormData(
                    $section->validationSet,
                    $profile,
                    $this->createContractFormData($normalizedFields),
                );
                $record->setSorting($this->getNextSortingValue($this->getDocumentRecords($profile, $section)));
                $record->setPid((int)$profile->getPid());
                $this->contractRepository->add($record);
            } else {
                $record = $this->profileInformationFactory->createFromFormData(
                    $section->validationSet,
                    $profile,
                    $this->createProfileInformationFormData($section, $normalizedFields),
                );
                $record->setSorting($this->getNextSortingValue($this->getDocumentRecords($profile, $section)));
                $record->setPid((int)$profile->getPid());
                $this->profileInformationRepository->add($record);
            }
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'section' => $section->identifier,
                'item' => $this->serializeDocumentItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $record = $this->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $normalizedFields = $this->normalizeAndValidateDocumentFields(
                $section,
                $this->getSubmittedDocumentFields($data),
                false,
            );
            if ($record instanceof Contract) {
                $this->contractRepository->update(
                    $this->contractFactory->updateFromFormData(
                        $section->validationSet,
                        $record,
                        $this->createContractFormData($normalizedFields),
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
                'item' => $this->serializeDocumentItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $record = $this->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
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
                'item' => $this->serializeDocumentItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $record = $this->findDocumentRecord($profile, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('document_not_found', 404);
            }
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
            if (array_key_exists('order', $data)) {
                $this->assertDocumentPayload($data, ['section', 'order'], ['section', 'order']);
                $this->assertDocumentActionAllowed($section, 'reorder');
                $process = $this->reorderDocumentRecords(
                    $this->getDocumentRecords($profile, $section),
                    $this->getSubmittedDocumentOrder($data),
                );
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
            if ($this->findDocumentRecord($profile, $section, $recordUid) === null) {
                $this->throwJsonError('document_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $this->assertDocumentActionAllowed($section, $direction);
            $records = $this->getDocumentRecords($profile, $section);
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
                : $this->findContractContactRecord($contract, $section, $recordUid);
            if ($recordUid !== null && $record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'record' => $record?->getUid(),
                'title' => $this->getContractContactSingularLabel($section),
                'fields' => $this->getContractContactFieldDefinitions($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $normalizedFields = $this->normalizeAndValidateContractContactFields(
                $section,
                $this->getSubmittedDocumentFields($data),
                true,
            );
            $record = $this->createContractContactRecord($contract, $section, $normalizedFields);
            $record->setSorting($this->getNextContractContactSortingValue($contract, $section));
            $record->setPid((int)$contract->getPid());
            $this->addContractContactRecord($record);
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->serializeContractContactItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $record = $this->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $normalizedFields = $this->normalizeAndValidateContractContactFields(
                $section,
                $this->getSubmittedDocumentFields($data),
                false,
            );
            $this->updateContractContactRecord($section, $record, $normalizedFields);
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->serializeContractContactItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $record = $this->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $this->removeContractContactRecord($record);
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
            $record = $this->findContractContactRecord($contract, $section, $recordUid);
            if ($record === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $hidden = $data['hidden'];
            if (!is_bool($hidden)) {
                $this->throwJsonError('invalid_payload', 400, 'The hidden flag must be true or false.');
            }
            $record->setHidden($hidden);
            $this->updateContractContactVisibility($record);
            $this->persistAndDispatchProfileUpdate($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'contract' => $contract->getUid(),
                'section' => $section->identifier,
                'item' => $this->serializeContractContactItem($section, $record),
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            if ($this->findContractContactRecord($contract, $section, $recordUid) === null) {
                $this->throwJsonError('contract_contact_not_found', 404);
            }
            $direction = $data['direction'];
            if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
                $this->throwJsonError('invalid_payload', 400, 'The direction must be up or down.');
            }
            $process = $this->sortItems(
                $this->getContractContactRecords($contract, $section),
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
        $contract = $this->findDocumentRecord($profile, $contractSection, $contractUid);
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
     * Finds a document record for the given profile and section by its UID.
     *
     * @param Profile $profile The profile whose document records should be searched.
     * @param DocumentSection $section The document section to inspect.
     * @param int $recordUid The UID of the record to find.
     * @return Contract|ProfileInformation|null The matching record, or null if no record with the given UID exists.
     */
    private function findDocumentRecord(
        Profile $profile,
        DocumentSection $section,
        int $recordUid,
    ): Contract|ProfileInformation|null {
        foreach ($this->getDocumentRecords($profile, $section) as $record) {
            if ((int)$record->getUid() === $recordUid) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Returns all document records belonging to the provided profile and section.
     *
     * For contract sections, the method returns the profile's contract records.
     * For all other sections, it returns the corresponding profile information records.
     *
     * @param Profile $profile The profile whose records should be retrieved.
     * @param DocumentSection $section The section whose records should be inspected.
     * @return list<Contract|ProfileInformation> The matching records, filtered to valid record instances.
     */
    private function getDocumentRecords(Profile $profile, DocumentSection $section): array
    {
        // Including the hidden ones: the editor is where a hidden record is shown
        // again, so it must list what the public views leave out.
        if ($section->isContractSection()) {
            return array_values(array_filter(
                $this->contractRepository->findByProfileIncludingHidden($profile)->toArray(),
                static fn(mixed $record): bool => $record instanceof Contract,
            ));
        }
        return array_values(array_filter(
            $this->profileInformationRepository
                ->findByProfileAndTypeIncludingHidden($profile, $section->type)
                ->toArray(),
            static fn(mixed $record): bool => $record instanceof ProfileInformation,
        ));
    }

    /**
     * Determines the next sorting value for a document record by taking the highest
     * existing sort value and adding a new increment.
     *
     * @param list<Contract|ProfileInformation> $records The records for which the next sorting position should be calculated.
     * @return int The next available sorting value, offset by 10 from the current maximum.
     */
    private function getNextSortingValue(array $records): int
    {
        $maximum = 0;
        foreach ($records as $record) {
            $maximum = max($maximum, $record->getSorting());
        }
        return $maximum + 10;
    }

    /**
     * @return list<ContractContactSectionDefinition>
     */
    private function getContractContactSections(Contract $contract): array
    {
        $sections = [];
        foreach ($this->academicPersonsSettings->contractContactSections as $section) {
            if (!in_array($section->identifier, ['physicalAddresses', 'emailAddresses', 'phoneNumbers'], true)) {
                continue;
            }
            $sections[] = [
                'identifier' => $section->identifier,
                'label' => $this->translateContractContactLabel('contract.' . $section->identifier),
                'singularLabel' => $this->getContractContactSingularLabel($section),
                'items' => array_map(
                    fn(Address|Email|PhoneNumber $record): array => $this->serializeContractContactItem(
                        $section,
                        $record,
                    ),
                    $this->getContractContactRecords($contract, $section),
                ),
            ];
        }
        return $sections;
    }

    private function getContractContactSingularLabel(ContractContactSection $section): string
    {
        $key = match ($section->identifier) {
            'physicalAddresses' => 'contract.physicalAddress',
            'emailAddresses' => 'contract.emailAddress',
            'phoneNumbers' => 'contract.phoneNumber',
            default => $section->identifier,
        };
        return $this->translateContractContactLabel($key);
    }

    private function translateContractContactLabel(string $key): string
    {
        return LocalizationUtility::translate($key, 'academic_persons_edit') ?? $key;
    }

    /**
     * @return list<ContractContactRecord>
     */
    private function getContractContactRecords(
        Contract $contract,
        ContractContactSection $section,
    ): array {
        $records = match ($section->identifier) {
            'physicalAddresses' => $this->addressRepository->findByContractIncludingHidden((int)$contract->getUid()),
            'emailAddresses' => $this->emailRepository->findByContractIncludingHidden((int)$contract->getUid()),
            'phoneNumbers' => $this->phoneNumberRepository->findByContractIncludingHidden((int)$contract->getUid()),
            default => [],
        };
        return array_values(array_filter(
            is_array($records) ? $records : $records->toArray(),
            static fn(mixed $record): bool => $record instanceof Address
                || $record instanceof Email
                || $record instanceof PhoneNumber,
        ));
    }

    private function findContractContactRecord(
        Contract $contract,
        ContractContactSection $section,
        int $recordUid,
    ): Address|Email|PhoneNumber|null {
        foreach ($this->getContractContactRecords($contract, $section) as $record) {
            if ((int)$record->getUid() === $recordUid) {
                return $record;
            }
        }
        return null;
    }

    /**
     * @param ContractContactRecord|null $record
     * @return list<DocumentFieldDefinition>
     */
    private function getContractContactFieldDefinitions(
        ContractContactSection $section,
        Address|Email|PhoneNumber|null $record,
    ): array {
        $definitions = [];
        foreach ($section->fields as $field) {
            $options = $this->getContractContactOptions($section, $field);
            $definition = $this->createDocumentField(
                $this->getContractContactTranslationPrefix($section),
                $field->propertyName,
                $this->getContractContactInputType($field),
                $record === null ? '' : $this->getContractContactPropertyValue($record, $field->propertyName),
                $options,
            );
            $definition['required'] = $field->validation->required;
            $definition['readOnly'] = $field->validation->readOnly;
            $definition['disabled'] = $field->validation->disabled;
            $definition['characterLimit'] = $field->validation->characterLimit;
            $definition['autocomplete'] = $field->autocomplete;
            $definition['helptext'] = $this->getContractContactHelptext($field);
            $definitions[] = $definition;
        }
        return $definitions;
    }

    private function getContractContactTranslationPrefix(ContractContactSection $section): string
    {
        return match ($section->identifier) {
            'physicalAddresses' => 'physicalAddress',
            'emailAddresses' => 'emailAddress',
            'phoneNumbers' => 'phoneNumber',
            default => 'contract',
        };
    }

    private function getContractContactInputType(ContractContactField $field): string
    {
        return match ($field->renderType) {
            'select' => 'select',
            'email' => 'email',
            'phone' => 'tel',
            default => 'text',
        };
    }

    private function getContractContactPropertyValue(
        Address|Email|PhoneNumber $record,
        string $propertyName,
    ): string {
        $getter = 'get' . ucfirst($propertyName);
        if (!is_callable([$record, $getter])) {
            return '';
        }
        $value = $record->{$getter}();
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getContractContactOptions(
        ContractContactSection $section,
        ContractContactField $field,
    ): array {
        if ($field->propertyName === 'country') {
            return $this->getCountryOptions();
        }
        return $field->renderType === 'select'
            ? $this->getContractContactTypeOptions($section)
            : [];
    }

    private function getContractContactHelptext(ContractContactField $field): string
    {
        if ($field->helptext === '') {
            return '';
        }
        return LocalizationUtility::translate($field->helptext) ?? $field->helptext;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getCountryOptions(): array
    {
        $options = [];
        foreach ($this->countryProvider->getAll() as $country) {
            $options[] = [
                'value' => $country->getAlpha2IsoCode(),
                'label' => LocalizationUtility::translate($country->getLocalizedNameLabel())
                    ?? $country->getName(),
            ];
        }
        usort(
            $options,
            static fn(array $left, array $right): int => strnatcasecmp($left['label'], $right['label'])
                ?: strcmp($left['value'], $right['value']),
        );
        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getContractContactTypeOptions(ContractContactSection $section): array
    {
        $configuredTypes = match ($section->identifier) {
            'physicalAddresses' => $this->physicalAddressTypes->getAll(),
            'emailAddresses' => $this->emailAddressTypes->getAll(),
            'phoneNumbers' => $this->phoneNumberTypes->getAll(),
            default => [],
        };
        $options = [];
        foreach ($configuredTypes as $value => $label) {
            if ($value === '') {
                continue;
            }
            $options[] = [
                'value' => $value,
                'label' => LocalizationUtility::translate($label, 'academic_persons') ?? $label,
            ];
        }
        return $options;
    }

    /**
     * @param ContractContactRecord $record
     * @return ContractContactItem
     */
    private function serializeContractContactItem(
        ContractContactSection $section,
        Address|Email|PhoneNumber $record,
    ): array {
        $values = [];
        $display = [];
        foreach ($this->getContractContactFieldDefinitions($section, $record) as $field) {
            $values[$field['name']] = $field['value'];
            $display[$field['name']] = $field['displayValue'];
        }
        return [
            'uid' => (int)$record->getUid(),
            'sorting' => $record->getSorting(),
            'hidden' => $record->getHidden(),
            'values' => $values,
            'display' => $display,
            'summary' => $this->getContractContactSummary($section, $display),
        ];
    }

    /**
     * @param array<string, string> $display
     * @return list<array{label: string, value: string}>
     */
    private function getContractContactSummary(ContractContactSection $section, array $display): array
    {
        $prefix = $this->getContractContactTranslationPrefix($section);
        $fields = match ($section->identifier) {
            'physicalAddresses' => [
                'street' => trim(($display['street'] ?? '') . ' ' . ($display['streetNumber'] ?? '')),
                'city' => trim(($display['zip'] ?? '') . ' ' . ($display['city'] ?? '')),
                'country' => $display['country'] ?? '',
            ],
            'emailAddresses' => [
                'type' => $display['type'] ?? '',
                'email' => $display['email'] ?? '',
            ],
            'phoneNumbers' => [
                'type' => $display['type'] ?? '',
                'phoneNumber' => $display['phoneNumber'] ?? '',
            ],
            default => [],
        };
        $summary = [];
        foreach ($fields as $name => $value) {
            $summary[] = [
                'label' => LocalizationUtility::translate(
                    $prefix . '.' . $name . '.label',
                    'academic_persons_edit',
                ) ?? $name,
                'value' => $value,
            ];
        }
        return $summary;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function normalizeAndValidateContractContactFields(
        ContractContactSection $section,
        array $fields,
        bool $creating,
    ): array {
        $definitionsByName = [];
        foreach ($this->getContractContactFieldDefinitions($section, null) as $definition) {
            $definitionsByName[$definition['name']] = $definition;
        }
        $errors = [];
        $normalized = [];
        foreach ($fields as $name => $value) {
            $definition = $definitionsByName[$name] ?? null;
            if ($definition === null || $definition['readOnly'] || $definition['disabled']) {
                $errors[$name][] = 'This field cannot be changed.';
                continue;
            }
            try {
                $normalized[$name] = $this->normalizeContractContactFieldValue($definition, $value);
            } catch (\UnexpectedValueException $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }
        foreach ($definitionsByName as $name => $definition) {
            if ($creating && $definition['required'] && !array_key_exists($name, $fields)) {
                $errors[$name][] = 'This field is required.';
            }
            if (!array_key_exists($name, $normalized)) {
                continue;
            }
            $validation = $section->validationSet->get($name);
            if ($validation !== null) {
                $this->validateDocumentField($name, $normalized[$name], $validation, $errors);
            }
        }
        if ($errors !== []) {
            $this->throwJsonError(
                'validation_failed',
                422,
                'The submitted Contract contact data is invalid.',
                $errors,
            );
        }
        return $normalized;
    }

    /**
     * @param DocumentFieldDefinition $definition
     */
    private function normalizeContractContactFieldValue(array $definition, mixed $value): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException('The value must be a string.');
        }
        $value = trim($value);
        if ($definition['type'] !== 'select' || $value === '') {
            return $value;
        }
        foreach ($definition['options'] as $option) {
            if ($option['value'] === $value) {
                return $value;
            }
        }
        throw new \UnexpectedValueException('The selected value is not available.');
    }

    /**
     * @param array<string, string> $fields
     * @return ContractContactRecord
     */
    private function createContractContactRecord(
        Contract $contract,
        ContractContactSection $section,
        array $fields,
    ): Address|Email|PhoneNumber {
        $formData = $this->createContractContactFormData($section, $fields);
        return match (true) {
            $formData instanceof AddressFormData => $this->addressFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
            $formData instanceof EmailFormData => $this->emailFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
            $formData instanceof PhoneNumberFormData => $this->phoneNumberFactory->createFromFormData(
                $section->validationSet,
                $contract,
                $formData,
            ),
        };
    }

    /**
     * @param ContractContactRecord $record
     * @param array<string, string> $fields
     */
    private function updateContractContactRecord(
        ContractContactSection $section,
        Address|Email|PhoneNumber $record,
        array $fields,
    ): void {
        $formData = $this->createContractContactFormData($section, $fields);
        if ($record instanceof Address && $formData instanceof AddressFormData) {
            $this->addressRepository->update(
                $this->addressFactory->updateFromFormData($section->validationSet, $record, $formData),
            );
            return;
        }
        if ($record instanceof Email && $formData instanceof EmailFormData) {
            $this->emailRepository->update(
                $this->emailFactory->updateFromFormData($section->validationSet, $record, $formData),
            );
            return;
        }
        if ($record instanceof PhoneNumber && $formData instanceof PhoneNumberFormData) {
            $this->phoneNumberRepository->update(
                $this->phoneNumberFactory->updateFromFormData($section->validationSet, $record, $formData),
            );
            return;
        }
        throw new \UnexpectedValueException('The Contract contact record does not match its section.');
    }

    /**
     * @param array<string, string> $fields
     */
    private function createContractContactFormData(
        ContractContactSection $section,
        array $fields,
    ): AddressFormData|EmailFormData|PhoneNumberFormData {
        $formData = match ($section->identifier) {
            'physicalAddresses' => new AddressFormData(),
            'emailAddresses' => new EmailFormData(),
            'phoneNumbers' => new PhoneNumberFormData(),
            default => throw new \UnexpectedValueException('The Contract contact section is not supported.'),
        };
        $this->applyDocumentFormOverrides($formData, $fields);
        return $formData;
    }

    /**
     * @param ContractContactRecord $record
     */
    private function addContractContactRecord(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->add($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->add($record);
        } else {
            $this->phoneNumberRepository->add($record);
        }
    }

    /**
     * @param ContractContactRecord $record
     */
    private function updateContractContactVisibility(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->update($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->update($record);
        } else {
            $this->phoneNumberRepository->update($record);
        }
    }

    private function removeContractContactRecord(Address|Email|PhoneNumber $record): void
    {
        if ($record instanceof Address) {
            $this->addressRepository->remove($record);
        } elseif ($record instanceof Email) {
            $this->emailRepository->remove($record);
        } else {
            $this->phoneNumberRepository->remove($record);
        }
    }

    private function getNextContractContactSortingValue(
        Contract $contract,
        ContractContactSection $section,
    ): int {
        $maximum = 0;
        foreach ($this->getContractContactRecords($contract, $section) as $record) {
            $maximum = max($maximum, $record->getSorting());
        }
        return $maximum + 10;
    }

    /**
     * Reorders document records to the submitted sequence and updates their sorting values.
     *
     * The submitted order is validated to contain each record exactly once. Records are
     * then assigned their new sorting position in increments of 10 and marked as changed.
     * Flushing them is left to the caller, which announces the change through
     * {@see self::persistAndDispatchProfileUpdate()}.
     *
     * @param list<Contract|ProfileInformation> $records The records that should be reordered.
     * @param list<int> $order The desired order as a list of record UIDs.
     * @return array{changed: bool, records: list<Contract|ProfileInformation>} The reorder result including whether
     *     the sorting changed and the ordered records in their new sequence.
     */
    private function reorderDocumentRecords(array $records, array $order): array
    {
        $recordsByUid = [];
        foreach ($records as $record) {
            $recordsByUid[(int)$record->getUid()] = $record;
        }
        $currentOrder = array_keys($recordsByUid);
        $normalizedCurrentOrder = $currentOrder;
        $normalizedSubmittedOrder = $order;
        sort($normalizedCurrentOrder);
        sort($normalizedSubmittedOrder);
        if ($normalizedSubmittedOrder !== $normalizedCurrentOrder || count(array_unique($order)) !== count($order)) {
            $this->throwJsonError('invalid_payload', 400, 'The document order must contain every section record exactly once.');
        }
        $orderedRecords = [];
        $changed = false;
        foreach ($order as $position => $recordUid) {
            $record = $recordsByUid[$recordUid];
            $expectedSorting = ($position + 1) * 10;
            if ($record->getSorting() !== $expectedSorting) {
                $record->setSorting($expectedSorting);
                $this->persistenceManager->update($record);
                $changed = true;
            }
            $orderedRecords[] = $record;
        }
        return ['changed' => $changed, 'records' => $orderedRecords];
    }

    /**
     * Builds the field definitions for a document section and applies validation,
     * help text and type overrides for the current record.
     *
     * @param DocumentSection $section The document section whose fields should be resolved.
     * @param Contract|ProfileInformation|null $record The current record used to populate field values.
     * @return list<DocumentFieldDefinition>
     */
    private function getDocumentFieldDefinitions(
        DocumentSection $section,
        Contract|ProfileInformation|null $record,
    ): array {
        $definitions = $section->isContractSection()
            ? $this->getContractFieldDefinitions($record instanceof Contract ? $record : null)
            : $this->getProfileInformationFieldDefinitions(
                $record instanceof ProfileInformation ? $record : null,
            );
        foreach ($definitions as &$definition) {
            $validation = $section->validationSet->get($definition['name']);
            $definition['required'] = $validation?->required ?? false;
            $definition['readOnly'] = $validation?->readOnly ?? false;
            $definition['disabled'] = $validation?->disabled ?? false;
            $definition['richText'] = $definition['richText']
                || ($validation?->isRichText() ?? false);
            $definition['characterLimit'] = $validation?->characterLimit ?? 0;
            if ($validation?->inputType === 'date') {
                $definition['type'] = 'date';
                $definition['placeholder'] = $this->getDocumentFieldPlaceholder('date');
            }
            $helptext = $this->profileDocumentSectionProvider->getFieldHelptext(
                $section,
                $definition['name'],
            );
            $definition['helptext'] = $helptext === ''
                ? ''
                : (LocalizationUtility::translate($helptext) ?? $helptext);
        }
        unset($definition);
        return $definitions;
    }

    /**
     * Returns the field definitions for a contract record.
     *
     * @param Contract|null $record The contract record for which the field definitions should be created.
     * @return list<DocumentFieldDefinition>
     */
    private function getContractFieldDefinitions(?Contract $record): array
    {
        $definitions = [];
        foreach ($this->academicPersonsSettings->contractFields as $field) {
            $definition = $this->createDocumentField(
                'contract',
                $field->propertyName,
                $this->getContractFieldInputType($field),
                $this->getContractFieldValue($field, $record),
                $this->getContractFieldOptions($field),
                richText: $field->validation->isRichText(),
            );
            $definition['autocomplete'] = $field->autocomplete;
            $definitions[] = $definition;
        }
        return $definitions;
    }

    private function getContractFieldInputType(ContractField $field): string
    {
        return match ($field->validation->inputType) {
            'select', 'checkbox', 'email', 'tel', 'date', 'number', 'textarea' => $field->validation->inputType,
            default => 'text',
        };
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    private function getContractFieldOptions(ContractField $field): array
    {
        return match ($field->optionSource) {
            'organisationalUnits' => $this->getEntityOptions(
                $this->organisationalUnitRepository->findAll(),
                static fn(OrganisationalUnit $item): string => $item->getUnitName(),
            ),
            'functionTypes' => $this->getEntityOptions(
                $this->functionTypeRepository->findAll(),
                static fn(FunctionType $item): string => $item->getFunctionName(),
            ),
            'locations' => $this->getEntityOptions(
                $this->locationRepository->findAll(),
                static fn(Location $item): string => $item->getTitle(),
            ),
            default => [],
        };
    }

    private function getContractFieldValue(ContractField $field, ?Contract $record): mixed
    {
        if ($record === null) {
            return match ($this->getContractFieldInputType($field)) {
                'checkbox' => false,
                'select', 'date' => null,
                default => '',
            };
        }
        $getter = $field->propertyName === 'publish'
            ? 'isPublish'
            : 'get' . ucfirst($field->propertyName);
        if (!is_callable([$record, $getter])) {
            return null;
        }
        $value = $record->{$getter}();
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(self::DOCUMENT_DATE_FORMAT),
            $value instanceof AbstractEntity => $value->getUid(),
            default => $value,
        };
    }

    /**
     * Returns the form field definitions for a profile information record.
     *
     * The three years are `<input type="number">` controls carrying the bounds
     * of the TCA `range` as `min`, `max` and `step`, so the browser, the
     * controller and `DataHandler` agree on what a year is. Nothing here is
     * locale dependent.
     *
     * @param ProfileInformation|null $record The profile information record or null for a new entry.
     * @return list<DocumentFieldDefinition>
     */
    private function getProfileInformationFieldDefinitions(?ProfileInformation $record): array
    {
        return [
            $this->createDocumentField('profileInformation', 'title', 'text', $record?->getTitle() ?? ''),
            $this->createDocumentField('profileInformation', 'link', 'url', $record?->getLink() ?? ''),
            $this->createDocumentField(
                'profileInformation',
                'year',
                'number',
                $record?->getYear(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                'profileInformation',
                'yearStart',
                'number',
                $record?->getYearStart(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                'profileInformation',
                'yearEnd',
                'number',
                $record?->getYearEnd(),
                columnClass: 'col-12 col-md-3',
                min: self::YEAR_MIN,
                max: self::YEAR_MAX,
                step: 1,
            ),
            $this->createDocumentField(
                'profileInformation',
                'bodytext',
                'textarea',
                $record?->getBodytext() ?? '',
            ),
        ];
    }

    /**
     * Creates a configuration array for a document form field.
     *
     * @param string $translationPrefix Translation key prefix used to resolve the field label.
     * @param string $name Field name identifier.
     * @param string $type Field type such as text, date, checkbox or textarea.
     * @param mixed $value Current field value.
     * @param list<array{value: int|string, label: string}> $options Optional select-like options for the field.
     * @param bool $richText Whether the field uses rich text editing.
     * @param string $columnClass CSS column class for layout handling.
     * @param bool $compactCheckbox Whether the checkbox should be rendered in compact mode.
     * @param int|null $min Lower bound of a number control, null for none.
     * @param int|null $max Upper bound of a number control, null for none.
     * @param int|null $step Step of a number control, null for none.
     * @return DocumentFieldDefinition
     */
    private function createDocumentField(
        string $translationPrefix,
        string $name,
        string $type,
        mixed $value,
        array $options = [],
        bool $richText = false,
        string $columnClass = '',
        bool $compactCheckbox = false,
        ?int $min = null,
        ?int $max = null,
        ?int $step = null,
    ): array {
        return [
            'name' => $name,
            'label' => LocalizationUtility::translate(
                $translationPrefix . '.' . $name . '.label',
                'academic_persons_edit',
            ) ?? $name,
            'type' => $type,
            'required' => false,
            'readOnly' => false,
            'disabled' => false,
            'richText' => $richText,
            'characterLimit' => 0,
            'autocomplete' => '',
            'placeholder' => $this->getDocumentFieldPlaceholder($type),
            'helptext' => '',
            'columnClass' => $columnClass,
            'compactCheckbox' => $compactCheckbox,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'value' => $value,
            'displayValue' => $this->getDocumentDisplayValue(
                $name,
                $type,
                $value,
                $options,
            ),
            'options' => $options,
        ];
    }

    /**
     * Builds a list of selectable entity options from the provided items.
     *
     * Each item is converted into an option entry containing its UID as the value
     * and a label generated by the provided callback. Items without a valid UID are
     * ignored.
     * @template T of object
     * @param iterable<T> $items
     * @param callable(T): string $labelCallback
     * @return list<array{value: int, label: string}>
     */
    private function getEntityOptions(iterable $items, callable $labelCallback): array
    {
        $options = [];
        foreach ($items as $item) {
            $uid = method_exists($item, 'getUid') ? (int)$item->getUid() : 0;
            if ($uid > 0) {
                $options[] = ['value' => $uid, 'label' => $labelCallback($item)];
            }
        }
        return $options;
    }

    /**
     * The hint a control shows while it is empty, or an empty string.
     *
     * Only the two contract dates have one, and it spells
     * {@see self::DOCUMENT_DATE_FORMAT} so the two cannot drift apart. It is
     * deliberately not translated: it is the shape of the value, not prose,
     * and the previous editor spelled the same literal in Fluid.
     *
     * @param string $type The field type of the descriptor.
     * @return string The placeholder, or an empty string for a field that has none.
     */
    private function getDocumentFieldPlaceholder(string $type): string
    {
        return $type === 'date' ? self::DOCUMENT_DATE_PLACEHOLDER : '';
    }

    /**
     * Reads a date field value, in either of the two formats that are accepted.
     *
     * `d.m.Y` is what the control shows and what it submits.
     * `Y-m-d` is what the endpoint answered and accepted while the control was
     * an `<input type="date">`, and it keeps being accepted so that a client
     * written against that shape is not broken by the control changing.
     *
     * A format is only accepted when it round trips: `createFromFormat()` is
     * lenient enough to read `32.01.2026` as the first of February, so the
     * parsed date is formatted back and compared with what came in.
     *
     * @param string $value The submitted or serialized value.
     * @return \DateTime|null The date at midnight, or null when the value is neither format.
     */
    private function parseDocumentDate(string $value): ?\DateTime
    {
        foreach ([self::DOCUMENT_DATE_FORMAT, self::DOCUMENT_DATE_ISO_FORMAT] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if (
                $date instanceof \DateTime
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value
            ) {
                return $date;
            }
        }
        return null;
    }

    /**
     * Converts a raw field value into the display value shown in the UI.
     *
     * Handles date, select, checkbox and fallback string values. For select fields,
     * the matching label is resolved from the provided option list. Checkbox fields
     * use translated labels depending on the field name and boolean value.
     *
     * @param string $name
     *   The field name used to determine checkbox-specific display behavior.
     * @param string $type
     *   The field type (for example: date, select, checkbox or fallback).
     * @param mixed $value
     *   The raw field value to be rendered.
     * @param list<array{value: int|string, label: string}> $options
     *   Available select options used to resolve option labels.
     *
     * @return string
     *   The formatted display value for the frontend or an empty string for null/empty values.
     */
    private function getDocumentDisplayValue(
        string $name,
        string $type,
        mixed $value,
        array $options,
    ): string {
        if ($value === null || $value === '') {
            return '';
        }
        if ($type === 'date' && is_string($value)) {
            $date = $this->parseDocumentDate($value);
            if (!$date instanceof \DateTime) {
                return $value;
            }
            // The contract dates are the only date controls left. They are
            // formatted for the locale of the requested site language, the way
            // the public views render them.
            return (new DateFormatter())->format(
                $date,
                'MEDIUMDATE',
                $this->resolveSiteLocale(),
            );
        }
        if ($type === 'select') {
            foreach ($options as $option) {
                if ((string)$option['value'] === (string)$value) {
                    return $option['label'];
                }
            }
            return '';
        }
        if ($type === 'checkbox') {
            return LocalizationUtility::translate(
                $value ? 'profileEditing.visibility.public' : 'profileEditing.visibility.private',
                'academic_persons_edit',
            ) ?? ($value ? 'Yes' : 'No');
        }
        return (string)$value;
    }

    /**
     * Normalize and validate submitted document field values for a section.
     *
     * The method checks whether each field exists in the section definition,
     * whether it is editable, normalizes the incoming values to the expected PHP
     * types, and applies the section's validation rules. Required fields are also
     * enforced when creating a new document.
     *
     * @param DocumentSection $section The document section whose field definitions and validation rules apply.
     * @param array<string, mixed> $fields Raw field data submitted by the client, keyed by field name.
     * @param bool $creating Whether the document is currently being created.
     * @return array<string, mixed> Normalized and validated field values keyed by field name.
     */
    private function normalizeAndValidateDocumentFields(
        DocumentSection $section,
        array $fields,
        bool $creating,
    ): array {
        $definitions = $this->getDocumentFieldDefinitions($section, null);
        $definitionsByName = [];
        foreach ($definitions as $definition) {
            $definitionsByName[$definition['name']] = $definition;
        }
        $errors = [];
        $normalized = [];
        foreach ($fields as $name => $value) {
            $definition = $definitionsByName[$name] ?? null;
            if ($definition === null || $definition['readOnly'] || $definition['disabled']) {
                $errors[$name][] = 'This field cannot be changed.';
                continue;
            }
            try {
                $normalized[$name] = $this->normalizeDocumentFieldValue(
                    $name,
                    $definition['type'],
                    $value,
                    $definition['richText'],
                    $definition['min'],
                    $definition['max'],
                );
            } catch (\UnexpectedValueException $exception) {
                $errors[$name][] = $exception->getMessage();
            }
        }
        foreach ($definitionsByName as $name => $definition) {
            if ($creating && $definition['required'] && !array_key_exists($name, $normalized)) {
                $errors[$name][] = 'This field is required.';
            }
            if (!array_key_exists($name, $normalized)) {
                continue;
            }
            $validation = $section->validationSet->get($name);
            if ($validation !== null) {
                $this->validateDocumentField($name, $normalized[$name], $validation, $errors);
            }
        }
        if ($errors !== []) {
            $this->throwJsonError(
                'validation_failed',
                422,
                'The submitted document data is invalid.',
                $errors,
            );
        }
        return $normalized;
    }

    /**
     * Normalizes a submitted document field value according to its declared field type.
     *
     * @param string $name The field name used to resolve select-based entity lookups.
     * @param string $type The document field type (for example: checkbox, number, date, select, or text).
     * @param mixed $value The submitted raw value to normalize.
     * @param bool $richText Whether the value should be sanitized as rich text.
     * @param int|null $min Lower bound of a number field, null for none.
     * @param int|null $max Upper bound of a number field, null for none.
     * @return mixed The normalized value for storage or validation.
     * @throws \UnexpectedValueException If the value does not match the expected format for the given type.
     */
    private function normalizeDocumentFieldValue(
        string $name,
        string $type,
        mixed $value,
        bool $richText,
        ?int $min = null,
        ?int $max = null,
    ): mixed {
        if ($type === 'checkbox') {
            if (!is_bool($value)) {
                throw new \UnexpectedValueException('The value must be boolean.');
            }
            return $value;
        }
        if ($type === 'number') {
            if ($value === null || $value === '') {
                return null;
            }
            $number = null;
            if (is_int($value)) {
                $number = $value;
            } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                $number = (int)$value;
            }
            if ($number === null) {
                throw new \UnexpectedValueException('The value must be an integer.');
            }
            // The bounds of the control are enforced again here: a browser that
            // ignores them, and every client that is not one, reach the same
            // refusal instead of a clamped value.
            if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
                throw new \UnexpectedValueException(sprintf(
                    'The value must be between %d and %d.',
                    $min ?? PHP_INT_MIN,
                    $max ?? PHP_INT_MAX,
                ));
            }
            return $number;
        }
        if ($type === 'date') {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_string($value)) {
                throw new \UnexpectedValueException('The value must be a date.');
            }
            $date = $this->parseDocumentDate($value);
            if (!$date instanceof \DateTime) {
                throw new \UnexpectedValueException('The value must be a valid date.');
            }
            return $date;
        }
        if ($type === 'select') {
            if ($value === null || $value === '' || $value === 0) {
                return null;
            }
            $uid = is_int($value)
                ? $value
                : (is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int)$value : 0);
            if ($uid <= 0) {
                throw new \UnexpectedValueException('The selected value is invalid.');
            }
            $entity = $this->findDocumentSelectEntity($name, $uid);
            if ($entity === null) {
                throw new \UnexpectedValueException('The selected value is not available.');
            }
            return $entity;
        }
        if (!is_string($value)) {
            throw new \UnexpectedValueException('The value must be a string.');
        }
        return ($richText || in_array($name, ['bodytext', 'officeHours'], true))
            ? $this->profileRichTextSanitizer->sanitize($value)
            : trim($value);
    }

    /**
     * Finds the matching document select entity for the given field name and UID.
     *
     * @param string $name The field name identifying the repository to search in.
     * @param int $uid The UID of the entity to look up.
     * @return FunctionType|OrganisationalUnit|Location|null The matching entity or null if no entity with the given UID exists.
     */
    private function findDocumentSelectEntity(
        string $name,
        int $uid,
    ): FunctionType|OrganisationalUnit|Location|null {
        $items = match ($name) {
            'functionType' => $this->functionTypeRepository->findAll(),
            'organisationalUnit' => $this->organisationalUnitRepository->findAll(),
            'location' => $this->locationRepository->findAll(),
            default => [],
        };
        foreach ($items as $item) {
            if ((int)$item->getUid() === $uid) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Validates a single document field against the configured character limit and custom validators.
     *
     * @param string $name The field name used as the validation error key.
     * @param mixed $value The submitted value to validate.
     * @param Validation $validation The validation configuration for the field.
     * @param array<string, list<string>> $errors Reference to the collected validation errors by field name.
     */
    private function validateDocumentField(
        string $name,
        mixed $value,
        Validation $validation,
        array &$errors,
    ): void {
        if (
            $validation->characterLimit > 0
            && is_string($value)
            && RichTextCharacterCounter::count($value) > $validation->characterLimit
        ) {
            $errors[$name][] = sprintf(
                'The text must not exceed %d characters.',
                $validation->characterLimit,
            );
        }
        foreach ($validation->validatorClassNames as $validatorClassName) {
            $validator = $this->validatorResolver->createValidator($validatorClassName);
            if (!$validator instanceof ValidatorInterface) {
                continue;
            }
            $validationResult = $validator->validate($value);
            foreach ($validationResult->getErrors() as $error) {
                $errors[$name][] = $error->getMessage();
            }
        }
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
     * Serializes a document item into a frontend-safe payload.
     *
     * This compiles the field definitions for the given document section and record
     * into a normalized array that contains the stored values as well as the
     * corresponding display labels for the editor UI.
     *
     * @param DocumentSection $section The document section that defines the fields to serialize.
     * @param Contract|ProfileInformation $record The record associated with the document item.
     * @return array{
     *     uid: int,
     *     sorting: int,
     *     values: array<string, mixed>,
     *     display: array<string, string>
     * }
     */
    private function serializeDocumentItem(
        DocumentSection $section,
        Contract|ProfileInformation $record,
    ): array {
        $values = [];
        $display = [];
        foreach ($this->getDocumentFieldDefinitions($section, $record) as $field) {
            $values[$field['name']] = $field['value'];
            $display[$field['name']] = $field['displayValue'];
        }
        return [
            'uid' => (int)$record->getUid(),
            'sorting' => $record->getSorting(),
            'hidden' => $record->getHidden(),
            'values' => $values,
            'display' => $display,
        ];
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
            $imageMetadata = $this->persistUploadedProfileImage($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'hasImage' => true,
                'imageAlternative' => $imageMetadata['alternative'],
                'imageTitle' => $imageMetadata['title'],
            ]);
        } catch (PropagateResponseException $exception) {
            throw $exception;
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
            $deleted = $this->deleteProfileImage($profile);
            return new JsonResponse([
                'success' => true,
                'profile' => $profile->getUid(),
                'deleted' => $deleted,
                'hasImage' => false,
            ]);
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
     * @return array{alternative: string, title: string} The updated metadata for the uploaded profile image.
     */
    private function persistUploadedProfileImage(Profile $profile): array
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
            );
            if (!$profile->getIsTranslation()) {
                $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent($profile));
            }
            $imageMetadata = $this->profileImageMetadataService->updateForProfileUid($persistedProfileUid);
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
    private function deleteProfileImage(Profile $profile): bool
    {
        $persistedProfileUid = $this->requirePersistedProfileUid($profile);
        $removedFileUids = $this->profileImageRelationWriter->remove($persistedProfileUid);
        if ($removedFileUids === []) {
            return false;
        }
        $this->profileImageRelationWriter->deleteUnreferencedFiles($removedFileUids);
        if (!$profile->getIsTranslation()) {
            $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent($profile));
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
     * Persists all pending changes before announcing the updated profile aggregate.
     */
    private function persistAndDispatchProfileUpdate(?Profile $profile): void
    {
        $this->persistenceManager->persistAll();
        if ($profile === null || $profile->getUid() === null || $profile->getIsTranslation()) {
            return;
        }
        $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent($profile));
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
