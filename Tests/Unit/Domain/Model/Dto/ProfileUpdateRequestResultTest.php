<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Unit\Domain\Model\Dto;

use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdatePayload;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileUpdateRequestResult;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProfileUpdateRequestResultTest extends UnitTestCase
{
    #[Test]
    public function successContainsPayloadAndProfile(): void
    {
        $payload = new ProfileUpdatePayload(
            profileUid: 123,
            data: ['firstName' => 'Jane'],
        );
        $profile = new Profile();

        $subject = ProfileUpdateRequestResult::success(
            $payload,
            $profile,
        );

        $this->assertTrue($subject->isValid());
        $this->assertSame($payload, $subject->getPayload());
        $this->assertSame($profile, $subject->getProfile());
        $this->assertNull($subject->getError());
        $this->assertSame(200, $subject->getStatusCode());
    }

    #[Test]
    public function failureContainsOnlyErrorAndStatusCode(): void
    {
        $subject = ProfileUpdateRequestResult::failure(
            'invalid_payload',
            400,
        );

        $this->assertFalse($subject->isValid());
        $this->assertNull($subject->getPayload());
        $this->assertNull($subject->getProfile());
        $this->assertSame('invalid_payload', $subject->getError());
        $this->assertSame(400, $subject->getStatusCode());
    }
}
