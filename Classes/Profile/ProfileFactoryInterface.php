<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Profile;

use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

interface ProfileFactoryInterface
{
    public function shouldCreateProfileForUser(FrontendUserAuthentication $frontendUserAuthentication): bool;

    /**
     * @return int The new profile uid. Null if profile could not be created.
     */
    public function createProfileForUser(FrontendUserAuthentication $frontendUserAuthentication): ?int;
}
