<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The visibility switch disabled by the installation.
 */
final class AcademicPersonsEditProfileVisibilitySwitchDisabledTest extends AbstractProfileVisibilitySwitchConfigurationTestCase
{
    protected function getSwitchConfigurationPackage(): string
    {
        return 'tests/test-visibility-switch-disabled';
    }

    protected function isSwitchRendered(): bool
    {
        return true;
    }
}
