<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every field of the profile editor has a label in every language file.
 *
 * The form partials compose the label key from the arguments a template passes,
 * `{element.form}.{element.identifier}.label`, so a key that does not match a unit of
 * the language file renders an empty label without any message. The e-mail address
 * field asked for `emailAddress.email.label` while the files declared
 * `emailAddress.emailAddress.label` (ACE-853).
 */
final class FormFieldLabelsTest extends UnitTestCase
{
    private const RESOURCES = __DIR__ . '/../../../Resources/Private/';

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function languageFileDataProvider(): \Generator
    {
        yield 'English' => ['locallang.xlf'];
        yield 'German' => ['de.locallang.xlf'];
    }

    #[Test]
    #[DataProvider('languageFileDataProvider')]
    public function everyFormFieldOfTheTemplatesHasALabel(string $languageFile): void
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->load(self::RESOURCES . 'Language/' . $languageFile));
        $units = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $units[$unit->getAttribute('id')] = true;
        }

        $fields = [];
        $templates = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            self::RESOURCES,
            \FilesystemIterator::SKIP_DOTS,
        ));
        foreach ($templates as $template) {
            if (!$template instanceof \SplFileInfo || $template->getExtension() !== 'html') {
                continue;
            }
            preg_match_all(
                "/form:\\s*'([^']+)',\\s*identifier:\\s*'([^']+)'/",
                (string)file_get_contents($template->getPathname()),
                $matches,
                PREG_SET_ORDER,
            );
            foreach ($matches as $match) {
                $fields[$match[1] . '.' . $match[2] . '.label'] = substr($template->getPathname(), strlen(self::RESOURCES));
            }
        }
        $this->assertNotSame([], $fields, 'No form field was found in the templates at all.');

        $missing = [];
        foreach ($fields as $key => $template) {
            if (!isset($units[$key])) {
                $missing[] = sprintf('%s (%s)', $key, $template);
            }
        }
        sort($missing);
        $this->assertSame([], $missing, sprintf('%s lacks the labels of these form fields.', $languageFile));
    }
}
