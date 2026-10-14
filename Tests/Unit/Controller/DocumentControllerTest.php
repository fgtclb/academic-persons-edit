<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Controller;

use FGTCLB\AcademicPersons\Domain\Repository\AddressRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ContractRepository;
use FGTCLB\AcademicPersons\Domain\Repository\EmailRepository;
use FGTCLB\AcademicPersons\Domain\Repository\FunctionTypeRepository;
use FGTCLB\AcademicPersons\Domain\Repository\LocationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\OrganisationalUnitRepository;
use FGTCLB\AcademicPersons\Domain\Repository\PhoneNumberRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileInformationRepository;
use FGTCLB\AcademicPersons\Domain\Repository\ProfileRepository;
use FGTCLB\AcademicPersons\Types\EmailAddressTypes;
use FGTCLB\AcademicPersons\Types\PhoneNumberTypes;
use FGTCLB\AcademicPersons\Types\PhysicalAddressTypes;
use FGTCLB\AcademicPersonsEdit\Controller\DocumentController;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ContractFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileInformationFactory;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactRecords;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldDefinitions;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldNormalizer;
use FGTCLB\AcademicPersonsEdit\Service\DocumentRecords;
use FGTCLB\AcademicPersonsEdit\Service\EditorFieldValidation;
use FGTCLB\AcademicPersonsEdit\Service\EditorLabels;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

final class DocumentControllerTest extends AbstractEditorControllerTestCase
{
    #[Test]
    public function documentFormReturnsJsonWhenAuthenticationIsRequired(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                        'data' => ['section' => 'cooperation', 'record' => 0],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->documentFormAction(),
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
    public function sortDocumentRejectsNonPostRequest(): void
    {
        $subject = $this->createSubject($this->createRequest('GET'));

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->sortDocumentAction(),
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
    public function initializeActionRefusesARequestWithoutTheEditorHeader(): void
    {
        $subject = $this->createSubject($this->createRequest('POST'));

        $response = $this->getPropagatedResponse(
            static function () use ($subject): ResponseInterface {
                $subject->initializeAction();
                throw new \LogicException('The request was not refused.', 1792001180);
            },
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    private function createSubject(Request $request): DocumentController
    {
        $academicPersonsSettings = $this->createAcademicPersonsSettings();
        $managedRecordLocks = $this->createManagedRecordLocks($academicPersonsSettings);
        $editorLabels = new EditorLabels();
        $editorFieldValidation = new EditorFieldValidation($this->createStub(ValidatorResolver::class));
        $documentFieldDefinitions = new DocumentFieldDefinitions(
            new ProfileDocumentSectionProvider(
                $academicPersonsSettings,
                $this->createStub(ContractRepository::class),
                $this->createStub(ProfileInformationRepository::class),
                $managedRecordLocks,
            ),
            $editorLabels,
            $academicPersonsSettings,
            $this->createStub(OrganisationalUnitRepository::class),
            $this->createStub(FunctionTypeRepository::class),
            $this->createStub(LocationRepository::class),
        );

        $subject = new DocumentController(
            $this->createProfileUpdateRequestService($this->createStub(ProfileRepository::class)),
            $academicPersonsSettings,
            $managedRecordLocks,
            new DocumentRecords(
                $this->createStub(ContractRepository::class),
                $this->createStub(ProfileInformationRepository::class),
                $this->createStub(PersistenceManager::class),
            ),
            $documentFieldDefinitions,
            new DocumentFieldNormalizer(
                $documentFieldDefinitions,
                $editorFieldValidation,
                $this->createStub(ProfileRichTextSanitizerInterface::class),
                $this->createStub(FunctionTypeRepository::class),
                $this->createStub(OrganisationalUnitRepository::class),
                $this->createStub(LocationRepository::class),
            ),
            new ContractContactFields(
                $academicPersonsSettings,
                new ContractContactRecords(
                    $this->createStub(AddressRepository::class),
                    $this->createStub(EmailRepository::class),
                    $this->createStub(PhoneNumberRepository::class),
                    $this->createStub(AddressFactory::class),
                    $this->createStub(EmailFactory::class),
                    $this->createStub(PhoneNumberFactory::class),
                ),
                $editorLabels,
                $documentFieldDefinitions,
                $this->createStub(CountryProvider::class),
                $this->createStub(PhysicalAddressTypes::class),
                $this->createStub(EmailAddressTypes::class),
                $this->createStub(PhoneNumberTypes::class),
                $managedRecordLocks,
                $editorFieldValidation,
            ),
            $this->createStub(ContractFactory::class),
            $this->createStub(ContractRepository::class),
            $this->createStub(ProfileInformationFactory::class),
            $this->createStub(ProfileInformationRepository::class),
            new ListSortingService(),
            $this->createEditorEndpoint(),
            $this->createEditorWrites(),
        );
        $this->setRequest($subject, $request);

        return $subject;
    }
}
