<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Tests\Unit\Context;

use Fgtclb\AcademicPersonsEdit\Context\ProfileAspect;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Fgtclb\AcademicPersonsEdit\Context\ProfileAspect::class)]
class ProfileAspectTest extends UnitTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function emptyProfileUidsWillResultInNotHavingProfile(): void
    {
        $subject = new ProfileAspect([], 1);

        $this->assertFalse($subject->get('hasProfile'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function givenProfileUidsWillResultInHavingProfile(): void
    {
        $subject = new ProfileAspect([1], 1);

        $this->assertTrue($subject->get('hasProfile'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function emptyProfileUidsWillReturnEmptyArray(): void
    {
        $subject = new ProfileAspect([], 1);

        $this->assertSame([], $subject->get('allProfileUids'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function givenProfileUidsWillReturnUids(): void
    {
        $subject = new ProfileAspect([1, 2], 1);

        $this->assertSame([1, 2], $subject->get('allProfileUids'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function emptyProfileUidsAndGivenActiveProfileUidWillReturnZero(): void
    {
        $subject = new ProfileAspect([], 1);

        $this->assertSame(0, $subject->get('activeProfileUid'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function givenProfileUidsAndGivenActiveProfileUidWillReturnActiveProfileUid(): void
    {
        $subject = new ProfileAspect([1], 1);

        $this->assertSame(1, $subject->get('activeProfileUid'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function givenProfileUidsAndNoActiveProfileUidWillReturnZero(): void
    {
        $subject = new ProfileAspect([1], 0);

        $this->assertSame(0, $subject->get('activeProfileUid'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function gettingInvalidValueWillResultInException(): void
    {
        $subject = new ProfileAspect([1], 1);

        self::expectException(AspectPropertyNotFoundException::class);
        self::expectExceptionCode(1689845908);

        $subject->get('does-not-exist');
    }
}
