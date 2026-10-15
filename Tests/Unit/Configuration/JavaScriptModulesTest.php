<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Configuration;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The import map of this extension resolves every CKEditor 5 bundle the rich
 * text editor loads on the installed TYPO3 core.
 *
 * "Configuration/JavaScriptModules.php" maps the bundles of EXT:rte_ckeditor
 * one by one, and which bundles that has to be is decided by the CKEditor build
 * the core ships, not by this extension. TYPO3 13.4.36 and 14.3.8 updated that
 * build, "ckeditor5-link" started to import "ckeditor5-image" and that one
 * "ckeditor5-upload". Neither was mapped, the browser refused the module graph
 * with "bare specifier was not remapped" and the profile editor never started,
 * while every other gate stayed green (ACE-911).
 *
 * The test derives the closure the way the browser does: it starts from the
 * "@ckeditor/*" specifiers the compiled modules of this extension import and
 * follows the imports through the installed bundles. The compiled modules are
 * the starting point rather than the TypeScript sources because they are what
 * the browser loads, type only imports are already gone from them, and
 * "checkJsBuildClean" keeps them in step with the sources.
 *
 * Only the direction that breaks the editor is asserted. The map is the union
 * over every supported patch level, so it legitimately carries bundles the
 * installed core does not import, and an equality check would fail on the
 * older patch levels. What every mapped bundle has to be instead is a file the
 * installed core ships, see the second test.
 */
final class JavaScriptModulesTest extends UnitTestCase
{
    private const CKEDITOR_PACKAGE = 'typo3/cms-rte-ckeditor';

    private const CKEDITOR_PREFIX = '@ckeditor/';

    private const TRANSLATIONS_PREFIX = '@typo3/ckeditor5/translations/';

    /**
     * Covers a static import or export, a side effect import and a dynamic
     * import, each with a specifier in single or double quotes. The lookbehind
     * keeps a property name or a string that merely reads "import" or "from"
     * out, which the minified bundles contain, for example a menu group called
     * "import". The specifier is restricted to the characters a module
     * specifier of these builds consists of, so a user interface string that
     * ends in "from" cannot run on into the next string literal.
     *
     * A dynamic import with a template literal is not covered. The one this
     * extension has loads a translation through its own prefix entry, its
     * specifier is not static, and no CKEditor bundle uses one.
     */
    private const IMPORT_PATTERN = '/(?<![\w$."\'])(?:from\s*|import\s*\(?\s*)(["\'])([\w@.\/-]+)\1/';

    #[Test]
    public function everyCkeditorBundleTheRichTextEditorLoadsIsMapped(): void
    {
        $imports = $this->loadImports();
        $roots = $this->collectCkeditorSpecifiersOfShippedModules();

        $this->assertNotSame([], $roots, 'No compiled module of the extension imports a "@ckeditor/*" specifier.');

        $closure = [];
        $unmapped = [];
        $missingFiles = [];
        $queue = $roots;
        while ($queue !== []) {
            $specifier = array_shift($queue);
            if (isset($closure[$specifier])) {
                continue;
            }
            $closure[$specifier] = true;

            $mapped = $this->resolveSpecifier($specifier, $imports);
            if ($mapped === null) {
                $unmapped[] = $specifier;
                // Keep following the bundle where EXT:rte_ckeditor ships it, so
                // the failure names every missing bundle and not only the first.
                $file = $this->conventionalBundleFile($specifier);
            } else {
                $file = $this->resolveExtensionPath($mapped);
                if ($file === null || !is_file($file)) {
                    $missingFiles[] = sprintf('%s => %s', $specifier, $mapped);
                    $file = null;
                }
            }
            if ($file === null || !is_file($file)) {
                continue;
            }
            foreach ($this->importedSpecifiers($file) as $imported) {
                if (str_starts_with($imported, './') || str_starts_with($imported, '../') || str_starts_with($imported, '/')) {
                    $this->fail(sprintf(
                        'Bundle "%s" imports "%s" by path, which the import map cannot answer and this test does not follow.',
                        $file,
                        $imported,
                    ));
                }
                $queue[] = $imported;
            }
        }

        sort($unmapped);
        $this->assertSame(
            [],
            $unmapped,
            sprintf(
                'The CKEditor bundles of %s %s import specifiers "Configuration/JavaScriptModules.php" does not map: %s',
                self::CKEDITOR_PACKAGE,
                InstalledVersions::getPrettyVersion(self::CKEDITOR_PACKAGE),
                implode(', ', $unmapped),
            ),
        );
        $this->assertSame([], $missingFiles, 'Mapped to a file that does not exist: ' . implode(', ', $missingFiles));
    }

