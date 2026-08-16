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
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ContractFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\ContractFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Validates the argument of `ContractController::createAction()` and
 * `updateAction()` against the validation set `contract`. `position` is the only
 * property the shipped `Settings.yaml` requires.
 */
final class ContractFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'contract';

    /**
     * The set identifier is the contract with `Settings.yaml`; an identifier that
     * does not match a registered set disables contract validation silently.
     */
    #[Test]
    public function theValidationSetContractIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['position' => [RecordingValidator::class]]),
            new ContractFormData(position: 'Professor')
        );

        $this->assertSame(['string(Professor)'], $this->messagesFor($result, 'position'));
    }

    #[Test]
    public function aValidationSetRegisteredUnderAnotherIdentifierIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier('contracts', ['position' => [RecordingValidator::class]]),
            new ContractFormData(position: 'Professor')
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * Unlike the other form data objects, this one carries relations and dates. A
     * validator configured for one of them is handed the object itself, and the
     * unset ones arrive as `null` - which is what `NotEmptyValidator` reports as
     * "must not be empty" once a project requires them.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
            new ContractFormData(
                validFrom: new \DateTime('2024-01-01'),
                position: 'Professor',
                room: 'B 2.14',
                officeHours: 'Tue 10-12',
                publish: true
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
            // Required in the shipped Configuration/AcademicPersons/Settings.yaml.
            'position' => ['position', 'string(Professor)'],
            // Readable, but not configured by default.
            'room' => ['room', 'string(B 2.14)'],
            'officeHours' => ['officeHours', 'string(Tue 10-12)'],
            'publish behind an is*() getter' => ['publish', 'bool(true)'],
            'validFrom as an object' => ['validFrom', 'DateTime'],
            'unset date' => ['validTo', 'null'],
            'unset relation' => ['organisationalUnit', 'null'],
            'unset function type' => ['functionType', 'null'],
            'unset location' => ['location', 'null'],
        ];
    }

    /**
     * A wrong argument type is a wiring mistake and must surface instead of letting
     * an unvalidated object through.
     */
    #[Test]
    #[DataProvider('unsuitableSubjects')]
    public function anythingButContractFormDataIsRejected(mixed $subject): void
    {
        $validator = new ContractFormDataValidator();
        $validator->injectAcademicPersonsSettings(ValidationSettings::forIdentifier(self::VALIDATION_SET, []));

        $this->expectException(UnsuitableValidatorException::class);
        $this->expectExceptionCode(1297418975);
        $this->expectExceptionMessage('Not a valid contract object.');

        $validator->validate($subject);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unsuitableSubjects(): array
    {
        return [
            'a sibling form data object' => [new ProfileFormData()],
            'an arbitrary object' => [new \stdClass()],
            'a non empty string' => ['not an object'],
        ];
    }

    private function validate(AcademicPersonsSettings $settings, mixed $subject): Result
    {
        $validator = new ContractFormDataValidator();
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
