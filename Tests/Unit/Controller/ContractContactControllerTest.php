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
use FGTCLB\AcademicPersonsEdit\Controller\ContractContactController;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\AddressFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\EmailFactory;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\PhoneNumberFactory;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactFields;
use FGTCLB\AcademicPersonsEdit\Service\ContractContactRecords;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldDefinitions;
use FGTCLB\AcademicPersonsEdit\Service\DocumentRecords;
use FGTCLB\AcademicPersonsEdit\Service\EditorFieldValidation;
use FGTCLB\AcademicPersonsEdit\Service\EditorLabels;
use FGTCLB\AcademicPersonsEdit\Service\ListSortingService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileDocumentSectionProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

final class ContractContactControllerTest extends AbstractEditorControllerTestCase
{
    #[Test]
    public function contractContactFormReturnsJsonWhenAuthenticationIsRequired(): void
    {
        $subject = $this->createSubject(
            $this->createRequest(
                'POST',
                json_encode(
                    [
                        'profile' => 123,
                        'data' => ['contract' => 1, 'section' => 'physicalAddresses', 'mode' => 'add'],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        );

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->contractContactFormAction(),
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
    public function sortContractContactRejectsNonPostRequest(): void
    {
        $subject = $this->createSubject($this->createRequest('GET'));

        $response = $this->getPropagatedResponse(
            static fn(): ResponseInterface => $subject->sortContractContactAction(),
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
                throw new \LogicException('The request was not refused.', 1792001181);
            },
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    private function createSubject(Request $request): ContractContactController
    {
        $academicPersonsSettings = $this->createAcademicPersonsSettings();
        $managedRecordLocks = $this->createManagedRecordLocks($academicPersonsSettings);
        $editorLabels = new EditorLabels();
        $contractContactRecords = new ContractContactRecords(
            $this->createStub(AddressRepository::class),
            $this->createStub(EmailRepository::class),
            $this->createStub(PhoneNumberRepository::class),
            $this->createStub(AddressFactory::class),
            $this->createStub(EmailFactory::class),
            $this->createStub(PhoneNumberFactory::class),
        );

        $subject = new ContractContactController(
            $this->createProfileUpdateRequestService($this->createStub(ProfileRepository::class)),
            $academicPersonsSettings,
            $managedRecordLocks,
            new DocumentRecords(
                $this->createStub(ContractRepository::class),
                $this->createStub(ProfileInformationRepository::class),
                $this->createStub(PersistenceManager::class),
            ),
            $contractContactRecords,
            new ContractContactFields(
                $academicPersonsSettings,
                $contractContactRecords,
                $editorLabels,
                new DocumentFieldDefinitions(
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
                ),
                $this->createStub(CountryProvider::class),
                $this->createStub(PhysicalAddressTypes::class),
                $this->createStub(EmailAddressTypes::class),
                $this->createStub(PhoneNumberTypes::class),
                $managedRecordLocks,
                new EditorFieldValidation($this->createStub(ValidatorResolver::class)),
            ),
            new ListSortingService(),
            $this->createEditorEndpoint(),
            $this->createEditorWrites(),
        );
        $this->setRequest($subject, $request);

        return $subject;
    }
}
