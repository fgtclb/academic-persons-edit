<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Imaging;

use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use FGTCLB\TestingHelper\FunctionalTestCase\ColourSchemeAwareIconsTrait;
use FGTCLB\TestingHelper\FunctionalTestCase\IconFilesAssertionTrait;
use PHPUnit\Framework\Attributes\Test;

/**
 * The icon rules of docs/architecture/icons.md, checked for every icon of this extension:
 * its content element icon. The other icon tests spell out the identifiers that exist
 * today. These checks read the registration files, the registries, the TCA and the icon
 * directory instead, so an icon added later is held to the same rules without a test naming
 * it.
 */
final class IconRulesTest extends AbstractAcademicPersonsEditTestCase
{
    use ColourSchemeAwareIconsTrait;
    use IconFilesAssertionTrait;

    #[Test]
    public function backendIdentifiersFollowTheNamingScheme(): void
    {
        $this->assertIconIdentifiersFollowTheNamingScheme('academic_persons_edit', ['plugin']);
    }

    #[Test]
    public function everyBackendIconOfTheExtensionIsInTheHouseFormat(): void
    {
        $this->assertEveryIconOfTheExtensionIsInTheHouseFormat('academic_persons_edit');
    }

    /**
     * Every type of a table of this extension, and every content element named here, names
     * an icon of this extension in the group of its kind, drawn for the colour scheme. A
     * table added later is covered as it is. A content element added later has to be added
     * to the list.
     */
    #[Test]
    public function everyTypeOfTheExtensionNamesAnIconOfItsOwn(): void
    {
        $this->assertEveryTypeOfTheExtensionNamesAnIconOfItsOwn(
            'academic_persons_edit',
            contentTypes: [
                'academicpersonsedit_profileediting',
            ],
        );
    }

    /**
     * The walk by file. It had nothing to check here while content element icons were
     * exempt from it, because the only type icon of this extension is one.
     */
    #[Test]
    public function everyRecordTypeIconOfThisExtensionIsColourSchemeAware(): void
    {
        $this->assertEveryRecordTypeIconIsColourSchemeAware('academic_persons_edit');
    }

    #[Test]
    public function everyIconFileIsTheSourceOfARegisteredIcon(): void
    {
        $this->assertEveryIconFileIsRegistered('academic_persons_edit');
    }

    #[Test]
    public function everyIconFileIsAttributedInTheLicenceNotice(): void
    {
        $this->assertEveryIconFileIsAttributedInTheNotice('academic_persons_edit');
    }
}
