<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * The profile editor names a profile by its title, first, middle and last name, and
 * leaves out the parts a profile does not have without a stray space (ACE-853).
 *
 * The fixture profile has neither a title nor a middle name, which is the case that
 * rendered " Max  Müllermann" in the list and in every header of the editor.
 */
final class AcademicPersonsEditProfileNameTest extends AbstractProfileEditingPluginTestCase
{
    #[Test]
    public function profileListNamesTheProfileWithSingleSpaces(): void
    {
        $this->setUpTestCase();

        $this->assertStringContainsString(
            '<td>Max Müllermann</td>',
            $this->getPageAsFrontendUser('https://www.acme.com/home'),
        );
    }

    #[Test]
    public function profileHeaderNamesTheProfileWithSingleSpaces(): void
    {
        $this->setUpTestCase();

        $content = $this->getProfileShowPage();

        $this->assertMatchesRegularExpression('#>\s*Max Müllermann\s*<#u', $content);
        $this->assertStringNotContainsString('  Müllermann', $content);
    }
}
