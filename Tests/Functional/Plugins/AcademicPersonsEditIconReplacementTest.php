<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * A site package replaces an action icon the profile editor renders in its own
 * `Configuration/FrontendIcons.php`, and every control shows its drawing without a
 * template override, the controls the editor builds in the browser from the templates
 * of the page included. A replacement left in `Configuration/Icons.php` does not reach
 * the editor, which shows the shipped drawing.
 *
 * The editor renders the shared icons of academic_base, so the identifiers replaced are
 * those of academic_base. The fixture `tests/editor-icon-replacement` is that site
 * package: it replaces `tx-academicbase-action-edit` in the file of the frontend and
 * `tx-academicbase-action-delete` in the file of the backend, both with a rectangle. A
 * TYPO3 v14 test instance orders the packages by their keys, so the first test asserts
 * that the fixture loads after academic_base and academic_persons_edit.
 */
final class AcademicPersonsEditIconReplacementTest extends AbstractFrontendProfilePluginTestCase
{
    /**
     * The rectangle of the fixture, as the serialisers of both core versions and the DOM
     * write it.
     */
    private const REPLACED_DRAWING = 'x="2" y="5" width="12" height="6"';

    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/editor-icon-replacement');
        parent::setUp();
    }

    #[Test]
    public function theSitePackageLoadsAfterTheExtension(): void
    {
        $packageKeys = array_keys($this->get(PackageManager::class)->getActivePackages());

        foreach (['academic_base', 'academic_persons_edit'] as $extensionKey) {
            $this->assertGreaterThan(
                array_search($extensionKey, $packageKeys, true),
                array_search('test_editor_icon_replacement', $packageKeys, true),
            );
        }
    }

    /**
     * The contact row and the edit button of a field without a value are templates the
     * editor clones in the browser. Their icons are rendered with the page, so the
     * replacement reaches every control built from them.
     */
    #[Test]
    public function aFrontendIconOfTheSitePackageReplacesTheShippedOneInEveryControl(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $edits = $this->renderedIconMarkups($content, 'tx-academicbase-action-edit');
        $this->assertNotSame([], $edits);
        foreach ($edits as $markup) {
            $this->assertStringContainsString(self::REPLACED_DRAWING, $markup);
        }
        foreach (['//template[@data-pe-proto="contact-row"]', '//template[@data-pe-new-button-template]'] as $query) {
            $template = $this->templateMarkup($content, $query);
            $this->assertStringContainsString('data-identifier="tx-academicbase-action-edit"', $template);
            $this->assertStringContainsString(self::REPLACED_DRAWING, $template);
        }
    }

    #[Test]
    public function aReplacementInTheBackendFileDoesNotReachTheEditor(): void
    {
        $this->setUpProfileEditingTestCase();
        $content = $this->renderProfileEditingPage();

        $deletes = $this->renderedIconMarkups($content, 'tx-academicbase-action-delete');
        $this->assertNotSame([], $deletes);
        foreach ($deletes as $markup) {
            $this->assertStringNotContainsString(self::REPLACED_DRAWING, $markup);
            $this->assertStringContainsString('d="M232.7 69.9', $markup);
        }
        $this->assertStringContainsString(
            'data-identifier="tx-academicbase-action-delete"',
            $this->templateMarkup($content, '//template[@data-pe-proto="contact-row"]'),
        );
    }

    /**
     * The inner markup of every rendered icon with the identifier, in page order,
     * inside templates or not.
     *
     * @return list<string>
     */
    private function renderedIconMarkups(string $content, string $identifier): array
    {
        preg_match_all(
            '@data-identifier="' . preg_quote($identifier, '@') . '" aria-hidden="true">\s*<span class="icon-markup">(.*?)</span>@s',
            $content,
            $matches,
        );

        return $matches[1];
    }

    private function templateMarkup(string $content, string $query): string
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING));
        $templates = (new \DOMXPath($document))->query($query);
        $this->assertNotFalse($templates);
        $this->assertCount(1, $templates);
        $template = $templates->item(0);
        $this->assertInstanceOf(\DOMElement::class, $template);
        $markup = '';
        foreach ($template->childNodes as $child) {
            $markup .= $document->saveHTML($child);
        }

        return $markup;
    }
}
