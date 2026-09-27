<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

use PHPUnit\Framework\Attributes\Test;

/**
 * A name in the managed fields map that matches no field is not skipped: the
 * editor fails with the exception naming it, as the backend person record
 * forms do, instead of leaving the misspelled field editable without a word.
 * The fixture extension `test_managed_fields_mistake` of `academic_persons`
 * names `positon` for the contracts.
 */
final class AcademicPersonsEditManagedFieldsMistakeTest extends AbstractFrontendProfilePluginTestCase
{
    protected function setUp(): void
    {
        $this->addTestExtensionsToLoad('tests/test-managed-fields-mistake');
        parent::setUp();
    }

    #[Test]
    public function aMistakeInTheMapFailsTheEditor(): void
    {
        // The profile has no import identifier: the map is checked before
        // any record is, so a mistake shows on every profile.
        $this->setUpProfileEditingTestCase();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1790536034);
        $this->expectExceptionMessage('`managedFields.contracts` names `positon`');
        $this->renderProfileEditingPage();
    }
}
