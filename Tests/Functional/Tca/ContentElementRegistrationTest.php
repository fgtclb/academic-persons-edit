<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Tca;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pins which content elements the extension registers.
 *
 * `academicpersonsedit_profileswitcher` was selectable long after the plugin behind it was
 * gone (ACE-306). `ExtensionManagementUtility::addPlugin()` writes three places, and all
 * three are asserted here — a leftover in any of them keeps the dead type reachable in one
 * form or another.
 */
final class ContentElementRegistrationTest extends AbstractAcademicPersonsEditTestCase
{
    private const REMOVED_CONTENT_TYPE = 'academicpersonsedit_profileswitcher';
    private const PROFILE_EDITING_CONTENT_TYPE = 'academicpersonsedit_profileediting';

    /**
     * @return list<string>
     */
    private function getContentTypeValues(): array
    {
        $values = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [] as $item) {
            $values[] = (string)($item['value'] ?? '');
        }

        return $values;
    }

    #[Test]
    public function profileEditingPluginIsSelectable(): void
    {
        $this->assertContains(self::PROFILE_EDITING_CONTENT_TYPE, $this->getContentTypeValues());
        $this->assertArrayHasKey(
            self::PROFILE_EDITING_CONTENT_TYPE,
            $GLOBALS['TCA']['tt_content']['types'],
        );
    }

    #[Test]
    public function profileSwitcherPluginIsNotSelectable(): void
    {
        $this->assertNotContains(self::REMOVED_CONTENT_TYPE, $this->getContentTypeValues());
    }

    #[Test]
    public function profileSwitcherPluginHasNoRecordType(): void
    {
        // Without this entry the page module renders a warning badge for a leftover record
        // instead of a header shaped form, which is what makes it visible to an editor.
        $this->assertArrayNotHasKey(
            self::REMOVED_CONTENT_TYPE,
            $GLOBALS['TCA']['tt_content']['types'],
        );
    }

    #[Test]
    public function profileSwitcherPluginHasNoTypeIcon(): void
    {
        $this->assertArrayNotHasKey(
            self::REMOVED_CONTENT_TYPE,
            $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'] ?? [],
        );
    }
}
