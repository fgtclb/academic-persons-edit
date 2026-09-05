<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersonsEdit\Service\LocalizedProfileUidResolver;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The resolver is four database queries and no logic that a mock could show, so it is
 * tested against real rows rather than as a unit.
 *
 * What it decides is which profile row an image write addresses, and getting it wrong
 * is not visible in the frontend: the write goes to a record of another language, or
 * the DataHandler is handed a uid that does not exist and the upload is answered with
 * a 500 (the state this class was written for).
 */
final class LocalizedProfileUidResolverTest extends AbstractAcademicPersonsEditTestCase
{
    private function subject(): LocalizedProfileUidResolver
    {
        return $this->get(LocalizedProfileUidResolver::class);
    }

    /**
     * uid 1  default language, translated into language 1 (uid 2)
     * uid 3  default language, translated into language 1 by a hidden row (uid 4)
     * uid 5  default language, no translation at all
     * uid 6  default language, deleted
     */
    private function importProfiles(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LocalizedProfileUidResolver/profiles.csv');
    }

    /**
     * @return \Generator<string, array{0: int, 1: int, 2: int|null}>
     */
    public static function resolutionProvider(): \Generator
    {
        yield 'the default record in the default language is itself' => [1, 0, 1];
        yield 'the default record resolves to its visible translation' => [1, 1, 2];
        yield 'the translation in its own language is itself' => [2, 1, 2];
        yield 'a language without a translation row writes the default record' => [5, 1, 5];
        yield 'a hidden translation is not found' => [3, 1, null];
        yield 'a deleted record is not found' => [6, 1, null];
        yield 'a record that does not exist is not found' => [999, 1, null];
        yield 'an invalid uid is not found' => [0, 1, null];
    }

    #[Test]
    #[DataProvider('resolutionProvider')]
    public function resolveAddressesTheRowOfTheRequestedLanguage(
        int $profileUid,
        int $languageId,
        ?int $expectedUid,
    ): void {
        $this->importProfiles();

        $this->assertSame($expectedUid, $this->subject()->resolve($profileUid, $languageId));
    }

    /**
     * The default-language fallback is the decision of Q4: a language whose editor shows
     * the default-language record - because Extbase's overlay returned it - must write to
     * that same record, exactly as the Extbase based text endpoints do. Answering "not
     * found" instead would leave one language able to change the name but not the image.
     */
    #[Test]
    public function aLanguageWithoutATranslationRowWritesTheDefaultRecordItRenders(): void
    {
        $this->importProfiles();

        $this->assertSame(5, $this->subject()->resolve(5, 1));
        $this->assertSame(5, $this->subject()->resolve(5, 2));
    }

    /**
     * A hidden translation is a row the visitor may not see. Falling back to the default
     * record would edit a different profile than the one on screen, so the caller is told
     * there is nothing to write and answers 404.
     */
    #[Test]
    public function aHiddenTranslationIsRefusedRatherThanFallingBackToTheDefaultRecord(): void
    {
        $this->importProfiles();

        $this->assertNull($this->subject()->resolve(3, 1));
        $this->assertNull($this->subject()->resolve(4, 1));
    }
}
