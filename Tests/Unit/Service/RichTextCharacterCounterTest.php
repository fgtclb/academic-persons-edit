<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersonsEdit\Service\RichTextCharacterCounter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RichTextCharacterCounterTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('richTextValues')]
    public function countUsesNormalizedVisibleText(string $value, int $expected): void
    {
        $this->assertSame($expected, RichTextCharacterCounter::count($value));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function richTextValues(): array
    {
        return [
            'markup is excluded' => ['<p><strong>Five</strong></p>', 4],
            'entities are decoded' => ['<p>A&amp;B&nbsp;C</p>', 5],
            'whitespace is normalized' => ["<p>One</p>\n<p>  two </p>", 7],
            'multibyte characters count once' => ['<p>Grüße</p>', 5],
        ];
    }
}
