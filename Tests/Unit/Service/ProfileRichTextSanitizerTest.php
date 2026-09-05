<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizer;
use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerBuilder;
use FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Validator\Fixtures\ValidationSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileRichTextSanitizerTest extends UnitTestCase
{
    #[Test]
    public function allowedEditorMarkupIsPreserved(): void
    {
        $subject = $this->createSubject();
        $value = '<p><strong>Strong</strong> and <em>emphasized</em></p>'
            . '<ul><li><a href="https://example.com/path">Linked item</a></li></ul>';
        $result = $subject->sanitize($value);
        $this->assertStringContainsString('<p><strong>Strong</strong> and <em>emphasized</em></p>', $result);
        $this->assertStringContainsString('<ul><li><a href="https://example.com/path">Linked item</a></li></ul>', $result);
    }

    #[Test]
    public function unsafeMarkupAttributesAndProtocolsAreRejected(): void
    {
        $subject = $this->createSubject();
        $result = $subject->sanitize(
            '<script>alert(1)</script>'
            . '<p class="danger" onclick="alert(2)">Text</p>'
            . '<a href="javascript:alert(3)" style="color:red">Link</a>'
            . '<img src="x" onerror="alert(4)">',
        );
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('style=', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringNotContainsString('class=', $result);
        $this->assertStringContainsString('<p>Text</p>', $result);
    }

    #[Test]
    #[DataProvider('supportedPropertyNames')]
    public function onlyConfiguredContentPropertiesAreSupported(
        string $propertyName,
        bool $expected,
    ): void {
        $this->assertSame($expected, $this->createSubject()->supports($propertyName));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function supportedPropertyNames(): array
    {
        return [
            'core competences' => ['coreCompetences', true],
            'teaching area' => ['teachingArea', true],
            'doctoral theses' => ['supervisedDoctoralThesis', true],
            'theses' => ['supervisedThesis', true],
            'miscellaneous' => ['miscellaneous', true],
            'plain profile field' => ['firstName', false],
            'unknown field' => ['unknown', false],
        ];
    }

    private function createSubject(): ProfileRichTextSanitizer
    {
        return new ProfileRichTextSanitizer(
            new ProfileRichTextSanitizerBuilder(),
            ValidationSettings::forProfileFields([
                'coreCompetences' => 'ckeditor',
                'teachingArea' => 'ckeditor',
                'supervisedDoctoralThesis' => 'ckeditor',
                'supervisedThesis' => 'ckeditor',
                'miscellaneous' => 'ckeditor',
                'firstName' => 'text',
            ]),
        );
    }
}
