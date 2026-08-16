<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicPersonsEdit\Domain\Validator\AbstractFormDataValidator;
use FGTCLB\AcademicPersonsEdit\Exception\UnsuitableValidatorException;

/**
 * The minimal concrete validator, shaped exactly like the six shipped ones: a type
 * guard, then `processValidations()` with a validation set identifier.
 * `processValidations()` is public but writes into `$this->result`,
 * which only `AbstractValidator::validate()` initializes, so the shared machinery
 * can only be exercised through a subclass.
 */
final class TestFormDataValidator extends AbstractFormDataValidator
{
    public function __construct(
        private readonly string $validationsIdentifier = 'testSet'
    ) {}

    protected function isValid(mixed $value): void
    {
        if (!$value instanceof TestFormData) {
            throw new UnsuitableValidatorException('Not a valid test form data object.', 1755350003);
        }
        $this->processValidations($value, $this->validationsIdentifier);
    }
}
