<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Tests\Unit\Context;

use Fgtclb\AcademicPersonsEdit\Context\ProfileAspect;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \Fgtclb\AcademicPersonsEdit\Context\ProfileAspect
 */
class ProfileAspectTest extends UnitTestCase
{
    /**
     * @test
     */
    public function emptyProfileUidsWillResultInNotHavingProfile(): void
    {
        $subject = new ProfileAspect([], 1);

        self::assertFalse($subject->get('hasProfile'));
    }

    /**
     * @test
     */
    public function givenProfileUidsWillResultInHavingProfile(): void
    {
        $subject = new ProfileAspect([1], 1);

        self::assertTrue($subject->get('hasProfile'));
    }

    /**
     * @test
     */
    public function emptyProfileUidsWillReturnEmptyArray(): void
    {
        $subject = new ProfileAspect([], 1);

        self::assertSame([], $subject->get('allProfileUids'));
    }

    /**
     * @test
     */
    public function givenProfileUidsWillReturnUids(): void
    {
        $subject = new ProfileAspect([1, 2], 1);

        self::assertSame([1, 2], $subject->get('allProfileUids'));
    }

    /**
     * @test
     */
    public function emptyProfileUidsAndGivenActiveProfileUidWillReturnZero(): void
    {
        $subject = new ProfileAspect([], 1);

        self::assertSame(0, $subject->get('activeProfileUid'));
    }

    /**
     * @test
     */
    public function givenProfileUidsAndGivenActiveProfileUidWillReturnActiveProfileUid(): void
    {
        $subject = new ProfileAspect([1], 1);

        self::assertSame(1, $subject->get('activeProfileUid'));
    }

    /**
     * @test
     */
    public function givenProfileUidsAndNoActiveProfileUidWillReturnZero(): void
    {
        $subject = new ProfileAspect([1], 0);

        self::assertSame(0, $subject->get('activeProfileUid'));
    }

    /**
     * @test
     */
    public function gettingInvalidValueWillResultInException(): void
    {
        $subject = new ProfileAspect([1], 1);

        self::expectException(AspectPropertyNotFoundException::class);
        self::expectExceptionCode(1689845908);

        $subject->get('does-not-exist');
    }
}
