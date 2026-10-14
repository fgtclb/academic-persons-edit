<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Controller;

use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Service\DataHandlerExecutionContext;
use FGTCLB\AcademicPersons\Service\ProfileImageMetadataService;
use FGTCLB\AcademicPersons\Service\ProfileImageRelationWriter;
use FGTCLB\AcademicPersons\Settings\ProjectProfileFieldCheck;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Service\EditorLabels;
use FGTCLB\AcademicPersonsEdit\Service\LocalizedProfileUidResolver;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileFieldOptionsService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerInterface;
use FGTCLB\AcademicPersonsEdit\Service\ProfileSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateValidationService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileVisibilityWriter;
use FGTCLB\AcademicPersonsEdit\Service\ProjectProfileFields;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Frontend\Controller\ErrorController;

final class ProfileControllerTest extends AbstractEditorControllerTestCase
{
    #[Test]
    public function updateRejectsNonPostRequest(): void
    {
        $subject = $this->createSubject(
            $this->createRequest('GET')
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateAction(),
        );

        $this->assertJsonResponse(
            $response,
            405,
            [
                'success' => false,
                'error' => 'method_not_allowed',
            ],
        );
    }

    #[Test]
    public function updateRejectsInvalidJson(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                '{"profile": 123,'
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateAction(),
        );

        $this->assertJsonResponse(
            $response,
            400,
            [
                'success' => false,
                'error' => 'invalid_json',
            ],
        );
    }

    #[Test]
    public function updateRejectsInvalidPayload(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                    ],
                    JSON_THROW_ON_ERROR,
                )
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateAction(),
        );

        $this->assertJsonResponse(
            $response,
            400,
            [
                'success' => false,
                'error' => 'invalid_payload',
            ],
        );
    }

    #[Test]
    public function updateReturnsJsonWhenAuthenticationIsRequired(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                        'data' => [],
                    ],
                    JSON_THROW_ON_ERROR,
                )
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateAction(),
        );

        $this->assertJsonResponse(
            $response,
            401,
            [
                'success' => false,
                'error' => 'authentication_required',
            ],
        );
    }

    #[Test]
    public function updateSkipSyncRejectsNonPostRequest(): void
    {
        $subject = $this->createSubject(
            $this->createRequest('GET')
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateSkipSyncAction(),
        );

        $this->assertJsonResponse(
            $response,
            405,
            [
                'success' => false,
                'error' => 'method_not_allowed',
            ],
        );
    }

    #[Test]
    public function updateSkipSyncReturnsJsonWhenAuthenticationIsRequired(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                        'data' => ['skipSync' => true],
                    ],
                    JSON_THROW_ON_ERROR,
                )
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateSkipSyncAction(),
        );

        $this->assertJsonResponse(
            $response,
            401,
            [
                'success' => false,
                'error' => 'authentication_required',
            ],
        );
    }

    #[Test]
    public function updateSkipSyncRejectsInvalidJson(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                '{"profile": 123,'
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->updateSkipSyncAction(),
        );

        $this->assertJsonResponse(
            $response,
            400,
            [
                'success' => false,
                'error' => 'invalid_json',
            ],
        );
    }

    #[Test]
    public function deleteImageRejectsNonPostRequest(): void
    {
        $subject = $this->createSubject(
            $this->createRequest('GET')
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->deleteImageAction(),
        );

        $this->assertJsonResponse(
            $response,
            405,
            [
                'success' => false,
                'error' => 'method_not_allowed',
            ],
        );
    }

    #[Test]
    public function deleteImageRejectsInvalidJson(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                '{"profile": 123,'
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->deleteImageAction(),
        );

        $this->assertJsonResponse(
            $response,
            400,
            [
                'success' => false,
                'error' => 'invalid_json',
            ],
        );
    }

    #[Test]
    public function deleteImageReturnsJsonWhenAuthenticationIsRequired(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                        'data' => [],
                    ],
                    JSON_THROW_ON_ERROR,
                )
            )
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->deleteImageAction(),
        );

        $this->assertJsonResponse(
            $response,
            401,
            [
                'success' => false,
                'error' => 'authentication_required',
            ],
        );
    }

    private function createSubject(Request $request): ProfileController
    {
        $profileRepository = $this->createStub(ProfileRepository::class);
        $academicPersonsSettings = $this->createAcademicPersonsSettings();
        $profileFieldOptionsService = new ProfileFieldOptionsService($academicPersonsSettings);
        $resourceFactory = $this->createStub(ResourceFactory::class);
        $connectionPool = $this->createStub(ConnectionPool::class);
        $tcaSchemaFactory = $this->createStub(TcaSchemaFactory::class);
        $dataHandlerExecutionContext = new DataHandlerExecutionContext(
            new Context(),
            $this->createStub(LanguageServiceFactory::class),
        );
        $profileImageRelationWriter = new ProfileImageRelationWriter(
            $connectionPool,
            $resourceFactory,
            $dataHandlerExecutionContext,
            $tcaSchemaFactory,
        );
        $managedRecordLocks = $this->createManagedRecordLocks($academicPersonsSettings);

        $subject = new ProfileController(
            new Context(),
            $academicPersonsSettings,
            $this->createStub(ProfileFactory::class),
            $profileRepository,
            $this->createStub(ErrorController::class),
            $this->createStub(LogManager::class),
            $resourceFactory,
            new ProfileImageMetadataService(
                $connectionPool,
                $profileImageRelationWriter,
                new NullLogger(),
                $tcaSchemaFactory,
                $this->createStub(MetaDataRepository::class),
                $resourceFactory,
                new NoopEventDispatcher(),
            ),
            $this->createProfileUpdateRequestService($profileRepository),
            new ProfileUpdateValidationService(
                $this->createStub(ProfileFormDataFactoryInterface::class),
                new ProfileFormDataValidator(),
                $profileFieldOptionsService,
                $this->createStub(ProfileRichTextSanitizerInterface::class),
                $academicPersonsSettings,
            ),
            new LocalizedProfileUidResolver($connectionPool, $tcaSchemaFactory),
            new ProfileVisibilityWriter($connectionPool, $dataHandlerExecutionContext, $tcaSchemaFactory),
            $profileImageRelationWriter,
            $dataHandlerExecutionContext,
            $profileFieldOptionsService,
            new ProfileSectionProvider($academicPersonsSettings),
            new ProfileDocumentSectionProvider(
                $academicPersonsSettings,
                $this->createStub(ContractRepository::class),
                $this->createStub(ProfileInformationRepository::class),
                $managedRecordLocks,
            ),
            $managedRecordLocks,
            new ProjectProfileFields(
                $academicPersonsSettings,
                new ProjectProfileFieldCheck(),
                $tcaSchemaFactory,
                $connectionPool,
                $dataHandlerExecutionContext,
            ),
            new EditorLabels(),
            $this->createEditorEndpoint(),
            $this->createEditorWrites(),
        );
        $this->setRequest($subject, $request);

        return $subject;
    }
}
