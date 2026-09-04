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
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\PhoneNumberFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\PhoneNumberFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Validates the argument of `PhoneNumberController::createAction()` and
 * `updateAction()` against the contact section `phoneNumbers`. Set identifier and
 * property name are spelled the same here, which makes a mix-up of the two easy
 * and invisible - both are pinned separately below.
 */
final class PhoneNumberFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'phoneNumbers';

    #[Test]
    public function theSectionPhoneNumberIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['phoneNumber' => [RecordingValidator::class]]),
            new PhoneNumberFormData(phoneNumber: '+49 89 123456')
        );

        $this->assertSame(['string(+49 89 123456)'], $this->messagesFor($result, 'phoneNumber'));
    }

    #[Test]
    public function aValidationSetRegisteredUnderAnotherIdentifierIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier('phones', ['phoneNumber' => [RecordingValidator::class]]),
            new PhoneNumberFormData(phoneNumber: '+49 89 123456')
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * Both properties the shipped configuration requires have to resolve off the
     * DTO; a rename would degrade them to `null` instead of raising.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
            new PhoneNumberFormData(phoneNumber: '+49 89 123456', type: 'mobile')
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
            'phoneNumber' => ['phoneNumber', 'string(+49 89 123456)'],
            'type' => ['type', 'string(mobile)'],
        ];
    }

    /**
     * A wrong argument type is a wiring mistake and must surface instead of letting
     * an unvalidated object through.
     */
    #[Test]
    #[DataProvider('unsuitableSubjects')]
    public function anythingButPhoneNumberFormDataIsRejected(mixed $subject): void
    {
        $validator = new PhoneNumberFormDataValidator();
        $validator->injectAcademicPersonsSettings(ValidationSettings::forIdentifier(self::VALIDATION_SET, []));

        $this->expectException(UnsuitableValidatorException::class);
        $this->expectExceptionCode(1297418975);
        $this->expectExceptionMessage('Not a valid phone number object.');

        $validator->validate($subject);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsuitableSubjects(): array
    {
        return [
            'a sibling form data object' => [new ContractFormData()],
            'an arbitrary object' => [new \stdClass()],
            'a non empty string' => ['+49 89 123456'],
        ];
    }

    private function validate(AcademicPersonsSettings $settings, mixed $subject): Result
    {
        $validator = new PhoneNumberFormDataValidator();
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
