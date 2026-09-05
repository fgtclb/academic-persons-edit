<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileUpdatePayloadTest extends UnitTestCase
{
    #[Test]
    public function constructorDataCanBeReadWithoutModification(): void
    {
        $data = [
            'firstName' => 'Jane',
            'website' => '',
            'skipSync' => false,
            'customValue' => null,
        ];

        $subject = new ProfileUpdatePayload(
            profileUid: 123,
            data: $data,
        );

        $this->assertSame(123, $subject->getProfileUid());
        $this->assertSame($data, $subject->getData());
    }

    #[Test]
    #[DataProvider('propertyValues')]
    public function presentPropertyCanBeDistinguishedFromMissingProperty(
        mixed $value,
    ): void {
        $subject = new ProfileUpdatePayload(
            profileUid: 123,
            data: ['property' => $value],
        );

        $this->assertTrue($subject->hasProperty('property'));
        $this->assertSame($value, $subject->getProperty('property'));
        $this->assertFalse($subject->hasProperty('missing'));
        $this->assertNull($subject->getProperty('missing'));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function propertyValues(): array
    {
        return [
            'non-empty string' => ['value'],
            'empty string' => [''],
            'false' => [false],
            'zero' => [0],
            'null' => [null],
            'array' => [['nested' => 'value']],
        ];
    }
}
