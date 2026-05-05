<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Domain\Factory;

use FGTCLB\AcademicBase\Domain\Model\Dto\PluginControllerActionContext;
use FGTCLB\AcademicPersons\Domain\Model\Profile;
use FGTCLB\AcademicPersonsEdit\Domain\Model\Dto\ProfileFormData;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Provides default implementation of {@see ProfileFormDataFactoryInterface} handling default
 * `EXT:academic_persons_edit` data mapping from {@see Profile} to {@see ProfileFormData}.
 *
 * Projects can use service decoration to provide custom mappings; {@see PluginControllerActionContext} is
 * provided to gave context for decision if custom handling is reasonable or requires additional work.
 *
 * @internal not part of public API.
 */
#[AsAlias(id: ProfileFormDataFactoryInterface::class, public: true)]
final class ProfileFormDataFactory implements ProfileFormDataFactoryInterface
{
    public function createFromProfile(
        PluginControllerActionContext $pluginControllerContext,
        Profile $profile,
    ): ProfileFormData {
        return new ProfileFormData(
            title: $profile->getTitle(),
            firstName: $profile->getFirstName(),
            middleName: $profile->getMiddleName(),
            lastName: $profile->getLastName(),
            gender: $profile->getGender(),
            publicationsLink: $profile->getPublicationsLink(),
            publicationsLinkTitle: $profile->getPublicationsLinkTitle(),
            website: $profile->getWebsite(),
            websiteTitle: $profile->getWebsiteTitle(),
            coreCompetences: $profile->getCoreCompetences(),
            miscellaneous: $profile->getMiscellaneous(),
            supervisedDoctoralThesis: $profile->getSupervisedDoctoralThesis(),
            supervisedThesis: $profile->getSupervisedThesis(),
            teachingArea: $profile->getTeachingArea(),
            skipSync: $profile->getSkipSync(),
        );
    }
}
