<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator;

use FGTCLB\AcademicBase\Settings\Exception\UnknownValidatorException;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\NotAValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\RecordingValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\SilentValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\TestFormData;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\TestFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `AbstractFormDataValidator::processValidations()` is the only piece of validation
 * logic in this extension - the six concrete validators do nothing but a type guard
 * and a call into it with their validation set identifier. Everything a profile
 * editor is allowed to save therefore goes through this one method, driven by
 * `Configuration/AcademicPersons/Settings.yaml`, which an integrator overrides.
 */
final class AbstractFormDataValidatorTest extends UnitTestCase
{
    /**
     * The value a validator sees is what ends up deciding acceptance, so an error
     * message carrying it (see `RecordingValidator`) is the assertion subject here.
     */
    #[Test]
    public function anErrorIsAttachedToThePropertyItsValidatorWasConfiguredFor(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $this->assertSame(
            ['readable' => ['string(submitted)']],
            $this->messagesByProperty($subject->validate(new TestFormData('submitted')))
        );
    }

    /**
     * The error code is what a template or a listener can branch on; it is the code
     * of the failing validator, not one of this extension's own.
     */
    #[Test]
    public function theErrorCodeOfTheFailingValidatorIsKept(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $errors = $subject->validate(new TestFormData())->forProperty('readable')->getErrors();

        $this->assertCount(1, $errors);
        $this->assertSame(RecordingValidator::ERROR_CODE, $errors[0]->getCode());
    }

    /**
     * An identifier that no `Settings.yaml` registers must not raise - it means
     * "nothing configured for this form", which is a valid state for a project that
     * only overrides one of the six sets.
     */
    #[Test]
    public function anUnregisteredValidationSetValidatesNothing(): void
    {
        $subject = new TestFormDataValidator('setThatNobodyRegistered');
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $this->assertFalse($subject->validate(new TestFormData())->hasErrors());
    }

    /**
     * `Settings.yaml` maps a property to a list, and a required email field maps to
     * both `NotEmptyValidator` and `EmailAddressValidator` - dropping the second one
     * silently would let any string through.
     */
    #[Test]
    public function everyValidatorConfiguredForAPropertyRuns(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', [
                'readable' => [SilentValidator::class, RecordingValidator::class, RecordingValidator::class],
            ])
        );

        $this->assertSame(
            ['readable' => ['string()', 'string()']],
            $this->messagesByProperty($subject->validate(new TestFormData()))
        );
    }

    /**
     * Every configured property is validated, and each keeps its own error bucket -
     * the profile edit templates render the errors per field.
     */
    #[Test]
    public function everyConfiguredPropertyIsValidatedSeparately(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', [
                'readable' => [RecordingValidator::class],
                'nullableYear' => [RecordingValidator::class],
                'flag' => [SilentValidator::class],
            ])
        );

        $this->assertSame(
            ['readable' => ['string(a)'], 'nullableYear' => ['int(1999)']],
            $this->messagesByProperty($subject->validate(new TestFormData('a', 1999)))
        );
    }

    /**
     * The DTO properties are `protected`, so the value has to be reached through the
     * public getter - including the `is*()` form a boolean uses. A property that has
     * no getter, or no declaration at all, is silently handed over as `null` instead
     * of raising: a typo in an integrator's `Settings.yaml` therefore turns into a
     * "must not be empty" error on a field the form never rendered.
     *
     * @param 'readable'|'nullableYear'|'flag'|'withoutGetter'|'notDeclaredAtAll' $property
     */
    #[Test]
    #[DataProvider('propertyValues')]
    public function aPropertyValueIsResolvedThroughItsPublicGetter(string $property, string $expectedDescription): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', [$property => [RecordingValidator::class]])
        );

        $this->assertSame(
            [$property => [$expectedDescription]],
            $this->messagesByProperty($subject->validate(new TestFormData('text', 2024, true)))
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function propertyValues(): array
    {
        return [
            'string behind a get*() getter' => ['readable', 'string(text)'],
            'nullable int behind a get*() getter' => ['nullableYear', 'int(2024)'],
            'boolean behind an is*() getter' => ['flag', 'bool(true)'],
            'declared property without a getter' => ['withoutGetter', 'null'],
            'property that does not exist' => ['notDeclaredAtAll', 'null'],
        ];
    }

    /**
     * `Settings.yaml` is normalized into class names without any check, so an
     * integrator override reaches `GeneralUtility::makeInstance()` unverified. The
     * guard has to be loud, because a silently skipped entry would mean a field that
     * is no longer validated at all.
     */
    #[Test]
    public function aConfiguredClassThatIsNoValidatorRaises(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [NotAValidator::class]])
        );

        $this->expectException(UnknownValidatorException::class);
        $this->expectExceptionCode(1702379249);
        $this->expectExceptionMessage('Unknown validator');

        $subject->validate(new TestFormData());
    }

    /**
     * The counterpart of `Domain/Factory/*`: those ask `shouldApplyProperty()` before
     * they write a value onto the domain model, so a property that was not submitted
     * is not applied. `processValidations()` does not ask - it reads every configured
     * property off the DTO, where an unsubmitted one still carries its declared
     * default. A partial form submit is therefore validated as if the missing fields
     * had been sent empty, and a required field that a template does not render at
     * all can never be saved.
     */
    #[Test]
    public function aPropertyThatWasNotSubmittedIsValidatedNevertheless(): void
    {
        $formData = new TestFormData();
        $parameters = new ExtbaseRequestParameters();
        $parameters->setArguments(['testFormData' => ['flag' => '1']]);
        $formData->setRequest((new ServerRequest())->withAttribute('extbase', $parameters));
        $formData->setArgumentName('testFormData');

        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $this->assertFalse($formData->shouldApplyProperty('readable'));
        $this->assertSame(
            ['readable' => ['string()']],
            $this->messagesByProperty($subject->validate($formData))
        );
    }

    /**
     * `AbstractValidator::validate()` skips `isValid()` for `null` and `''` because
     * `$acceptsEmptyValues` is left at its default. Neither the type guard of the
     * concrete validators nor any configured validation therefore runs for them: an
     * argument that failed property mapping arrives as `null` and passes validation
     * unnoticed, and the guard that would have rejected it is never reached.
     */
    #[Test]
    #[DataProvider('emptyValues')]
    public function anEmptyArgumentIsNotValidatedAtAll(mixed $value): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $this->assertFalse($subject->validate($value)->hasErrors());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function emptyValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    /**
     * A validator instance is reused for both the create and the update action of a
     * controller, so a result left over from a previous run would report an error on
     * a submit that is fine.
     */
    #[Test]
    public function aResultIsNotCarriedOverIntoTheNextRun(): void
    {
        $subject = new TestFormDataValidator();
        $subject->injectAcademicPersonsSettings(
            ValidationSettings::forIdentifier('testSet', ['readable' => [RecordingValidator::class]])
        );

        $subject->validate(new TestFormData('first'));

        $this->assertSame(
            ['readable' => ['string(second)']],
            $this->messagesByProperty($subject->validate(new TestFormData('second')))
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function messagesByProperty(Result $result): array
    {
        $messages = [];
        foreach ($result->getFlattenedErrors() as $propertyPath => $errors) {
            foreach ($errors as $error) {
                $messages[$propertyPath][] = $error->getMessage();
            }
        }
        return $messages;
    }
}
