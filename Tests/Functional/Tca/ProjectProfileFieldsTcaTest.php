<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Tca;

use FGTCLB\AcademicPersons\EventListener\ApplySettingsToTca;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Package\Cache\PackageDependentCacheIdentifier;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Schema\SchemaCollection;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * The project fields of the fixture extension `test_project_profile_fields` in the
 * backend: the required name prefix `tx_test_prefix` and the checkbox
 * `tx_test_guest`, two columns the extension adds to the profile table.
 */
final class ProjectProfileFieldsTcaTest extends AbstractAcademicPersonsEditTestCase
{
    private const TABLE = 'tx_academicpersons_domain_model_profile';

    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'tests/test-project-profile-fields';
        parent::setUp();
    }

    #[Test]
    public function theBackendFormRequiresARequiredProjectField(): void
    {
        $columns = $GLOBALS['TCA'][self::TABLE]['columns'];

        $this->assertSame('input', $columns['tx_test_prefix']['config']['type']);
        $this->assertTrue($columns['tx_test_prefix']['config']['required'] ?? null);
        $this->assertFalse($columns['tx_test_guest']['config']['required'] ?? null);
        $this->assertSame('check', $columns['tx_test_guest']['config']['type']);
    }

    /**
     * The backend form is built from the cached TCA schema, not from the global array.
     */
    #[Test]
    public function theCachedSchemaRequiresARequiredProjectField(): void
    {
        $cacheIdentifier = (new PackageDependentCacheIdentifier($this->get(PackageManager::class)))
            ->withPrefix('TcaSchema')
            ->toString();
        $schemata = $this->get('cache.core')->require($cacheIdentifier);
        $this->assertTrue(is_array($schemata) || $schemata instanceof SchemaCollection);

        $this->assertTrue($schemata[self::TABLE]->getField('tx_test_prefix')->isRequired());
        $this->assertTrue($this->get(TcaSchemaFactory::class)->get(self::TABLE)->getField('tx_test_prefix')->isRequired());
    }

    /**
     * A column the TCA does not have takes neither the backend nor the install tool
     * down: the TCA is compiled, without the column, and the notice names it.
     */
    #[Test]
    public function aMissingProjectColumnRaisesANoticeNamingIt(): void
    {
        $tca = $GLOBALS['TCA'];
        unset($tca[self::TABLE]['columns']['tx_test_prefix']);
        // Taken from the TCA the listener already merged, so the flag is set again here.
        unset($tca[self::TABLE]['columns']['tx_test_guest']['config']['readOnly']);
        $event = new AfterTcaCompilationEvent($tca);

        $notices = $this->collectDeprecationNotices(fn() => $this->get(ApplySettingsToTca::class)($event));

        $this->assertSame([
            'The project field "profile.namePrefix" of the persons settings cannot use the column "tx_test_prefix"'
            . ' of the table "tx_academicpersons_domain_model_profile": the TCA of the table has no such column.'
            . ' The settings of the field are not applied to the TCA, and the frontend profile editor fails until'
            . ' the settings or the TCA are corrected.',
        ], $notices);

        $this->assertArrayNotHasKey('tx_test_prefix', $event->getTca()[self::TABLE]['columns']);
        $this->assertFalse($event->getTca()[self::TABLE]['columns']['tx_test_guest']['config']['readOnly']);
    }

    /**
     * A system column gets nothing of the settings of a project field that names it:
     * a required project field on the edit lock would make the lock of every profile
     * a required value.
     */
    #[Test]
    public function aSystemColumnRaisesANoticeAndKeepsItsConfiguration(): void
    {
        $tca = $GLOBALS['TCA'];
        $tca[self::TABLE]['columns']['tx_test_prefix']['config'] = ['type' => 'input'];
        $tca[self::TABLE]['ctrl']['editlock'] = 'tx_test_prefix';
        $event = new AfterTcaCompilationEvent($tca);

        $notices = $this->collectDeprecationNotices(fn() => $this->get(ApplySettingsToTca::class)($event));

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('"profile.namePrefix"', $notices[0]);
        $this->assertStringContainsString('the column "tx_test_prefix"', $notices[0]);
        $this->assertStringContainsString(': it is a system column of the table.', $notices[0]);

        $this->assertSame(['type' => 'input'], $event->getTca()[self::TABLE]['columns']['tx_test_prefix']['config']);
    }

    /**
     * The `E_USER_DEPRECATED` notices the callback raises, taken from the test run so
     * that the test asserts them instead of the run failing on them. Every other error
     * is handed to the handler registered before, which is the one of PHPUnit. A
     * handler registered for one level only would send the others to the handler of
     * PHP instead, and a warning of the listener would no longer fail the run.
     *
     * @return list<string>
     */
    private function collectDeprecationNotices(\Closure $callback): array
    {
        $notices = [];
        $previousHandler = null;
        $previousHandler = set_error_handler(
            static function (int $level, string $message, string $file = '', int $line = 0) use (&$notices, &$previousHandler): bool {
                if ($level === E_USER_DEPRECATED) {
                    $notices[] = $message;
                    return true;
                }
                return is_callable($previousHandler) && (bool)$previousHandler($level, $message, $file, $line);
            },
        );
        try {
            $callback();
        } finally {
            restore_error_handler();
        }
        return $notices;
    }
}
