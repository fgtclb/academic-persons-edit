<?php

declare(strict_types=1);

/*
 * This file is part of the "academic_persons_edit" Extension for TYPO3 CMS.
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
     * @param list<int> $profileUids
     */
    public function __construct(private readonly array $profileUids, private readonly int $activeProfileUid) {}

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
                'Property "' . $name . '" not found in Aspect "' . self::class . '".',
                1689845908
            )
        };
    }
}