    /**
     * An entry pointing at a file the installed core does not ship is either a
     * typo or a bundle a CKEditor update removed. The union over the supported
     * patch levels only ever holds bundles every one of them ships, so this
     * holds on the oldest and on the newest at the same time. The prefix entry
     * of the editor translations is checked the same way, it has to name a
     * directory of the installed core.
     */
    #[Test]
    public function everyMappedCkeditorPathExistsInTheInstalledCore(): void
    {
        $imports = $this->loadImports();
        $this->assertArrayHasKey(self::TRANSLATIONS_PREFIX, $imports);
        $translations = $this->resolveExtensionPath($imports[self::TRANSLATIONS_PREFIX]);
        $this->assertTrue(
            $translations !== null && is_dir($translations),
            sprintf(
                'The translations of %s %s are not where the import map expects them: %s',
                self::CKEDITOR_PACKAGE,
                InstalledVersions::getPrettyVersion(self::CKEDITOR_PACKAGE),
                $imports[self::TRANSLATIONS_PREFIX],
            ),
        );

        $bundles = array_filter(
            $imports,
            static fn(string $specifier): bool => str_starts_with($specifier, self::CKEDITOR_PREFIX),
            ARRAY_FILTER_USE_KEY,
        );

        $this->assertNotSame([], $bundles, 'The import map maps no "@ckeditor/*" specifier.');

        $missing = [];
        foreach ($bundles as $specifier => $path) {
            $file = $this->resolveExtensionPath($path);
            if ($file === null || !is_file($file)) {
                $missing[] = sprintf('%s => %s', $specifier, $path);
            }
        }

        $this->assertSame(
            [],
            $missing,
            sprintf(
                'Mapped CKEditor bundles that %s %s does not ship: %s',
                self::CKEDITOR_PACKAGE,
                InstalledVersions::getPrettyVersion(self::CKEDITOR_PACKAGE),
                implode(', ', $missing),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadImports(): array
    {
        $configuration = require $this->extensionPath() . '/Configuration/JavaScriptModules.php';
        $this->assertIsArray($configuration);
        $this->assertIsArray($configuration['imports'] ?? null);

        return $configuration['imports'];
    }

    /**
     * @return list<string>
     */
    private function collectCkeditorSpecifiersOfShippedModules(): array
    {
        $directory = $this->extensionPath() . '/Resources/Public/JavaScript/frontend';
        $specifiers = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'js') {
                continue;
            }
            foreach ($this->importedSpecifiers($file->getPathname()) as $specifier) {
                if (str_starts_with($specifier, self::CKEDITOR_PREFIX)) {
                    $specifiers[$specifier] = $specifier;
                }
            }
        }
        ksort($specifiers);

        return array_values($specifiers);
    }

    /**
     * @return list<string>
     */
    private function importedSpecifiers(string $file): array
    {
        $source = file_get_contents($file);
        $this->assertIsString($source, sprintf('Could not read "%s".', $file));
        preg_match_all(self::IMPORT_PATTERN, $source, $matches);

        return array_values(array_unique($matches[2]));
    }

    /**
     * The address the import map gives a specifier: an exact entry, or the
     * longest prefix entry ending in a slash, the way the browser resolves it.
     *
     * @param array<string, string> $imports
     */
    private function resolveSpecifier(string $specifier, array $imports): ?string
    {
        if (isset($imports[$specifier])) {
            return $imports[$specifier];
        }
        $match = null;
        foreach ($imports as $prefix => $address) {
            if (str_ends_with($prefix, '/')
                && str_starts_with($specifier, $prefix)
                && ($match === null || strlen($prefix) > strlen($match))
            ) {
                $match = $prefix;
            }
        }

        return $match === null ? null : $imports[$match] . substr($specifier, strlen($match));
    }

    private function resolveExtensionPath(string $path): ?string
    {
        $packagePaths = [
            'EXT:rte_ckeditor/' => $this->ckeditorPackagePath(),
            'EXT:academic_persons_edit/' => $this->extensionPath(),
        ];
        foreach ($packagePaths as $prefix => $packagePath) {
            if (str_starts_with($path, $prefix)) {
                return $packagePath . '/' . substr($path, strlen($prefix));
            }
        }

        return null;
    }

    private function conventionalBundleFile(string $specifier): ?string
    {
        if (!str_starts_with($specifier, self::CKEDITOR_PREFIX)) {
            return null;
        }

        return $this->ckeditorPackagePath() . '/Resources/Public/Contrib/' . $specifier . '.js';
    }

    private function ckeditorPackagePath(): string
    {
        $path = InstalledVersions::getInstallPath(self::CKEDITOR_PACKAGE);
        $this->assertIsString($path, sprintf('Package "%s" is not installed.', self::CKEDITOR_PACKAGE));

        return rtrim($path, '/');
    }

    private function extensionPath(): string
    {
        return dirname(__DIR__, 3);
    }
}
