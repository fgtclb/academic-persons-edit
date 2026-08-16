<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * A validator that always fails and reports the value it was handed as the error
 * message. The real validators configured through `Settings.yaml` are
 * `NotEmptyValidator` and `EmailAddressValidator`, and both translate their message
 * through `LocalizationUtility`, which needs a container. Recording the received
 * value here is what makes the property resolution of
 * `AbstractFormDataValidator::processValidations()` observable in a unit test.
 */
final class RecordingValidator extends AbstractValidator
{
    public const ERROR_CODE = 1755350001;

    /**
     * The value must be inspected even when it is null or an empty string, which is
     * exactly the case a required property produces.
     *
     * @var bool
     */
    protected $acceptsEmptyValues = false;

    public function isValid(mixed $value): void
    {
        $this->addError(self::describe($value), self::ERROR_CODE);
    }

    /**
     * A short, stable rendering of a value, so that a test can assert on both the
     * type and the content of what a validator was given.
     */
    public static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool(' . ($value ? 'true' : 'false') . ')',
            is_int($value) => 'int(' . $value . ')',
            is_string($value) => 'string(' . $value . ')',
            default => get_debug_type($value),
        };
    }
}
