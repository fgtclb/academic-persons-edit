<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator;

use FGTCLB\AcademicBase\Settings\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The profile validator selects submitted fields from all configured profile
 * sections and ignores configured relation fields which are not part of its DTO.
 */
final class ProfileFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'information';

    #[Test]
    public function aConfiguredProfileSectionIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forProfileSection(self::VALIDATION_SET, ['lastName' => [RecordingValidator::class]]),
            new ProfileFormData(lastName: 'Doe'),
            ['lastName'],
        );
        $this->assertSame(['string(Doe)'], $this->messagesFor($result, 'lastName'));
    }

    #[Test]
    public function aConfiguredRelationFieldThatIsNotPartOfTheProfileDtoIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forProfileSection('information', ['email' => [RecordingValidator::class]]),
            new ProfileFormData(lastName: 'Doe')
        );
        $this->assertFalse($result->hasErrors());
    }

    #[Test]
    public function everyConfiguredProfileSectionKeepsAndProcessesItsOwnValidationSet(): void
    {
        $information = ValidationSettings::forProfileSection(
            'information',
            ['gender' => [RecordingValidator::class]],
        );
        $aboutme = ValidationSettings::forProfileSection(
            'aboutme',
            ['miscellaneous' => [RecordingValidator::class]],
        );
        $settings = new AcademicPersonsSettings(
            profileSections: array_replace($information->profileSections, $aboutme->profileSections),
        );
        $result = $this->validate(
            $settings,
            new ProfileFormData(gender: 'female', miscellaneous: 'About'),
            ['gender', 'miscellaneous'],
        );
        $this->assertSame(['string(female)'], $this->messagesFor($result, 'gender'));
        $this->assertSame(['string(About)'], $this->messagesFor($result, 'miscellaneous'));
    }

    #[Test]
    public function aConfiguredPropertyMissingFromThePartialSubmissionIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forProfileSection(self::VALIDATION_SET, ['gender' => [RecordingValidator::class]]),
            new ProfileFormData(gender: ''),
        );
        $this->assertFalse($result->hasErrors());
    }

    #[Test]
    public function aRegisteredOverrideIsValidatedInsteadOfThePersistedValue(): void
    {
        $formData = new ProfileFormData(lastName: 'Persisted');
        $formData->setPropertyOverride('lastName', 'Submitted');
        $result = $this->validate(
            ValidationSettings::forProfileSection(self::VALIDATION_SET, ['lastName' => [RecordingValidator::class]]),
            $formData,
        );
        $this->assertSame(['string(Submitted)'], $this->messagesFor($result, 'lastName'));
    }

    #[Test]
    public function configuredProfileRichTextCharacterLimitCountsVisibleTextWithoutMarkup(): void
    {
        $settings = ValidationSettings::forProfileSection(
            'aboutme',
            [],
            characterLimits: ['miscellaneous' => 5],
        );
        $valid = new ProfileFormData(miscellaneous: 'Persisted');
        $valid->setPropertyOverride('miscellaneous', '<p><strong>12345</strong></p>');
        $this->assertSame([], $this->messagesFor($this->validate($settings, $valid), 'miscellaneous'));
        $invalid = new ProfileFormData(miscellaneous: 'Persisted');
        $invalid->setPropertyOverride('miscellaneous', '<p><strong>123456</strong></p>');
        $this->assertSame(
            ['The text must not exceed %d characters.'],
            $this->messagesFor($this->validate($settings, $invalid), 'miscellaneous'),
        );
    }

    /**
     * All direct profile properties used by the profile editor must resolve off the DTO.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forProfileSection(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
            new ProfileFormData(
                title: 'Dr.',
                firstName: 'Jane',
                middleName: 'M.',
                lastName: 'Doe',
                gender: 'female',
                publicationsLink: 'https://example.org/pub',
                publicationsLinkTitle: 'Publications',
                website: 'https://example.org',
                websiteTitle: 'Homepage',
                coreCompetences: 'Physics',
                miscellaneous: 'Misc',
                supervisedDoctoralThesis: 'Doctoral',
                supervisedThesis: 'Thesis',
                teachingArea: 'Optics',
                skipSync: true
            ),
            [$property],
        );
        $this->assertSame([$expectedDescription], $this->messagesFor($result, $property));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function configuredProperties(): array
    {
        return [
            // Named in the shipped academic-persons/Configuration/AcademicPersons/Settings.yaml.
            'gender' => ['gender', 'string(female)'],
            'firstName' => ['firstName', 'string(Jane)'],
            'middleName' => ['middleName', 'string(M.)'],
            'lastName' => ['lastName', 'string(Doe)'],
            'publicationsLink' => ['publicationsLink', 'string(https://example.org/pub)'],
            'website' => ['website', 'string(https://example.org)'],
            'coreCompetences' => ['coreCompetences', 'string(Physics)'],
            'miscellaneous' => ['miscellaneous', 'string(Misc)'],
            'supervisedDoctoralThesis' => ['supervisedDoctoralThesis', 'string(Doctoral)'],
            'supervisedThesis' => ['supervisedThesis', 'string(Thesis)'],
            'teachingArea' => ['teachingArea', 'string(Optics)'],
            // Direct title and generated combined-link companion properties remain readable.
            'title' => ['title', 'string(Dr.)'],
            'publicationsLinkTitle' => ['publicationsLinkTitle', 'string(Publications)'],
            'websiteTitle' => ['websiteTitle', 'string(Homepage)'],
        ];
    }

    #[Test]
    public function aDirectSpecialPropertyIsValidatedFromItsOwnConfiguration(): void
    {
        $settings = ValidationSettings::forProfileSection(self::VALIDATION_SET, []);
        $formData = new ProfileFormData(skipSync: false);
        $formData->setPropertyOverride('skipSync', true);
        // Replace the special validation with a recording validator while keeping
        // it outside every regular profile section.
        $special = $settings->getSpecialField('skipSync');
        $this->assertNotNull($special);
        $settings = new AcademicPersonsSettings(
            profileSections: $settings->profileSections,
            specialFields: [
                'skipSync' => new \FGTCLB\AcademicPersons\Settings\SpecialField(
                    identifier: 'skipSync',
                    type: 'special',
                    fieldType: 'check',
                    renderType: 'checkbox',
                    fieldIdentifiers: [],
                    validation: new \FGTCLB\AcademicBase\Settings\Validation(
                        identifier: 'skipSync',
                        fieldName: 'skip_sync',
                        required: false,
                        disabled: false,
                        readOnly: false,
                        validatorClassNames: [RecordingValidator::class],
                        tcaConfig: ['type' => 'check'],
                        inputType: 'checkbox',
                    ),
                    position: 0,
                ),
            ],
        );
        $result = $this->validate($settings, $formData);
        $result = $this->validate($settings, $formData);
        $this->assertSame(['bool(true)'], $this->messagesFor($result, 'skipSync'));
    }

    /**
     * A wrong argument type is a wiring mistake and must surface instead of letting
     * an unvalidated object through.
     */
    #[Test]
    #[DataProvider('unsuitableSubjects')]
    public function anythingButProfileFormDataIsRejected(mixed $subject): void
    {
        $validator = new ProfileFormDataValidator();
        $validator->injectAcademicPersonsSettings(ValidationSettings::forProfileSection(self::VALIDATION_SET, []));
        $this->expectException(UnsuitableValidatorException::class);
        $this->expectExceptionCode(1297418975);
        $this->expectExceptionMessage('Not a valid profile object.');
        $validator->validate($subject);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsuitableSubjects(): array
    {
        return [
            'a sibling form data object' => [new PhoneNumberFormData()],
            'an arbitrary object' => [new \stdClass()],
            'a non empty string' => ['not an object'],
        ];
    }

    /**
     * @param list<string> $submittedProperties
     */
    private function validate(
        AcademicPersonsSettings $settings,
        mixed $subject,
        array $submittedProperties = [],
    ): Result {
        if ($subject instanceof ProfileFormData) {
            foreach ($submittedProperties as $property) {
                $subject->setPropertyOverride($property, $subject->_getProperty($property));
            }
        }
        $validator = new ProfileFormDataValidator();
        $validator->injectAcademicPersonsSettings($settings);
        return $validator->validate($subject);
    }

    /**
     * @return array<int, string>
     */
    private function messagesFor(Result $result, string $property): array
    {
        return array_map(
            static fn($error): string => $error->getMessage(),
            $result->forProperty($property)->getErrors()
        );
    }
}
