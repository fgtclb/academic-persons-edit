<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Imaging;

use FGTCLB\AcademicBase\Imaging\FrontendIconRegistry;
use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendIconsAssertionTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\AbstractSvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * The action icons of the profile editor are frontend icons: registered in
 * `Configuration/FrontendIcons.php` and rendered by `ab:icon` of academic_base. The
 * backend never shows them, so the icon registry of the backend must not know them,
 * or a site that replaces one in `Configuration/Icons.php` sees no effect and no error.
 * The icon of the content element is the opposite case, a backend icon only.
 *
 * `FrontendIconFactory::getIcon()` never fails on an unknown identifier: it answers with
 * the `default-not-found` placeholder, so a typo in a registration, a renamed file or a
 * deleted one reaches a page as a small icon and nothing else. The list below is the
 * registered API and is spelled out here rather than read back out of
 * `Configuration/FrontendIcons.php`, so a rename has to be made twice instead of silently
 * agreeing with itself.
 */
final class ProfileEditingIconsTest extends AbstractAcademicPersonsEditTestCase
{
    use FrontendIconsAssertionTrait;

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
    public function actionIconIsAFrontendIconWithTheColourSchemeAwareProvider(string $identifier): void
    {
        $this->assertFrontendIconIsRegisteredWithProvider($identifier, CurrentColorSvgIconProvider::class);
    }

    /**
     * The default markup is the inlined file, not an `<img>`. That is the whole reason the
     * set is registered with {@see CurrentColorSvgIconProvider}: an `<img>` keeps the colours
     * of its file, an inlined `<svg>` drawn in `currentColor` takes the colour of the button
     * it sits in.
     */
    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function actionIconIsInlinedInBothMarkups(string $identifier): void
    {
        $icon = $this->getFrontendIcon($identifier);
        $markup = $icon->getMarkup();

        $this->assertStringStartsWith('<svg', $markup);
        $this->assertStringNotContainsString('<img', $markup);
        $this->assertSame($markup, $icon->getAlternativeMarkup(AbstractSvgIconProvider::MARKUP_IDENTIFIER_INLINE));
        $this->assertFrontendIconMarkupFollowsTheTextColour($identifier);
    }

    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function renderedActionIconCarriesItsIdentifier(string $identifier): void
    {
        $this->assertRenderedFrontendIconCarriesItsIdentifier($identifier);
    }

    /**
     * Asked through the icon API of the backend, the way `core:icon` asks, the identifier
     * is unknown and the answer is TYPO3's placeholder.
     */
    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function actionIconIsNoBackendIcon(string $identifier): void
    {
        $this->assertFrontendIconIsNotABackendIcon($identifier);
        $this->assertSame(
            'default-not-found',
            $this->get(IconFactory::class)->getIcon($identifier, IconSize::SMALL)->getIdentifier(),
        );
    }

    #[Test]
    public function contentElementIconIsABackendIconOnly(): void
    {
        $this->assertTrue($this->get(IconRegistry::class)->isRegistered('persons_edit_icon'));
        $this->assertFalse($this->get(FrontendIconRegistry::class)->isRegistered('persons_edit_icon'));
    }
}
