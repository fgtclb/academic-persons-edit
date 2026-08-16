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
 * A validator that accepts everything, used to prove that a configured property
 * only contributes errors when one of its validators produces one.
 */
final class SilentValidator extends AbstractValidator
{
    /**
     * @var bool
     */
    protected $acceptsEmptyValues = false;

    public function isValid(mixed $value): void {}
}
