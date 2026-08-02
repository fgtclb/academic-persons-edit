<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Covers the image actions the profile detail view of the
 * `academicpersonsedit_profileediting` plugin offers, and the replace form they lead to.
 *
 * The point of ACE-304: `editImage` used to be rendered only while the profile had no
 * image, so the replacement handling of `addImageAction()` could not be reached from the
 * shipped templates at all. These tests assert the link set of both states, which is what
 * makes that path reachable.
 *
 * The image is seeded rather than uploaded, so this runs on both supported core versions -
 * see `AcademicPersonsEditProfileImageUploadTest` for why an upload cannot.
 */
final class AcademicPersonsEditProfileImageReplaceTest extends AbstractProfileEditingPluginTestCase
{
    /**
     * Returns the markup of every anchor that links an Extbase action, keyed by that action,
     * so both the link target and its label can be asserted together.
     *
     * @return array<string, string> action name => anchor markup
     */
    private function getActionAnchors(string $content): array
    {
        preg_match_all('@<a\b[^>]*href="([^"]*)"[^>]*>(.*?)</a>@s', $content, $matches, PREG_SET_ORDER);

        $anchors = [];
        foreach ($matches as $match) {
            $href = html_entity_decode($match[1]);
            if (preg_match('@\[action]=([^&]+)@', urldecode($href), $actionMatch) !== 1) {
                continue;
            }
            $anchors[$actionMatch[1]] = $match[0];
        }

        return $anchors;
    }

    #[Test]
    public function profileWithoutAnImageOffersAddingOneOnly(): void
    {
        $this->setUpTestCase();

        $anchors = $this->getActionAnchors($this->getProfileShowPage());

        $this->assertArrayHasKey('editImage', $anchors, 'The profile offers no way to add an image.');
        $this->assertStringContainsString('Add', $anchors['editImage']);
        // Nothing to remove yet.
        $this->assertArrayNotHasKey('removeImage', $anchors);
    }

    #[Test]
    public function profileWithAnImageOffersReplacingItNextToRemovingIt(): void
    {
        $this->setUpTestCase();
        $this->seedProfileImage();

        $anchors = $this->getActionAnchors($this->getProfileShowPage());

        // Before ACE-304 this key was absent: the two links were rendered as an either/or,
        // so a profile that had an image could only have it deleted.
        $this->assertArrayHasKey('editImage', $anchors, 'The profile offers no way to replace its image.');
        $this->assertStringContainsString('Replace', $anchors['editImage']);
        $this->assertStringNotContainsString('Add', $anchors['editImage']);
        $this->assertArrayHasKey('removeImage', $anchors, 'The profile offers no way to remove its image.');
    }

    #[Test]
    public function replaceFormShowsTheImageItIsAboutToReplace(): void
    {
        $this->setUpTestCase();
        $this->seedProfileImage();

        $replaceUrl = $this->extractActionLink($this->getProfileShowPage(), 'editImage');
        $content = $this->getPageAsFrontendUser($replaceUrl);

        // The form alone does not say which image is being replaced.
        $this->assertMatchesRegularExpression(
            '@<img\b[^>]+src="[^"]*profile-image[^"]*\.png"@',
            $content,
            'The replace form does not render the image it replaces.',
        );
        $this->assertMatchesRegularExpression('@<form\b[^>]+enctype="multipart/form-data"@', $content);
    }

    #[Test]
    public function addFormShowsNoImageForAProfileWithoutOne(): void
    {
        $this->setUpTestCase();

        $addUrl = $this->extractActionLink($this->getProfileShowPage(), 'editImage');
        $content = $this->getPageAsFrontendUser($addUrl);

        $this->assertStringNotContainsString('<picture>', $content);
        $this->assertMatchesRegularExpression('@<form\b[^>]+enctype="multipart/form-data"@', $content);
    }

    /**
     * A missing label renders as an empty string, which the anchor assertions above would
     * not notice, and the German file is maintained by hand.
     */
    #[Test]
    public function replaceLabelIsShippedInBothLanguages(): void
    {
        $languageService = $this->get(LanguageServiceFactory::class)->create('default');
        $this->assertSame(
            'Replace',
            $languageService->sL(
                'LLL:EXT:academic_persons_edit/Resources/Private/Language/locallang.xlf:actions.replace'
            ),
        );

        $file = __DIR__ . '/../../../Resources/Private/Language/de.locallang.xlf';
        $this->assertFileExists($file);
        $document = new \DOMDocument();
        $this->assertTrue($document->load($file));

        $targets = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $target = $unit->getElementsByTagName('target')->item(0);
            if ($target !== null) {
                $targets[(string)$unit->getAttribute('id')] = $target->textContent;
            }
        }
        $this->assertArrayHasKey('actions.replace', $targets, 'No German translation for "actions.replace".');
        $this->assertSame('Ersetzen', $targets['actions.replace']);
    }
}
