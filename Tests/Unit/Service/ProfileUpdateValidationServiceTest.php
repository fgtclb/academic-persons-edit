<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Factory\ProfileFormDataFactoryInterface;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Service\ProfileFieldOptionsService;
use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerInterface;
use FGTCLB\AcademicPersonsEdit\Service\ProfileUpdateValidationService;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileUpdateValidationServiceTest extends UnitTestCase
{
    private bool $tcaWasDefined = false;

    /** @var mixed */
    private $tcaBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tcaWasDefined = array_key_exists('TCA', $GLOBALS);
        $this->tcaBackup = $GLOBALS['TCA'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->tcaWasDefined) {
            $GLOBALS['TCA'] = $this->tcaBackup;
        } else {
            unset($GLOBALS['TCA']);
        }
        parent::tearDown();
    }

    #[Test]
    public function payloadPropertiesAreRegisteredAsOverridesOnFactoryResult(): void
    {
        $context = $this->createPluginControllerActionContext();
        $profile = new Profile();
        $formData = new ProfileFormData(
            firstName: 'Persisted',
            lastName: 'Unchanged',
        );
        $factory = $this->createMock(ProfileFormDataFactoryInterface::class);
        $factory
            ->expects($this->once())
            ->method('createFromProfile')
            ->with($context, $profile)
            ->willReturn($formData);
        $subject = $this->createSubject($factory);

        $result = $subject->createFormData(
            $context,
            $profile,
            new ProfileUpdatePayload(
                profileUid: 123,
                data: [
                    'firstName' => 'Submitted',
                    'website' => '',
                    'skipSync' => true,
                ],
            ),
        );

        $this->assertSame($formData, $result);
        $this->assertSame('Submitted', $result->getPropertyOverride('firstName'));
        $this->assertSame('', $result->getPropertyOverride('website'));
        $this->assertTrue($result->getPropertyOverride('skipSync'));
        $this->assertTrue($result->shouldApplyProperty('firstName'));
        $this->assertFalse($result->hasPropertyOverride('lastName'));
        $this->assertFalse($result->shouldApplyProperty('lastName'));
    }

    #[Test]
    public function unknownProfilePropertyIsRejected(): void
    {
        $subject = $this->createSubjectForFormData(new ProfileFormData());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown profile property "unknown".');

        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(
                profileUid: 123,
                data: ['unknown' => 'value'],
            ),
        );
    }

    #[Test]
    public function profileFieldsMarkedReadOnlyByTheirSectionAreRejected(): void
    {
        $formData = new ProfileFormData(firstName: 'Persisted');
        $factory = $this->createStub(ProfileFormDataFactoryInterface::class);
        $factory->method('createFromProfile')->willReturn($formData);
        $settings = ValidationSettings::forProfileFields(['firstName' => 'text'], ['firstName']);
        $subject = new ProfileUpdateValidationService(
            $factory,
            new ProfileFormDataValidator(),
            new ProfileFieldOptionsService($settings),
            $this->createStub(ProfileRichTextSanitizerInterface::class),
            $settings,
        );
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown profile property "firstName".');
        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(profileUid: 123, data: ['firstName' => 'Submitted']),
        );
    }

    #[Test]
    public function aConfiguredReadOnlyRuleOverridesAnInternalDefault(): void
    {
        $formData = new ProfileFormData(title: 'Persisted');
        $factory = $this->createStub(ProfileFormDataFactoryInterface::class);
        $factory->method('createFromProfile')->willReturn($formData);
        $settings = ValidationSettings::forProfileFields(['title' => 'text'], ['title']);
        $subject = new ProfileUpdateValidationService(
            $factory,
            new ProfileFormDataValidator(),
            new ProfileFieldOptionsService($settings),
            $this->createStub(ProfileRichTextSanitizerInterface::class),
            $settings,
        );
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown profile property "title".');
        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(profileUid: 123, data: ['title' => 'Submitted']),
        );
    }

    #[Test]
    #[DataProvider('invalidGenderValues')]
    public function invalidGenderIsRejected(mixed $gender): void
    {
        $this->setGenderItems([
            ['label' => 'Female', 'value' => 'female'],
        ]);
        $subject = $this->createSubjectForFormData(new ProfileFormData());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid gender value.');

        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(
                profileUid: 123,
                data: ['gender' => $gender],
            ),
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidGenderValues(): array
    {
        return [
            'unknown string' => ['unknown'],
            'integer' => [1],
            'null' => [null],
            'array' => [['female']],
        ];
    }

    #[Test]
    #[DataProvider('validGenderValues')]
    public function configuredOrEmptyGenderPassesPayloadNormalization(string $gender): void
    {
        $this->setGenderItems([
            ['label' => 'Female', 'value' => 'female'],
        ]);
        $subject = $this->createSubjectForFormData(new ProfileFormData());

        $result = $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(
                profileUid: 123,
                data: ['gender' => $gender],
            ),
        );

        $this->assertTrue($result->hasPropertyOverride('gender'));
        $this->assertSame($gender, $result->getPropertyOverride('gender'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validGenderValues(): array
    {
        return [
            'configured value' => ['female'],
            'empty value for clearing' => [''],
        ];
    }

    #[Test]
    public function anotherConfiguredSelectUsesTheSameStrictTcaAllowList(): void
    {
        $this->setSelectItems('first_name', [
            ['label' => 'Jane', 'value' => 'jane'],
        ]);
        $formData = new ProfileFormData(firstName: 'Persisted');
        $factory = $this->createStub(ProfileFormDataFactoryInterface::class);
        $factory->method('createFromProfile')->willReturn($formData);
        $settings = ValidationSettings::forProfileFields(['firstName' => 'select']);
        $subject = new ProfileUpdateValidationService(
            $factory,
            new ProfileFormDataValidator(),
            new ProfileFieldOptionsService($settings),
            $this->createStub(ProfileRichTextSanitizerInterface::class),
            $settings,
        );

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid select value for profile property "firstName".');

        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(profileUid: 123, data: ['firstName' => 'unknown']),
        );
    }

    #[Test]
    public function richTextIsSanitizedBeforeItIsRegisteredAsOverride(): void
    {
        $formData = new ProfileFormData();
        $factory = $this->createStub(ProfileFormDataFactoryInterface::class);
        $factory->method('createFromProfile')->willReturn($formData);
        $sanitizer = $this->createMock(ProfileRichTextSanitizerInterface::class);
        $sanitizer
            ->expects($this->once())
            ->method('supports')
            ->with('coreCompetences')
            ->willReturn(true);
        $sanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with('<p onclick="alert(1)">Secure content</p>')
            ->willReturn('<p>Secure content</p>');
        $settings = $this->createSettings();
        $subject = new ProfileUpdateValidationService(
            $factory,
            new ProfileFormDataValidator(),
            new ProfileFieldOptionsService($settings),
            $sanitizer,
            $settings,
        );
        $payload = new ProfileUpdatePayload(
            profileUid: 123,
            data: ['coreCompetences' => '<p onclick="alert(1)">Secure content</p>'],
        );
        $result = $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            $payload,
        );
        $this->assertSame('<p>Secure content</p>', $result->getPropertyOverride('coreCompetences'));
        $this->assertSame(
            ['coreCompetences' => '<p>Secure content</p>'],
            $subject->getNormalizedData($result, $payload),
        );
    }

    #[Test]
    public function nonStringProfileFieldValueIsRejected(): void
    {
        $subject = $this->createSubjectForFormData(new ProfileFormData());
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid value for profile property "firstName".');
        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(
                profileUid: 123,
                data: ['firstName' => ['not', 'a', 'string']],
            ),
        );
    }

    #[Test]
    public function configuredCheckboxFieldsRequireBooleanPayloadValues(): void
    {
        $subject = $this->createSubjectForFormData(new ProfileFormData());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Invalid boolean value for profile property "skipSync".',
        );
        $subject->createFormData(
            $this->createPluginControllerActionContext(),
            new Profile(),
            new ProfileUpdatePayload(
                profileUid: 123,
                data: ['skipSync' => '1'],
            ),
        );
    }

    #[Test]
    public function validateReturnsErrorsFromProfileFormDataValidator(): void
    {
        $validator = new ProfileFormDataValidator();
        $validator->injectAcademicPersonsSettings(
            ValidationSettings::forProfileSection(
                'information',
                ['firstName' => [RecordingValidator::class]],
            ),
        );
        $settings = $this->createSettings();
        $subject = new ProfileUpdateValidationService(
            $this->createStub(ProfileFormDataFactoryInterface::class),
            $validator,
            new ProfileFieldOptionsService($settings),
            $this->createStub(ProfileRichTextSanitizerInterface::class),
            $settings,
        );

        $formData = new ProfileFormData(firstName: 'Persisted');
        $formData->setPropertyOverride('firstName', 'Jane');
        $result = $subject->validate($formData);

        $this->assertSame(
            ['string(Jane)'],
            array_map(
                static fn($error): string => $error->getMessage(),
                $result->forProperty('firstName')->getErrors(),
            ),
        );
    }

    private function createSubjectForFormData(
        ProfileFormData $formData,
    ): ProfileUpdateValidationService {
        $factory = $this->createStub(ProfileFormDataFactoryInterface::class);
        $factory->method('createFromProfile')->willReturn($formData);
        return $this->createSubject($factory);
    }

    private function createSubject(
        ProfileFormDataFactoryInterface $factory,
    ): ProfileUpdateValidationService {
        $richTextSanitizer = $this->createStub(ProfileRichTextSanitizerInterface::class);
        $richTextSanitizer->method('supports')->willReturn(false);
        $settings = $this->createSettings();
        return new ProfileUpdateValidationService(
            $factory,
            new ProfileFormDataValidator(),
            new ProfileFieldOptionsService($settings),
            $richTextSanitizer,
            $settings,
        );
    }

    private function createSettings(): AcademicPersonsSettings
    {
        return ValidationSettings::forProfileFields([
            'gender' => 'select',
            'firstName' => 'text',
            'middleName' => 'text',
            'lastName' => 'text',
            'website' => 'combinedLink',
            'publicationsLink' => 'combinedLink',
            'coreCompetences' => 'ckeditor',
            'teachingArea' => 'ckeditor',
            'supervisedDoctoralThesis' => 'ckeditor',
            'supervisedThesis' => 'ckeditor',
            'miscellaneous' => 'ckeditor',
        ]);
    }

    private function createPluginControllerActionContext(): PluginControllerActionContext
    {
        $request = (new ServerRequest())->withAttribute(
            'extbase',
            new ExtbaseRequestParameters(),
        );

        return new PluginControllerActionContext(
            new Request($request),
            [],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function setGenderItems(array $items): void
    {
        $this->setSelectItems('gender', $items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function setSelectItems(string $fieldName, array $items): void
    {
        $GLOBALS['TCA'] = [
            'tx_academicpersons_domain_model_profile' => [
                'columns' => [
                    $fieldName => [
                        'config' => [
                            'items' => $items,
                        ],
                    ],
                ],
            ],
        ];
    }
}
