<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Imaging;

use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\AbstractSvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * Renders the action icons of the profile editing frontend through the `IconFactory` of the
 * container, the way `<core:icon>` does.
 *
 * `IconFactory::getIcon()` never fails on an unknown identifier: it answers with the
 * `default-not-found` placeholder, so a typo in a registration, a renamed file or a deleted
 * one reaches a page as a small red icon and nothing else. The list below is the registered
 * API and is spelled out here rather than read back out of `Configuration/Icons.php`, so a
 * rename has to be made twice - in the registration and here - instead of silently agreeing
 * with itself.
 */
final class ProfileEditingIconsTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function actionIconIdentifiers(): \Generator
    {
        $identifiers = [
            'academic-persons-edit-add',
            'academic-persons-edit-back',
            'academic-persons-edit-clear',
            'academic-persons-edit-delete',
            'academic-persons-edit-edit',
            'academic-persons-edit-help',
            'academic-persons-edit-move-down',
            'academic-persons-edit-move-up',
            'academic-persons-edit-save',
            'academic-persons-edit-sort-handle',
            'academic-persons-edit-undo',
            'academic-persons-edit-upload-image',
            'academic-persons-edit-view',
            'academic-persons-edit-view-close',
            'academic-persons-edit-visible',
            'academic-persons-edit-hidden',
        ];
        foreach ($identifiers as $identifier) {
            yield $identifier => [$identifier];
        }
    }

    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function actionIconResolves(string $identifier): void
    {
        $this->assertTrue($this->get(IconRegistry::class)->isRegistered($identifier));
        $this->assertSame($identifier, $this->getIcon($identifier)->getIdentifier());
    }

    /**
     * The default markup is the inlined file, not an `<img>`. That is the whole reason the
     * set is registered with {@see CurrentColorSvgIconProvider}: an `<img>` keeps the colours
     * of its file, an inlined `<svg>` drawn in `currentColor` takes the colour of the button
     * it sits in - in the frontend as much as in a dark backend colour scheme.
     */
    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function actionIconIsInlinedInBothMarkups(string $identifier): void
    {
        $icon = $this->getIcon($identifier);
        $markup = $icon->getMarkup();

        $this->assertStringStartsWith('<svg', $markup);
        $this->assertStringNotContainsString('<img', $markup);
        $this->assertStringContainsString('fill="currentColor"', $markup);
        $this->assertSame($markup, $icon->getAlternativeMarkup(AbstractSvgIconProvider::MARKUP_IDENTIFIER_INLINE));
    }

    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function renderedActionIconCarriesItsIdentifier(string $identifier): void
    {
        $rendered = $this->getIcon($identifier)->render();

        $this->assertStringContainsString('data-identifier="' . $identifier . '"', $rendered);
        $this->assertStringNotContainsString('default-not-found', $rendered);
        $this->assertStringContainsString('<svg', $rendered);
    }

    private function getIcon(string $identifier): Icon
    {
        return $this->get(IconFactory::class)->getIcon($identifier, IconSize::SMALL);
    }
}
