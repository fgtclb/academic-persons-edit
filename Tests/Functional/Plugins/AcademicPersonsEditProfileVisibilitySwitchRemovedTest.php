<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\Plugins;

/**
 * The visibility switch removed with `~` by the installation.
 */
final class AcademicPersonsEditProfileVisibilitySwitchRemovedTest extends AbstractProfileVisibilitySwitchConfigurationTestCase
{
    protected function getSwitchConfigurationPackage(): string
    {
        return 'tests/test-visibility-switch-removed';
    }

    protected function isSwitchRendered(): bool
    {
        return false;
    }
}
