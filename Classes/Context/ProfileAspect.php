<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Fgtclb\AcademicPersonsEdit\Context;

use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

final class ProfileAspect implements AspectInterface
{
    /**
     * @var list<int>
     */
    private array $profileUids;

    private int $activeProfileUid;

    /**
     * @param list<int> $profileUids
     */
    public function __construct(array $profileUids, int $activeProfileUid)
    {
        $this->profileUids = $profileUids;
        $this->activeProfileUid = $activeProfileUid;
    }

    /**
     * @return bool|int|int[]
     * @throws AspectPropertyNotFoundException
     */
    public function get(string $name)
    {
        return match ($name) {
            'hasProfile' => !empty($this->profileUids),
            'allProfileUids' => $this->profileUids,
            'activeProfileUid' => empty($this->profileUids) ? 0 : $this->activeProfileUid,
            default => throw new AspectPropertyNotFoundException(
                'Property "' . $name . '" not found in Aspect "' . __CLASS__ . '".',
                1689845908
            )
        };
    }
}
