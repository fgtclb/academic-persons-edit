<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Attributes;

/**
 * Native enumeration class for supported list item sorting modes.
 *
 * @internal for use in `EXT:academic_persons_edit` only and not part of public API.
 */
enum ListSortingMode: string
{
    case NONE = 'none';
    case TOP = 'top';
    case BOTTOM = 'bottom';
    case UP = 'up';
    case DOWN = 'down';

    public static function tryFromDefault(string $value): static
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
