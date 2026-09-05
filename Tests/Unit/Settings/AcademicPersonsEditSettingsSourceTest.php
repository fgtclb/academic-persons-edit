<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The profile editor is configured by the settings file of `EXT:academic_persons`
 * alone. An editor-local second file would be loaded by nothing and would silently
 * disagree with the graph the editing endpoints validate against.
 *
 * What the file itself has to contain, and what the factory builds from it, is
 * covered by `EXT:academic_persons` - the package that owns both.
 */
final class AcademicPersonsEditSettingsSourceTest extends UnitTestCase
{
    #[Test]
    public function editExtensionShipsNoSettingsFileOfItsOwn(): void
    {
        $this->assertFileDoesNotExist(__DIR__ . '/../../../Configuration/AcademicsPersonsEdit/Settings.yaml');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../../../Configuration/AcademicsPersonsEdit');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../../../Configuration/AcademicPersonsEdit');
        $this->assertFileExists(
            __DIR__ . '/../../../../academic-persons/Configuration/AcademicPersons/Settings.yaml',
        );
    }
}
