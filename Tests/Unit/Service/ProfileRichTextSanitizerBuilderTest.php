<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Service;

use FGTCLB\AcademicPersonsEdit\Service\ProfileRichTextSanitizerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The allow list the profile editor's rich text is stored with.
 *
 * `ProfileRichTextSanitizerTest` covers the service that decides *which* property is
 * sanitized; this covers the behaviour the builder configures, which is what actually
 * decides what a visitor of the public profile gets to see. The tags are the ones the
 * CKEditor configuration of the editor can produce - anything else reaching the column
 * came from somewhere the editor is not.
 */
final class ProfileRichTextSanitizerBuilderTest extends UnitTestCase
{
    private function sanitize(string $value): string
    {
        return trim((new ProfileRichTextSanitizerBuilder())->build()->sanitize($value));
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function allowedMarkupProvider(): \Generator
    {
        yield 'paragraph' => ['<p>Text</p>', '<p>Text</p>'];
        yield 'line break' => ['<p>One<br>Two</p>', '<p>One<br>Two</p>'];
        yield 'bold' => ['<p><strong>Bold</strong></p>', '<p><strong>Bold</strong></p>'];
        yield 'italic' => ['<p><em>Italic</em></p>', '<p><em>Italic</em></p>'];
        yield 'unordered list' => ['<ul><li>Item</li></ul>', '<ul><li>Item</li></ul>'];
        yield 'ordered list' => ['<ol><li>Item</li></ol>', '<ol><li>Item</li></ol>'];
    }

    #[Test]
    #[DataProvider('allowedMarkupProvider')]
    public function theMarkupTheEditorProducesSurvives(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->sanitize($value));
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function allowedLinkProvider(): \Generator
    {
        yield 'http' => ['<a href="http://example.org/x">Link</a>', 'href="http://example.org/x"'];
        yield 'https' => ['<a href="https://example.org/x">Link</a>', 'href="https://example.org/x"'];
        yield 'mailto' => ['<a href="mailto:info@example.org">Mail</a>', 'href="mailto:info@example.org"'];
        yield 'tel' => ['<a href="tel:+4912345">Phone</a>', 'href="tel:+4912345"'];
        yield 'relative' => ['<a href="/page">Local</a>', 'href="/page"'];
    }

    #[Test]
    #[DataProvider('allowedLinkProvider')]
    public function theLinkSchemesOfTheEditorSurvive(string $value, string $expectedHref): void
    {
        $this->assertStringContainsString($expectedHref, $this->sanitize($value));
    }

    /**
     * @return \Generator<string, array{0: string, 1: string}>
     */
    public static function rejectedMarkupProvider(): \Generator
    {
        yield 'script element' => ['<p>Text</p><script>alert(1)</script>', '<script'];
        yield 'style element' => ['<p>Text</p><style>p{}</style>', '<style'];
        yield 'iframe' => ['<p>Text</p><iframe src="https://example.org"></iframe>', '<iframe'];
        yield 'svg' => ['<p>Text</p><svg><use href="#x"/></svg>', '<svg'];
        yield 'event handler' => ['<p onclick="alert(1)">Text</p>', 'onclick'];
        yield 'style attribute' => ['<p style="color:red">Text</p>', 'style='];
        yield 'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript:'];
        yield 'data link' => ['<a href="data:text/html;base64,PHA+">Link</a>', 'data:text/html'];
        yield 'heading' => ['<h1>Heading</h1>', '<h1'];
        yield 'image' => ['<p><img src="https://example.org/x.png" alt=""></p>', '<img'];
        yield 'table' => ['<table><tr><td>Cell</td></tr></table>', '<table'];
        yield 'comment' => ['<p>Text</p><!-- secret -->', '<!--'];
    }

    #[Test]
    #[DataProvider('rejectedMarkupProvider')]
    public function everythingElseIsRemoved(string $value, string $forbidden): void
    {
        $this->assertStringNotContainsString($forbidden, $this->sanitize($value));
    }

    /**
     * The text of an unexpected element goes with the element, because the behaviour
     * declares `REMOVE_UNEXPECTED_CHILDREN`. That is deliberate: what CKEditor cannot
     * produce did not come from the editor, and keeping its text would keep half of a
     * payload. An element that only wraps allowed content, such as a paragraph inside
     * a list item, is the case that keeps its text.
     */
    #[Test]
    public function theTextOfAnUnexpectedElementIsRemovedWithIt(): void
    {
        $this->assertSame('', $this->sanitize('<h1>Heading text</h1>'));
        $this->assertStringContainsString('Item text', $this->sanitize('<ul><li>Item text</li></ul>'));
    }
}
