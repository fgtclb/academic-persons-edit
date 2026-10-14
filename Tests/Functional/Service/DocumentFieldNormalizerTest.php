<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Service;

use FGTCLB\AcademicPersons\Settings\AcademicPersonsSettings;
use FGTCLB\AcademicPersonsEdit\Service\DocumentFieldNormalizer;
use FGTCLB\AcademicPersonsEdit\Service\Exception\EditorRequestException;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * What a document request may write, checked against the document sections the
 * persons settings ship: `contracts` and the profile information section
 * `cooperation`.
 *
 * A functional test rather than a unit test, because every field definition
 * carries a translated label, and the label lookup needs a booted TYPO3.
 */
final class DocumentFieldNormalizerTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * The Extbase request of a frontend plugin in the default language, which the
     * label lookup reads on TYPO3 v14.
     */
    private function createRequest(): Request
    {
        return new Request(
            (new ServerRequest('https://www.acme.com/'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('language', new SiteLanguage(0, 'en_US.UTF-8', new Uri('https://www.acme.com/'), []))
                ->withAttribute('extbase', new ExtbaseRequestParameters()),
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $managedProperties
     * @return array<string, mixed>
     */
    private function normalize(string $section, array $fields, bool $creating, array $managedProperties = []): array
    {
        $documentSection = $this->get(AcademicPersonsSettings::class)->getDocumentSection($section);
        $this->assertNotNull($documentSection);
        return $this->get(DocumentFieldNormalizer::class)->normalizeAndValidateDocumentFields(
            $this->createRequest(),
            $documentSection,
            $fields,
            $creating,
            $managedProperties,
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, list<string>>
     */
    private function refuse(string $section, array $fields, bool $creating): array
    {
        try {
            $this->normalize($section, $fields, $creating);
        } catch (EditorRequestException $exception) {
            $this->assertSame('validation_failed', $exception->error);
            $this->assertSame(422, $exception->statusCode);
            $this->assertSame('The submitted document data is invalid.', $exception->responseMessage);
            return $exception->fieldErrors;
        }
        $this->fail('The fields were accepted.');
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function acceptedDateProvider(): \Generator
    {
        yield 'the format the control shows' => ['01.02.2026'];
        yield 'the format of the former native control' => ['2026-02-01'];
    }

    #[Test]
    #[DataProvider('acceptedDateProvider')]
    public function aContractDateIsReadInEitherFormat(string $date): void
    {
        $normalized = $this->normalize('contracts', ['validFrom' => $date], false);

        $this->assertInstanceOf(\DateTime::class, $normalized['validFrom']);
        $this->assertSame('2026-02-01 00:00:00', $normalized['validFrom']->format('Y-m-d H:i:s'));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function refusedDateProvider(): \Generator
    {
        yield 'a day the month does not have' => ['32.01.2026'];
        yield 'a day the month does not have, ISO' => ['2026-02-30'];
        yield 'a day without its leading zero' => ['1.02.2026'];
        yield 'neither format' => ['February 1st'];
    }

    #[Test]
    #[DataProvider('refusedDateProvider')]
    public function aDateThatDoesNotRoundTripIsRefused(string $date): void
    {
        $this->assertSame(
            ['validFrom' => ['The value must be a valid date.']],
            $this->refuse('contracts', ['validFrom' => $date], false),
        );
    }

    /**
     * @return \Generator<string, array{0: int|string}>
     */
    public static function yearOutOfBoundsProvider(): \Generator
    {
        yield 'below the range' => [-1];
        yield 'above the range' => [10000];
        yield 'above the range, as text' => ['10000'];
    }

    #[Test]
    #[DataProvider('yearOutOfBoundsProvider')]
    public function aYearOutsideTheBoundsOfTheControlIsRefused(int|string $year): void
    {
        $this->assertSame(
            ['year' => ['The value must be between 0 and 9999.']],
            $this->refuse('cooperation', ['year' => $year], false),
        );
    }

    #[Test]
    public function aYearWithinTheBoundsIsStoredAsAnInteger(): void
    {
        $this->assertSame(
            ['year' => 0, 'yearStart' => 2026, 'yearEnd' => 9999],
            $this->normalize('cooperation', ['year' => 0, 'yearStart' => '2026', 'yearEnd' => 9999], false),
        );
    }

    #[Test]
    public function aSelectValueThatNamesNoRecordIsRefused(): void
    {
        $this->assertSame(
            ['functionType' => ['The selected value is not available.']],
            $this->refuse('contracts', ['functionType' => 999], false),
        );
    }

    /**
     * No shipped section has a checkbox field, so the value is normalised directly
     * rather than through a section: the method throws what the section check turns
     * into the error of the field.
     */
    #[Test]
    public function aCheckboxValueThatIsNotBooleanIsNotNormalised(): void
    {
        $normalizer = $this->get(DocumentFieldNormalizer::class);
        $normalize = new \ReflectionMethod($normalizer, 'normalizeDocumentFieldValue');

        $this->assertTrue($normalize->invoke($normalizer, 'flag', 'checkbox', true, false));
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The value must be boolean.');

        $normalize->invoke($normalizer, 'flag', 'checkbox', 'yes', false);
    }

    #[Test]
    public function richTextIsSanitised(): void
    {
        $normalized = $this->normalize(
            'cooperation',
            ['bodytext' => '<p>Kept</p><script>alert(1)</script>'],
            false,
        );

        $this->assertStringContainsString('<p>Kept</p>', $normalized['bodytext']);
        $this->assertStringNotContainsString('script', $normalized['bodytext']);
    }

    #[Test]
    public function aFieldTheSynchronisationManagesIsDroppedAndNotRefused(): void
    {
        $this->assertSame(
            ['room' => 'B 1.02'],
            $this->normalize('contracts', ['position' => 'Dean', 'room' => ' B 1.02 '], false, ['position']),
        );
    }

    #[Test]
    public function aFieldTheSectionDoesNotHaveIsRefused(): void
    {
        $this->assertSame(
            ['nonsense' => ['This field cannot be changed.']],
            $this->refuse('contracts', ['nonsense' => 'x', 'room' => 'B 1.02'], false),
        );
    }

    #[Test]
    public function aRequiredFieldIsRefusedWhenACreationLacksIt(): void
    {
        $this->assertSame(
            [
                'position' => ['This field is required.'],
                'validFrom' => ['This field is required.'],
            ],
            $this->refuse('contracts', ['room' => 'B 1.02'], true),
        );
    }

    #[Test]
    public function aRequiredFieldIsNotDemandedOfAnUpdate(): void
    {
        $this->assertSame(['room' => 'B 1.02'], $this->normalize('contracts', ['room' => 'B 1.02'], false));
    }
}
