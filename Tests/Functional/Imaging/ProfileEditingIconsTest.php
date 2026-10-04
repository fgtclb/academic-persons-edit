<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Imaging;

use FGTCLB\AcademicBase\Imaging\FrontendIconRegistry;
use FGTCLB\AcademicBase\Imaging\IconProvider\CurrentColorSvgIconProvider;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\ColourSchemeAwareIconsTrait;
use FGTCLB\TestingHelper\FunctionalTestCase\FrontendIconsAssertionTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconProvider\AbstractSvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * The action and state icons of the profile editor are the shared frontend icons of
 * academic_base: registered in its `Configuration/FrontendIcons.php` and rendered by
 * `ab:icon`. This extension registers none of its own. The backend never shows them,
 * so the icon registry of the backend must not know them, or a site that replaces one
 * in `Configuration/Icons.php` sees no effect and no error. The icon of the content
 * element is the opposite case, a backend icon only.
 *
 * `FrontendIconFactory::getIcon()` never fails on an unknown identifier: it answers with
 * the `default-not-found` placeholder, so a typo in a registration, a renamed file or a
 * deleted one reaches a page as a small icon and nothing else. The lists below are
 * spelled out here rather than read back out of a configuration file, so a rename has to
 * be made twice instead of silently agreeing with itself.
 */
final class ProfileEditingIconsTest extends AbstractAcademicPersonsEditTestCase
{
    use ColourSchemeAwareIconsTrait;
    use FrontendIconsAssertionTrait;

    private const PLUGIN_ICON_IDENTIFIER = 'tx-academicpersonsedit-plugin-profile-editing';
    private const PROFILE_EDITING_CONTENT_TYPE = 'academicpersonsedit_profileediting';

    /**
     * The identifiers the templates of the editor and the profile overview render.
     *
     * @return \Generator<string, array{0: string}>
     */
    public static function actionIconIdentifiers(): \Generator
    {
        $identifiers = [
            'tx-academicbase-action-add',
            'tx-academicbase-action-back',
            'tx-academicbase-action-clear',
            'tx-academicbase-action-delete',
            'tx-academicbase-action-drag',
            'tx-academicbase-action-edit',
            'tx-academicbase-action-help',
            'tx-academicbase-action-move-down',
            'tx-academicbase-action-move-up',
            'tx-academicbase-action-save',
            'tx-academicbase-action-undo',
            'tx-academicbase-action-upload-image',
            'tx-academicbase-action-view',
            'tx-academicbase-action-view-close',
            'tx-academicbase-state-visible',
            'tx-academicbase-state-hidden',
        ];
        foreach ($identifiers as $identifier) {
            yield $identifier => [$identifier];
        }
    }

    /**
     * The identifiers of the editor up to the shared icon set. They are renamed without an
     * alias, so neither registry may still answer for one of them.
     *
     * @return \Generator<string, array{0: string}>
     */
    public static function removedIconIdentifiers(): \Generator
    {
        $identifiers = [
            'persons_edit_icon',
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

    /**
     * The file comes from academic_base: the editor draws the shared set, so a project
     * that replaces one of these identifiers replaces it in every academic extension.
     */
    #[Test]
    #[DataProvider('actionIconIdentifiers')]
    public function actionIconIsASharedFrontendIconWithTheColourSchemeAwareProvider(string $identifier): void
    {
        $this->assertFrontendIconIsRegisteredWithProvider($identifier, CurrentColorSvgIconProvider::class);
        $this->assertStringStartsWith(
            'EXT:academic_base/Resources/Public/Icons/',
            (string)($this->get(FrontendIconRegistry::class)->getIconConfiguration($identifier)['options']['source'] ?? ''),
        );
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
    #[DataProvider('removedIconIdentifiers')]
    public function removedIdentifierIsRegisteredNowhere(string $identifier): void
    {
        $this->assertFalse($this->get(FrontendIconRegistry::class)->isRegistered($identifier));
        $this->assertFalse($this->get(IconRegistry::class)->isRegistered($identifier));
    }

    /**
     * The icon of the content element reaches the page module and the new content element
     * wizard, and follows the backend colour scheme in both.
     */
    #[Test]
    public function contentElementIconIsAColourSchemeAwareBackendIcon(): void
    {
        $this->assertIconIsRegisteredWithCurrentColorProvider(self::PLUGIN_ICON_IDENTIFIER);
        $this->assertIconIsInlinedInBothMarkups(self::PLUGIN_ICON_IDENTIFIER);
        $this->assertIconMarkupFollowsTheTextColour(self::PLUGIN_ICON_IDENTIFIER);
        $this->assertRenderedIconCarriesItsIdentifier(self::PLUGIN_ICON_IDENTIFIER);
        $this->assertSame(
            'EXT:academic_persons_edit/Resources/Public/Icons/plugin/profile-editing.svg',
            $this->get(IconRegistry::class)->getIconConfigurationByIdentifier(self::PLUGIN_ICON_IDENTIFIER)['options']['source'] ?? null,
        );
    }

    #[Test]
    public function contentElementIconIsABackendIconOnly(): void
    {
        $this->assertTrue($this->get(IconRegistry::class)->isRegistered(self::PLUGIN_ICON_IDENTIFIER));
        $this->assertFalse($this->get(FrontendIconRegistry::class)->isRegistered(self::PLUGIN_ICON_IDENTIFIER));
    }

    /**
     * `TcaManipulator::addContentElementPlugin()` writes the item icon verbatim into
     * `typeicon_classes`, which is what the page module renders for a record of the type.
     */
    #[Test]
    public function contentElementTypeUsesThePluginIcon(): void
    {
        $this->assertSame(
            self::PLUGIN_ICON_IDENTIFIER,
            $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][self::PROFILE_EDITING_CONTENT_TYPE] ?? null,
        );
        $itemIcons = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [] as $item) {
            if (($item['value'] ?? null) === self::PROFILE_EDITING_CONTENT_TYPE) {
                $itemIcons[] = $item['icon'] ?? null;
            }
        }
        $this->assertSame([self::PLUGIN_ICON_IDENTIFIER], $itemIcons);
    }

    /**
     * Nothing but the content element icon is registered by this extension: the action and
     * state icons are the shared ones, and an identifier of its own that nothing renders
     * would be dead API.
     */
    #[Test]
    public function thisExtensionRegistersOnlyThePluginIcon(): void
    {
        $registeredIcons = require __DIR__ . '/../../../Configuration/Icons.php';
        $this->assertIsArray($registeredIcons);
        $this->assertSame([self::PLUGIN_ICON_IDENTIFIER], array_keys($registeredIcons));
        $this->assertFileDoesNotExist(__DIR__ . '/../../../Configuration/FrontendIcons.php');
    }
}
