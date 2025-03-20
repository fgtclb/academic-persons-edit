<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Event;

use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;

final class AfterProfileUpdateEvent
{
    public function __construct(private readonly Profile $profile) {}

    public function getProfile(): Profile
    {
        return $this->profile;
    }
}
