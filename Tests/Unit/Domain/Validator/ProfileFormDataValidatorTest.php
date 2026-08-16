<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ProfileFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Validates the argument of `ProfileController::updateAction()` against the
 * validation set `profile`. The profile form is the largest of the six and the one
 * an integrator is most likely to extend, so the resolvability of every property is
 * what matters here rather than a single required field.
 */
final class ProfileFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'profile';

    #[Test]
    public function theValidationSetProfileIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['lastName' => [RecordingValidator::class]]),
            new ProfileFormData(lastName: 'Doe')
        );

        $this->assertSame(['string(Doe)'], $this->messagesFor($result, 'lastName'));
    }

    #[Test]
    public function aValidationSetRegisteredUnderAnotherIdentifierIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier('profiles', ['lastName' => [RecordingValidator::class]]),
            new ProfileFormData(lastName: 'Doe')
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * The shipped configuration names `firstName`, `middleName` and `lastName`; the
     * remaining ones are what a project adds. All of them have to resolve off the
     * DTO, because an unreadable property silently becomes `null` and would then be
     * reported as empty on every submit.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
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
            // Named in the shipped Configuration/AcademicPersons/Settings.yaml.
            'firstName' => ['firstName', 'string(Jane)'],
            'middleName' => ['middleName', 'string(M.)'],
            'lastName' => ['lastName', 'string(Doe)'],
            // Readable, but not configured by default.
            'title' => ['title', 'string(Dr.)'],
            'gender' => ['gender', 'string(female)'],
            'publicationsLink' => ['publicationsLink', 'string(https://example.org/pub)'],
            'publicationsLinkTitle' => ['publicationsLinkTitle', 'string(Publications)'],
            'website' => ['website', 'string(https://example.org)'],
            'websiteTitle' => ['websiteTitle', 'string(Homepage)'],
            'coreCompetences' => ['coreCompetences', 'string(Physics)'],
            'miscellaneous' => ['miscellaneous', 'string(Misc)'],
            'supervisedDoctoralThesis' => ['supervisedDoctoralThesis', 'string(Doctoral)'],
            'supervisedThesis' => ['supervisedThesis', 'string(Thesis)'],
            'teachingArea' => ['teachingArea', 'string(Optics)'],
            'skipSync' => ['skipSync', 'bool(true)'],
        ];
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
        $validator->injectAcademicPersonsSettings(ValidationSettings::forIdentifier(self::VALIDATION_SET, []));

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

    private function validate(AcademicPersonsSettings $settings, mixed $subject): Result
    {
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
