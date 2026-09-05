<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

/**
 * Counts normalized visible characters in rich text without counting markup.
 *
 * @internal not part of public API.
 */
final class RichTextCharacterCounter
{
    public static function count(string $value): int
    {
        $plainText = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $plainText = str_replace("\u{00a0}", ' ', $plainText);
        $plainText = preg_replace('/\s+/u', ' ', $plainText) ?? $plainText;
        return mb_strlen(trim($plainText), 'UTF-8');
    }
}
