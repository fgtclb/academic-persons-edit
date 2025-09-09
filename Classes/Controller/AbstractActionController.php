<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Controller;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Service\UserSessionService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
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

    protected PersistenceManager $persistenceManager;
    protected UserSessionService $userSessionService;
    protected LocalizationUtility $localizationUtility;
    protected AcademicPersonsSettings $academicPersonsSettings;
    protected Context $context;

    public function injectContext(Context $context): void
    {
        $this->context = $context;
    }

    public function injectPersistenceManager(PersistenceManager $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function injectUserSessionService(UserSessionService $userSessionService): void
    {
        $this->userSessionService = $userSessionService;
    }

    public function injectLocalizationUtility(LocalizationUtility $localizationUtility): void
    {
        $this->localizationUtility = $localizationUtility;
    }

    public function injectAcademicPersonsSettings(AcademicPersonsSettings $academicPersonsSettings): void
    {
        $this->academicPersonsSettings = $academicPersonsSettings;
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
}
