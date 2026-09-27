<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The visibility switch read-only by the installation.
 */
final class AcademicPersonsEditProfileVisibilitySwitchReadOnlyTest extends AbstractProfileVisibilitySwitchConfigurationTestCase
{
    protected function getSwitchConfigurationPackage(): string
    {
        return 'tests/test-visibility-switch-readonly';
    }

    protected function isSwitchRendered(): bool
    {
        return true;
    }
}
