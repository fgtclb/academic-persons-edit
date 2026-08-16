<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\AbstractFormData;

/**
 * A form data object carrying one property of every shape the real DTOs use, so
 * that the property resolution of `AbstractFormDataValidator` can be pinned once
 * instead of per concrete validator: a string with a getter, a nullable int, a
 * boolean behind an `is*()` getter, and a property with no getter at all.
 */
final class TestFormData extends AbstractFormData
{
    protected string $readable = '';
    protected ?int $nullableYear = null;
    protected bool $flag = false;
    protected string $withoutGetter = 'never reachable';

    public function __construct(
        string $readable = '',
        ?int $nullableYear = null,
        bool $flag = false
    ) {
        $this->readable = $readable;
        $this->nullableYear = $nullableYear;
        $this->flag = $flag;
    }

    public function getReadable(): string
    {
        return $this->readable;
    }

    public function getNullableYear(): ?int
    {
        return $this->nullableYear;
    }

    public function isFlag(): bool
    {
        return $this->flag;
    }
}
