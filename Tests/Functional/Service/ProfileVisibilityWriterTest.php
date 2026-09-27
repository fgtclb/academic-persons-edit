<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersonsEdit\Service\ProfileVisibilityWriter;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The write behind the owner's visibility switch, against real rows: which record it
 * addresses, and that the value reaches the translations through the DataHandler
 * rather than through a synchronisation of the editor.
 */
final class ProfileVisibilityWriterTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * uid 1  default language, translated into language 1 (uid 2) and 2 (uid 3)
     * uid 4  a standalone translation in language 1, without a default record
     * uid 5  default language, deleted, translated into language 1 (uid 6)
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProfileVisibilityWriter/profiles.csv');
    }

    private function subject(): ProfileVisibilityWriter
    {
        return $this->get(ProfileVisibilityWriter::class);
    }

    /**
     * @return array<int, int> the hidden flag by profile uid
     */
    private function getHiddenByUid(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tx_academicpersons_domain_model_profile')
            ->executeQuery('SELECT uid, hidden FROM tx_academicpersons_domain_model_profile ORDER BY uid')
            ->fetchAllAssociative();
        $hiddenByUid = [];
        foreach ($rows as $row) {
            $hiddenByUid[(int)$row['uid']] = (int)$row['hidden'];
        }
        return $hiddenByUid;
    }

    #[Test]
    public function theDefaultRecordAndEveryTranslationAreHiddenAndShownTogether(): void
    {
        $this->assertTrue($this->subject()->write(1, true));
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0, 6 => 0], $this->getHiddenByUid());

        $this->assertFalse($this->subject()->write(1, false));
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], $this->getHiddenByUid());
    }

    /**
     * A translation uid is written through its default record, so the translation is
     * never hidden on its own while the default language stays public.
     */
    #[Test]
    public function aTranslationIsWrittenThroughItsDefaultRecord(): void
    {
        $this->assertTrue($this->subject()->write(2, true));

        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0, 6 => 0], $this->getHiddenByUid());
    }

    /**
     * A standalone translation, as a site language with `fallbackType: free` may
     * carry, has no record to follow and is written itself.
     */
    #[Test]
    public function aStandaloneTranslationIsWrittenItself(): void
    {
        $this->assertTrue($this->subject()->write(4, true));

        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 0, 6 => 0], $this->getHiddenByUid());
    }

    #[Test]
    public function aDeletedProfileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790503202);

        $this->subject()->write(5, true);
    }

    #[Test]
    public function aTranslationOfADeletedProfileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1790503203);

        $this->subject()->write(6, true);
    }
}
