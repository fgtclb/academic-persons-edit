<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicBase\Settings\Exception\UnsuitableValidatorException;
use FGTCLB\AcademicPersonsEdit\Domain\Validator\AbstractFormDataValidator;

/**
 * Minimal concrete validator selecting the validation set of one profile section.
 */
final class TestFormDataValidator extends AbstractFormDataValidator
{
    public function __construct(
        private readonly string $sectionIdentifier = 'testSet'
    ) {}

    protected function isValid(mixed $value): void
    {
        if (!$value instanceof TestFormData) {
            throw new UnsuitableValidatorException('Not a valid test form data object.', 1755350003);
        }
        $this->processValidationSet(
            $value,
            $this->getAcademicPersonsSettings()->getProfileValidationSet($this->sectionIdentifier),
        );
    }
}
