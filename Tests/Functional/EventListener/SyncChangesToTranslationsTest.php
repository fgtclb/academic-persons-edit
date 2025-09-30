<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Service\RecordSynchronizer;
use FGTCLB\AcademicPersons\Service\RecordSynchronizerInterface;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Profile\ProfileTranslator;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\SiteFinder;

final class SyncChangesToTranslationsTest extends AbstractAcademicPersonsEditTestCase
{
    #[Test]
    public function eventListenerCanBeRetrievedFromContainer(): void
    {
        $eventListener = $this->get(SyncChangesToTranslations::class);
        $this->assertInstanceOf(SyncChangesToTranslations::class, $eventListener);

        $eventListenerReflection = new \ReflectionClass($eventListener);
        $this->assertInstanceOf(RecordSynchronizerInterface::class, $eventListenerReflection->getProperty('recordSyncronizer')->getValue($eventListener));
        $this->assertInstanceOf(RecordSynchronizer::class, $eventListenerReflection->getProperty('recordSyncronizer')->getValue($eventListener));
        $this->assertInstanceOf(ProfileTranslator::class, $eventListenerReflection->getProperty('profileTranslator')->getValue($eventListener));
        $this->assertInstanceOf(SiteFinder::class, $eventListenerReflection->getProperty('siteFinder')->getValue($eventListener));
    }
}
