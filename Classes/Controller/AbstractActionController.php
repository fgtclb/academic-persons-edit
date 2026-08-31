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
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Property\TypeConverter\AbstractFormDataConverter;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\UserSessionService;
use Psr\Http\Message\ResponseInterface;
use Symfony\Contracts\Service\Attribute\Required;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\ErrorController;

/**
 * Provides shared functionality and services for multiple concrete extension
 * extbase controllers to avoid duplicate code fragments within the extension.
 *
 * @internal to be used only in `EXT:academic_person_edit` and not part of public API.
 */
abstract class AbstractActionController extends ActionController
{
    use GetCurrentContentRecordMethodTrait;
    public const FLASH_MESSAGE_QUEUE_IDENTIFIER = 'academic_profile';

    protected const DATETIME_ARGUMENTS = [
        'contract' => [
            'validFrom' => 'd.m.Y',
            'validTo' => 'd.m.Y',
        ],
        'contractFormData' => [
            'validFrom' => 'd.m.Y',
            'validTo' => 'd.m.Y',
        ],
    ];

    protected ListSortingService $listSortingService;
    protected PersistenceManager $persistenceManager;
    protected UserSessionService $userSessionService;
    protected LocalizationUtility $localizationUtility;
    protected AcademicPersonsSettings $academicPersonsSettings;
    protected Context $context;

    #[Required]
    public function injectContext(Context $context): void
    {
        $this->context = $context;
    }

    #[Required]
    public function injectPersistenceManager(PersistenceManager $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    #[Required]
    public function injectUserSessionService(UserSessionService $userSessionService): void
    {
        $this->userSessionService = $userSessionService;
    }

    #[Required]
    public function injectLocalizationUtility(LocalizationUtility $localizationUtility): void
    {
        $this->localizationUtility = $localizationUtility;
    }

    #[Required]
    public function injectAcademicPersonsSettings(AcademicPersonsSettings $academicPersonsSettings): void
    {
        $this->academicPersonsSettings = $academicPersonsSettings;
    }

    #[Required]
    public function injectListSortingService(ListSortingService $listSortingService): void
    {
        $this->listSortingService = $listSortingService;
    }

    /**
     * @return ResponseInterface
     */
    protected function errorAction(): ResponseInterface
    {
        if (($response = $this->forwardToReferringRequest()) !== null) {
            return $response->withStatus(400);
        }

        $response = $this->htmlResponse($this->getFlattenedValidationErrorMessage());
        return $response->withStatus(400);
    }

    /**
     * Adds a validator to the validators Extbase already built for an action argument.
     *
     * Used from `initialize<Action>Action()` methods instead of a `#[Validate]` attribute,
     * because both attribute forms usable on TYPO3 v13 are deprecated on v14 and will be
     * removed in v15: passing an array of configuration values, and naming the validated
     * parameter. The documented replacement, placing the attribute on the method parameter,
     * requires `Attribute::TARGET_PARAMETER`, which TYPO3 v13 does not declare. The API used
     * here emits no deprecation on either version.
     *
     * `initializeActionMethodValidators()` runs before the action specific initialize method
     * and already put a `ConjunctionValidator` holding the base validation on the argument,
     * so it is extended rather than replaced — replacing it would silently drop the model
     * level validation.
     *
     * @param class-string<ValidatorInterface> $validatorClassName
     */
    protected function addArgumentValidator(string $argumentName, string $validatorClassName): void
    {
        $argument = $this->arguments->getArgument($argumentName);
        $additionalValidator = $this->validatorResolver->createValidator($validatorClassName);
        if ($additionalValidator === null) {
            return;
        }

        $validator = $argument->getValidator();
        if ($validator instanceof ConjunctionValidator) {
            $validator->addValidator($additionalValidator);
            return;
        }

        /** @var ConjunctionValidator $conjunctionValidator */
        $conjunctionValidator = $this->validatorResolver->createValidator(ConjunctionValidator::class);
        if ($validator !== null) {
            $conjunctionValidator->addValidator($validator);
        }
        $conjunctionValidator->addValidator($additionalValidator);
        $argument->setValidator($conjunctionValidator);
    }

    public function initializeAction(): void
    {
        if ($this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false) === false) {
            throw new PropagateResponseException(
                GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                    $this->request,
                    'Authentication needed'
                ),
                1744109477
            );
        }

        /** @var Argument $argument */
        foreach ($this->arguments as $argument) {
            $this->setCurrentRequestForAbstractFormDataBasedArguments($argument);
        }

