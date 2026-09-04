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
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileInformationFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileInformationFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Validates the argument of `ProfileInformationController::createAction()` and
 * `updateAction()` against the validation set `profileInformation`. This is the one
 * form data object whose required properties are not strings: `year` is `?int`, so
 * "not submitted" and "submitted empty" both arrive as `null` rather than as `''`.
 */
final class ProfileInformationFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'profileInformation';

    #[Test]
    public function theValidationSetProfileInformationIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['title' => [RecordingValidator::class]]),
            new ProfileInformationFormData(title: 'A publication')
        );

        $this->assertSame(['string(A publication)'], $this->messagesFor($result, 'title'));
    }

    #[Test]
    public function aValidationSetRegisteredUnderAnotherIdentifierIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier('profileInformations', ['title' => [RecordingValidator::class]]),
            new ProfileInformationFormData(title: 'A publication')
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * `title` and `year` are the two the shipped configuration requires; the others
     * are what a project adds. All of them have to resolve off the DTO, because an
     * unreadable property silently becomes `null`.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
            new ProfileInformationFormData(
                type: 'publication',
                title: 'A publication',
                bodytext: 'Some text',
                link: 'https://example.org',
                year: 2024,
                yearStart: 2020,
                yearEnd: 2023
            )
        );

        $this->assertSame([$expectedDescription], $this->messagesFor($result, $property));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function configuredProperties(): array
    {
        return [
            // Both required in the shipped Configuration/AcademicPersons/Settings.yaml.
            'title' => ['title', 'string(A publication)'],
            'year' => ['year', 'int(2024)'],
            // Readable, but not configured by default.
            'type' => ['type', 'string(publication)'],
            'bodytext' => ['bodytext', 'string(Some text)'],
            'link' => ['link', 'string(https://example.org)'],
            'yearStart' => ['yearStart', 'int(2020)'],
            'yearEnd' => ['yearEnd', 'int(2023)'],
        ];
    }

    /**
     * A `?int` property has no empty-string state: an omitted or non numeric `year`
     * arrives as `null`, which `NotEmptyValidator` reports with its *null* message
     * and code 1221560910 rather than the empty one. That distinction is what a
     * template branching on the error code sees.
     */
    #[Test]
    public function anOmittedNullableIntIsHandedOverAsNull(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['year' => [RecordingValidator::class]]),
            new ProfileInformationFormData(title: 'A publication')
        );

        $this->assertSame(['null'], $this->messagesFor($result, 'year'));
    }

    /**
     * A wrong argument type is a wiring mistake and must surface instead of letting
     * an unvalidated object through.
     */
    #[Test]
    #[DataProvider('unsuitableSubjects')]
    public function anythingButProfileInformationFormDataIsRejected(mixed $subject): void
    {
        $validator = new ProfileInformationFormDataValidator();
        $validator->injectAcademicPersonsSettings(ValidationSettings::forIdentifier(self::VALIDATION_SET, []));

        $this->expectException(UnsuitableValidatorException::class);
        $this->expectExceptionCode(1297418975);
        $this->expectExceptionMessage('Not a valid profile information object.');

        $validator->validate($subject);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsuitableSubjects(): array
    {
        return [
            'a sibling form data object' => [new EmailFormData()],
            'an arbitrary object' => [new \stdClass()],
            'a non empty string' => ['not an object'],
        ];
    }

    private function validate(AcademicPersonsSettings $settings, mixed $subject): Result
    {
        $validator = new ProfileInformationFormDataValidator();
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
