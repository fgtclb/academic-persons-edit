<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Parser;

use FGTCLB\AcademicPersonsEdit\Domain\Parser\ProfileUpdatePayloadParser;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileUpdatePayloadParserTest extends UnitTestCase
{
    #[Test]
    public function parseReturnsPayloadForValidJson(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $result = $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                    'data' => [
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                    ],
                ],
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertSame(123, $result->getProfileUid());
        $this->assertSame(
            [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            $result->getData(),
        );
    }

    #[Test]
    public function parsePreservesEmptyString(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $result = $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                    'data' => [
                        'website' => '',
                    ],
                ],
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertTrue($result->hasProperty('website'));
        $this->assertSame('', $result->getData()['website']);
    }

    #[Test]
    public function parseDoesNotAddMissingProperties(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $result = $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                    'data' => [
                        'firstName' => 'Max',
                    ],
                ],
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertTrue($result->hasProperty('firstName'));
        $this->assertFalse($result->hasProperty('lastName'));
    }

    #[Test]
    public function parseAcceptsEmptyDataObject(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $result = $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                    'data' => [],
                ],
                JSON_THROW_ON_ERROR,
            )
        );

        $this->assertSame(123, $result->getProfileUid());
        $this->assertSame([], $result->getData());
    }

    #[Test]
    public function parseThrowsJsonExceptionForInvalidJson(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\JsonException::class);

        $subject->parse('{"profile": 123,');
    }

    #[Test]
    public function parseThrowsExceptionWhenJsonRootIsNotArray(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The JSON root must be an object.');

        $subject->parse('"foobar"');
    }

    #[Test]
    public function parseThrowsExceptionWhenProfileIsMissing(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'The payload must contain a positive integer profile and a data object.'
        );

        $subject->parse(
            json_encode(
                [
                    'data' => [
                        'firstName' => 'Max',
                    ],
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }

    #[Test]
    public function parseThrowsExceptionWhenProfileIsZero(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);

        $subject->parse(
            json_encode(
                [
                    'profile' => 0,
                    'data' => [],
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }

    #[Test]
    public function parseThrowsExceptionWhenProfileIsNegative(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);

        $subject->parse(
            json_encode(
                [
                    'profile' => -1,
                    'data' => [],
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }

    #[Test]
    public function parseThrowsExceptionWhenProfileIsString(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);

        $subject->parse(
            json_encode(
                [
                    'profile' => '123',
                    'data' => [],
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }

    #[Test]
    public function parseThrowsExceptionWhenDataIsMissing(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);

        $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }

    #[Test]
    public function parseThrowsExceptionWhenDataIsNotArray(): void
    {
        $subject = new ProfileUpdatePayloadParser();

        $this->expectException(\UnexpectedValueException::class);

        $subject->parse(
            json_encode(
                [
                    'profile' => 123,
                    'data' => 'invalid',
                ],
                JSON_THROW_ON_ERROR,
            )
        );
    }
}