        // Map date and time arguments
        foreach (self::DATETIME_ARGUMENTS as $argument => $datetimeProperties) {
            if ($this->arguments->hasArgument($argument)) {
                foreach ($datetimeProperties as $property => $format) {
                    $this->arguments->getArgument($argument)
                        ->getPropertyMappingConfiguration()
                        ->forProperty($property)
                        ->setTypeConverterOption(
                            DateTimeConverter::class,
                            DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                            $format
                        );
                }
            }
        }
    }

    /**
     * @param Argument $argument
     */
    private function setCurrentRequestForAbstractFormDataBasedArguments(Argument $argument): void
    {
        $dataType = $argument->getDataType();
        if (!class_exists($dataType)
            || !in_array(AbstractFormData::class, class_parents($dataType), true)
        ) {
            // Argument is not mapped to an object and is not based on AbstractFormData. Skip.
            return;
        }
        $argument
            ->getPropertyMappingConfiguration()
            ->setTypeConverterOptions(
                AbstractFormDataConverter::class,
                [
                    'request' => $this->request,
                    'argumentName' => $argument->getName(),
                ]
            );
    }

    /**
     * Add translated success message to the flash message queue
     *
     * @param string $key
     */
    public function addTranslatedSuccessMessage(string $key): void
    {
        $this->addFlashMessage(
            $this->localizationUtility->translate($key, 'academic_persons_edit') ?? $key,
            '',
            ContextualFeedbackSeverity::OK,
            true
        );
    }

    /**
     * Add translated error message to the flash message queue
     *
     * @param string $key
     */
    public function addTranslatedErrorMessage(string $key): void
    {
        $this->addFlashMessage(
            $this->localizationUtility->translate($key, 'academic_persons_edit') ?? $key,
            '',
            ContextualFeedbackSeverity::ERROR,
            true
        );
    }

    protected function getCurrentContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->request->getAttribute('currentContentObject');
    }

    /**
     * Creates a redirect with status code `303` to be used to add
     * `post-redirect-get (PRG)` for form persistence submission
     * actions and should be used to avoid duplicate data handling
     * when user uses reload (F5) in the browser after sending the
     * form data.
     *
     * @param string $action
     * @param array<string, mixed> $arguments
     * @return ResponseInterface
     */
    protected function createFormPersistencePrgRedirect(
        string $action,
        array $arguments = [],
    ): ResponseInterface {
        // Use `303 - see other` as semantically correct status code to tell the browser / client to redirect
        // to another uri and discard the POST data (not sending it along with the redirect), which is what
        // we want for a `post-redirect-get (PRG)` implementation
        return (new Response())
            ->withStatus(303)
            ->withHeader('location', $this->uriBuilder
                ->reset()
                ->setRequest($this->request)
                ->setCreateAbsoluteUri(true)
                ->uriFor($action, $arguments))
            ->withHeader('x-redirected-by', 'TYPO3 academic-persons-edit');
    }

    /**
     * Persists all pending changes and announces the change to the profile aggregate.
     *
     * Every action that persists a change to a profile or one of its child records
     * (contract, address, email address, phone number, profile information) calls this
     * once after the change - the one place `EXT:academic_persons_edit` dispatches
     * {@see AfterProfileUpdateEvent} from the frontend editing flow. The dispatch was
     * lost in the a1a471c44 restructuring and restored with ACE-485; listeners keep the
     * translated profile records in sync (`SyncChangesToTranslations`) and regenerate
     * the profile slug (`GenerateSlugForProfile`).
     *
     * The event carries the persisted default language profile: listeners read the
     * database, so the dispatch must happen after `persistAll()`, and a profile fetched
     * as translation overlay is skipped - synchronisation runs from the default language
     * record only, exactly as `AbstractProfileFactory::createProfileForUser()` does it.
     * Child controllers resolve the owning profile through their relation accessors and
     * pass `null` when the relation chain is broken, which skips the dispatch.
     *
     * Note: `skip_sync` on the profile gates the fe_users to profile data
     * synchronisation and deliberately not this event.
     */
    protected function persistAndDispatchProfileUpdate(?Profile $profile): void
    {
        $this->persistenceManager->persistAll();
        if ($profile === null || $profile->getUid() === null || $profile->getIsTranslation()) {
            return;
        }
        $this->eventDispatcher->dispatch(new AfterProfileUpdateEvent($profile));
    }

    /**
     * @param AbstractEntity[] $items
     * @param int<1,max> $tagetUid
     * @param ListSortingMode $mode
     * @return ListSortingProcess
     */
    protected function sortItems(
        array $items,
        int $tagetUid,
        ListSortingMode $mode,
    ): ListSortingProcess {
        return $this->listSortingService->sort($items, $tagetUid, $mode);
    }
}
