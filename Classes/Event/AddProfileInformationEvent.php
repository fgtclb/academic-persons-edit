<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Event;

use Fgtclb\AcademicPersons\Domain\Model\ProfileInformation;
use Fgtclb\AcademicPersonsEdit\Domain\Model\Profile;

final class AddProfileInformationEvent
{
    private Profile $profile;

    private ProfileInformation $profileInformation;

    public function __construct(Profile $profile, ProfileInformation $profileInformation)
    {
        $this->profile = $profile;
        $this->profileInformation = $profileInformation;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function getProfileInformation(): ProfileInformation
    {
        return $this->profileInformation;
    }
}
