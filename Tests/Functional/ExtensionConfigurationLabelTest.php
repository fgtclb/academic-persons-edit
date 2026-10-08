<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\TypoScript\AST\CommentAwareAstBuilder;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\AST\Traverser\AstTraverser;
use TYPO3\CMS\Core\TypoScript\AST\Visitor\AstConstantCommentVisitor;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LosslessTokenizer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * The extension configuration form of the install tool splits a label at its first
 * colon into a title and a description. The two deprecated options used to name
 * `EXT:academic_persons` before any other colon, which left the title "Deprecated and
 * no effect. See EXT" and moved the rest into the description.
 *
 * The labels are read the way the install tool reads them. The install tool always
 * renders them in the default language, so the German data sets guard the translation
 * itself, not what the install tool shows.
 */
final class ExtensionConfigurationLabelTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * @return array<string, array{label: string, description: string}> by option name
     */
    private function getExtensionConfigurationLabels(string $extensionKey, string $languageKey): array
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($languageKey);
        $ast = $this->get(CommentAwareAstBuilder::class)->build(
            (new LosslessTokenizer())->tokenize(
                (string)file_get_contents(ExtensionManagementUtility::extPath($extensionKey, 'ext_conf_template.txt'))
            ),
            new RootNode(),
        );
        $visitor = new AstConstantCommentVisitor();
        (new AstTraverser())->traverse($ast, [$visitor]);
        $labels = [];
        foreach ($visitor->getConstants() as $name => $constant) {
            $labels[$name] = ['label' => $constant['label'], 'description' => $constant['description']];
        }
        return $labels;
    }

    public static function deprecatedOptionDataSets(): \Generator
    {
        yield 'autoCreateProfiles, English' => [
            'default',
            'profile.autoCreateProfiles',
            'Auto create profile (deprecated, no effect)',
            'EXT:academic_persons, option profile.autoCreateProfiles',
        ];
        yield 'createProfileForUserGroups, English' => [
            'default',
            'profile.createProfileForUserGroups',
            'Create for user groups (deprecated, no effect)',
            'EXT:academic_persons, option profile.createProfileForUserGroups',
        ];
        yield 'autoCreateProfiles, German' => [
            'de',
            'profile.autoCreateProfiles',
            'Profil automatisch anlegen (veraltet, ohne Wirkung)',
            'EXT:academic_persons, Option profile.autoCreateProfiles',
        ];
        yield 'createProfileForUserGroups, German' => [
            'de',
            'profile.createProfileForUserGroups',
            'Für Benutzergruppen anlegen (veraltet, ohne Wirkung)',
            'EXT:academic_persons, Option profile.createProfileForUserGroups',
        ];
    }

    #[DataProvider('deprecatedOptionDataSets')]
    #[Test]
    public function aDeprecatedOptionNamesWhereItMoved(
        string $languageKey,
        string $option,
        string $expectedTitle,
        string $expectedPartOfDescription,
    ): void {
        $labels = $this->getExtensionConfigurationLabels('academic_persons_edit', $languageKey);

        $this->assertSame($expectedTitle, $labels[$option]['label']);
        $this->assertStringContainsString($expectedPartOfDescription, $labels[$option]['description']);
    }

    public static function extensionDataSets(): \Generator
    {
        foreach (['academic_persons', 'academic_persons_edit'] as $extensionKey) {
            foreach (['default', 'de'] as $languageKey) {
                yield $extensionKey . ', ' . $languageKey => [$extensionKey, $languageKey];
            }
        }
    }

    /**
     * An extension reference written into a label before its separator cuts the title
     * at `EXT`. The options of `academic_persons` are covered as well, because they
     * are the ones these labels point to.
     */
    #[DataProvider('extensionDataSets')]
    #[Test]
    public function noTitleIsCutAtAnExtensionReference(string $extensionKey, string $languageKey): void
    {
        foreach ($this->getExtensionConfigurationLabels($extensionKey, $languageKey) as $option => $label) {
            $this->assertNotSame('', $label['label'], $option);
            $this->assertStringEndsNotWith('EXT', $label['label'], $option);
            $this->assertStringEndsNotWith('LLL', $label['label'], $option);
        }
    }
}
