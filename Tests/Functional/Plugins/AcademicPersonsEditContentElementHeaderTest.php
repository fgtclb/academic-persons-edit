<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use FGTCLB\TestingHelper\FunctionalTestCase\ContentElementHeaderAssertionTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The header of the `academicpersonsedit_profileediting` content element renders once on
 * every view of the plugin: by default the content element layout renders it and the plugin
 * does not. A site whose layout renders no header switches `renderContentElementHeader` on,
 * and the `ProfileEdit` layout of the plugin then renders it itself.
 *
 * The profile list and the view of the profile stand for all views, which share that
 * layout.
 */
final class AcademicPersonsEditContentElementHeaderTest extends AbstractProfileEditingPluginTestCase
{
    use ContentElementHeaderAssertionTrait;

    private const HEADER = 'My profile';
    private const SUBHEADER = 'Keep your details up to date';
    private const PLUGIN_WRAPPER = '//div[contains(concat(" ", normalize-space(@class), " "), " academic-persons-edit ")]';

    /**
     * The header layouts "Default", 2 and "Hidden" on the profile list and on the view of the
     * profile, with the number of times the header and the subheader have to render.
     * "Default" is the layout the header partial resolves through a setting, and the one a
     * plugin rendering it without that setting leaves an empty `<header>` for.
     *
     * @return array<string, array{int, int, bool}>
     */
    public static function headerLayoutsAndViews(): array
    {
        return [
            'header layout "Default" on the profile list' => [0, 1, false],
            'header layout 2 on the profile list' => [2, 1, false],
            'header layout "Hidden" on the profile list' => [100, 0, false],
            'header layout "Default" on the view of the profile' => [0, 1, true],
            'header layout 2 on the view of the profile' => [2, 1, true],
            'header layout "Hidden" on the view of the profile' => [100, 0, true],
        ];
    }

    #[Test]
    #[DataProvider('headerLayoutsAndViews')]
    public function profileEditingPluginLeavesTheContentElementHeaderToTheLayout(int $headerLayout, int $expectedHeadings, bool $profileView): void
    {
        $this->setUpTestCase();
        $this->setContentElementHeader($headerLayout);

        $content = $this->renderView($profileView);
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::HEADER));
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::SUBHEADER));
        $this->assertSame(0, $this->countHeaderElements($content, self::PLUGIN_WRAPPER));
    }

    #[Test]
    #[DataProvider('headerLayoutsAndViews')]
    public function profileEditingPluginRendersTheContentElementHeaderWhenSwitchedOn(int $headerLayout, int $expectedHeadings, bool $profileView): void
    {
        $this->setUpTestCase(
            ['EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Setup/LayoutWithoutHeader.typoscript'],
            ['EXT:academic_persons_edit/Tests/Functional/Plugins/Fixtures/TypoScript/Constants/RenderContentElementHeader.typoscript'],
        );
        $this->setContentElementHeader($headerLayout);

        $content = $this->renderView($profileView);
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::HEADER));
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::HEADER, self::PLUGIN_WRAPPER));
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::SUBHEADER));
        $this->assertSame($expectedHeadings, $this->countHeadingsReading($content, self::SUBHEADER, self::PLUGIN_WRAPPER));
    }

    private function setContentElementHeader(int $headerLayout): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->update(
                'tt_content',
                ['header' => self::HEADER, 'subheader' => self::SUBHEADER, 'header_layout' => $headerLayout],
                ['uid' => 1],
            );
    }

    private function renderView(bool $profileView): string
    {
        return $profileView
            ? $this->getProfileShowPage()
            : $this->getPageAsFrontendUser('https://www.acme.com/home');
    }
}
