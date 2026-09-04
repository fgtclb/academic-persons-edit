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
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AddressFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\EmailFormData;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\AddressFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Validates the argument of `PhysicalAddressController::createAction()` and
 * `updateAction()` against the validation set `physicalAddress`.
 */
final class AddressFormDataValidatorTest extends UnitTestCase
{
    private const VALIDATION_SET = 'physicalAddress';

    /**
     * The set identifier is the contract with `Settings.yaml`. Changing it - or
     * changing the key in the yaml file - disables address validation completely
     * rather than raising, because an unregistered set validates nothing.
     */
    #[Test]
    public function theValidationSetPhysicalAddressIsProcessed(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['city' => [RecordingValidator::class]]),
            new AddressFormData(city: 'Munich')
        );

        $this->assertSame(['string(Munich)'], $this->messagesFor($result, 'city'));
    }

    #[Test]
    public function aValidationSetRegisteredUnderAnotherIdentifierIsIgnored(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier('address', ['city' => [RecordingValidator::class]]),
            new AddressFormData(city: 'Munich')
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * The property names in `Settings.yaml` are resolved off the DTO through
     * `ObjectAccess`, which yields `null` for anything it cannot read instead of
     * raising. Renaming a DTO property without touching the yaml file would
     * therefore keep validating - against `null` - and a required address field
     * would become unsaveable. Every property the shipped configuration names is
     * pinned here to prove it still resolves to the submitted value.
     */
    #[Test]
    #[DataProvider('configuredProperties')]
    public function aConfiguredPropertyResolvesToTheSubmittedValue(string $property, string $expectedDescription): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, [$property => [RecordingValidator::class]]),
            new AddressFormData(
                street: 'Bahnhofstrasse',
                streetNumber: '12a',
                additional: 'c/o Faculty',
                zip: '80331',
                city: 'Munich',
                state: 'Bavaria',
                country: 'DE',
                type: 'work'
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
            'street' => ['street', 'string(Bahnhofstrasse)'],
            'streetNumber' => ['streetNumber', 'string(12a)'],
            'zip' => ['zip', 'string(80331)'],
            'city' => ['city', 'string(Munich)'],
            'country' => ['country', 'string(DE)'],
            // Not configured by default, but readable, so a project may require them.
            'additional' => ['additional', 'string(c/o Faculty)'],
            'state' => ['state', 'string(Bavaria)'],
            'type' => ['type', 'string(work)'],
        ];
    }

    /**
     * The counter example to the test above: this is what a stale property name in
     * `Settings.yaml` looks like from the inside.
     */
    #[Test]
    public function aPropertyTheFormDataDoesNotHaveIsValidatedAsNull(): void
    {
        $result = $this->validate(
            ValidationSettings::forIdentifier(self::VALIDATION_SET, ['houseNumber' => [RecordingValidator::class]]),
            new AddressFormData(streetNumber: '12a')
        );

        $this->assertSame(['null'], $this->messagesFor($result, 'houseNumber'));
    }

    /**
     * The validator is wired per controller argument, so a mismatch is a programming
     * error and has to surface instead of passing an unvalidated object on.
     */
    #[Test]
    #[DataProvider('unsuitableSubjects')]
    public function anythingButAddressFormDataIsRejected(mixed $subject): void
    {
        $validator = new AddressFormDataValidator();
        $validator->injectAcademicPersonsSettings(ValidationSettings::forIdentifier(self::VALIDATION_SET, []));

        $this->expectException(UnsuitableValidatorException::class);
        $this->expectExceptionCode(1297418975);
        $this->expectExceptionMessage('Not a valid address object.');

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
        $validator = new AddressFormDataValidator();
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
