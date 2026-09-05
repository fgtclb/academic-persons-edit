<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

/**
 * Pins the listening side of {@see AfterProfileUpdateEvent} for ACE-485.
 *
 * The dispatching side is driven end to end by
 * `AcademicPersonsEditProfileEditingTranslationSyncTest`, which saves a field
 * through the plugin and reads the translation back. What no other test sees is
 * the registration itself: a listener that is not registered dispatches nothing
 * and fails no service test.
 */
final class SyncChangesToTranslationsRegistrationTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * The listening side: the sync listener IS registered for the event, so every
     * dispatch with a persisted default language profile runs the synchronisation.
     */
    #[Test]
    public function syncListenerIsRegisteredForAfterProfileUpdateEvent(): void
    {
        $listenerProvider = $this->get(ListenerProvider::class);
        $services = array_column(
            $listenerProvider->getAllListenerDefinitions()[AfterProfileUpdateEvent::class] ?? [],
            'service',
        );
        $this->assertContains(SyncChangesToTranslations::class, $services);
    }
}
