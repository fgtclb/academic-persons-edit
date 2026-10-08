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
use FGTCLB\AcademicPersons\Domain\Model\Email;
use FGTCLB\AcademicPersons\Domain\Model\PhoneNumber;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Domain\Model\ProfileInformation;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Attributes\ListSortingMode;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;
use FGTCLB\AcademicPersonsEdit\Property\TypeConverter\AbstractFormDataConverter;
use FGTCLB\AcademicPersonsEdit\Service\DataTransferObject\ListSortingProcess;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileOwnershipService;
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

    /**
     * The domain models an action of the editor receives as an argument, and the table
     * each of them is stored in. Every argument of one of these types is checked for
     * ownership before it is mapped, see {@see self::denyRecordsOfOtherFrontendUsers()}.
     *
     * @var array<class-string, string>
     */
    private const OWNED_RECORD_TABLES = [
        Profile::class => ProfileOwnershipService::PROFILE_TABLE,
        Contract::class => 'tx_academicpersons_domain_model_contract',
        Email::class => 'tx_academicpersons_domain_model_email',
        PhoneNumber::class => 'tx_academicpersons_domain_model_phone_number',
        Address::class => 'tx_academicpersons_domain_model_address',
        ProfileInformation::class => 'tx_academicpersons_domain_model_profile_information',
    ];

    protected ListSortingService $listSortingService;
    protected PersistenceManager $persistenceManager;
    protected UserSessionService $userSessionService;
    protected LocalizationUtility $localizationUtility;
    protected AcademicPersonsSettings $academicPersonsSettings;
    protected Context $context;
    protected ProfileOwnershipService $profileOwnershipService;

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

    #[Required]
    public function injectProfileOwnershipService(ProfileOwnershipService $profileOwnershipService): void
    {
        $this->profileOwnershipService = $profileOwnershipService;
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

        $this->denyRecordsOfOtherFrontendUsers();

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
     * Refuses the request unless every record it names as an argument belongs to a profile
     * of the logged in frontend user.
     *
     * This runs on the raw request arguments, before Extbase maps them. The identity a form
     * posts is not covered by the cHash, so it can name any record. The mapping itself already
     * has effects, the profile image upload stores the file while the argument is mapped. An
     * argument the request does not carry is left to Extbase, which refuses a missing required
     * argument itself.
     *
     * Actions that take a record uid as a plain integer are not covered by this and check
     * it themselves with {@see self::assertRecordIsOwnedByCurrentFrontendUser()}.
     */
    private function denyRecordsOfOtherFrontendUsers(): void
    {
        /** @var Argument $argument */
        foreach ($this->arguments as $argument) {
            $tableName = $this->getOwnedRecordTable($argument->getDataType());
            if ($tableName === null || !$this->request->hasArgument($argument->getName())) {
                continue;
            }
            $this->assertRecordIsOwnedByCurrentFrontendUser(
                $tableName,
                $this->getRequestedRecordUid($this->request->getArgument($argument->getName())),
            );
        }
    }

    /**
     * Answers the request with the access denied response of the site unless the record
     * belongs to a profile of the logged in frontend user. A record that does not exist is
     * answered the same way, so the response does not tell which uids exist.
     *
     * The response is propagated rather than returned with a 403 status: TYPO3 v12 and v13
     * send the status of an Extbase plugin response with `header()` instead of passing it on
     * to the frontend response.
     *
     * @throws PropagateResponseException
     */
    protected function assertRecordIsOwnedByCurrentFrontendUser(string $tableName, int $recordUid): void
    {
        $frontendUserUid = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($this->profileOwnershipService->isOwnedByFrontendUser($tableName, $recordUid, $frontendUserUid)) {
            return;
        }
        $this->denyAccess();
    }

    /**
     * Answers the request with the access denied response of the site, see
     * {@see self::assertRecordIsOwnedByCurrentFrontendUser()}.
     *
     * @throws PropagateResponseException
     */
    protected function denyAccess(): never
    {
        throw new PropagateResponseException(
            GeneralUtility::makeInstance(ErrorController::class)->accessDeniedAction(
                $this->request,
                'Record not editable'
            ),
            1791459313
        );
    }

    private function getOwnedRecordTable(string $dataType): ?string
    {
        foreach (self::OWNED_RECORD_TABLES as $className => $tableName) {
            if (is_a($dataType, $className, true)) {
                return $tableName;
            }
        }
        return null;
    }

    /**
     * The uid a request argument names the way Extbase reads it: a plain uid in a link, an
     * `__identity` in a form. Anything else is no uid and resolves to 0, which no record has.
     */
    protected function getRequestedRecordUid(mixed $value): int
    {
        if (is_array($value)) {
            $value = $value['__identity'] ?? null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int)$value;
        }
        return 0;
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
            $this->localizationUtility->translate($key, 'AcademicPersonsEdit') ?? $key,
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
            $this->localizationUtility->translate($key, 'AcademicPersonsEdit') ?? $key,
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
