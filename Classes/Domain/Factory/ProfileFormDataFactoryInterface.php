<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Controller\ProfileController;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;

/**
 * Defines the surface for creating ProfileFormData instances in {@see ProfileController} actions.
 * @internal not part of public API.
 */
interface ProfileFormDataFactoryInterface
{
    public function createFromProfile(
        PluginControllerActionContext $pluginControllerContext,
        Profile $profile,
    ): ProfileFormData;
}
