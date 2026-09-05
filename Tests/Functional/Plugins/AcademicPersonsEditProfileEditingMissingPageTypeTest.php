<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * The editor writes every change to `&type=1733735`, and that page type is new
 * in 3.0: it is delivered by the site set
 * `fgtclb/academic-persons-edit-profile-editing` or by the static template of
 * the extension. A site package that copied the extension's TypoScript instead
 * of including it renders the editor and gets the site's error page where the
 * browser expects JSON - a failure that is entirely client side and names
 * nothing.
 *
 * The fixture setup unsets the `PAGE` object again, which is exactly that
 * installation.
 */
final class AcademicPersonsEditProfileEditingMissingPageTypeTest extends AbstractFrontendProfilePluginTestCase
{
    private const MISSING_PAGE_TYPE_SETUP =
        'EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/WithoutAjaxPageType.typoscript';

    #[Test]
    public function theEditorSaysThatItCannotSaveWithoutItsPageType(): void
    {
        $this->setUpProfileEditingTestCase([self::MISSING_PAGE_TYPE_SETUP]);

        $content = $this->withoutProfileEditingPrototypes($this->renderProfileEditingPage());

        $this->assertStringContainsString('data-pe-missing-ajax-page-type', $content);
        $this->assertStringContainsString(
            'the website is missing the page type the profile editor writes through',
            $content,
        );
    }

    /**
     * The negative control: the same page with the shipped TypoScript says
     * nothing, so the message is a report of the defect and not a permanent
     * banner.
     */
    #[Test]
    public function theShippedTypoScriptRendersNoSuchMessage(): void
    {
        $this->setUpProfileEditingTestCase();

        $content = $this->withoutProfileEditingPrototypes($this->renderProfileEditingPage());

        $this->assertStringNotContainsString('data-pe-missing-ajax-page-type', $content);
    }
}
