<?php

declare(strict_types=1);

namespace TESTS\TestProjectProfileColumnRemoved\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

/**
 * A site package listener ordered after the persons settings that removes the
 * column of the project field `namePrefix`. The settings listener saw the column
 * and raised no notice, so only the check of the profile editor finds the mistake.
 */
final class RemoveNamePrefixColumn
{
    #[AsEventListener(
        identifier: 'academic-fixture/remove-name-prefix-column',
        after: 'academic-persons/apply-settings-to-tca',
    )]
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();
        unset($tca['tx_academicpersons_domain_model_profile']['columns']['tx_test_prefix']);
        $event->setTca($tca);
    }
}
