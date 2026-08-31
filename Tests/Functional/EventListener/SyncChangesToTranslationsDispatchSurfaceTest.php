<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Profile\AbstractProfileFactory;
use FGTCLB\AcademicPersonsEdit\Controller\AbstractActionController;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

/**
 * Pins the dispatch surface of {@see AfterProfileUpdateEvent} for ACE-485.
 *
 * `a1a471c44 [!!!][TASK] Restructure profile editing` removed the dispatch from
 * `ProfileController`, so for a while a frontend profile edit synchronised nothing -
 * the only in-repo dispatch was `AbstractProfileFactory::createProfileForUser()`.
 * ACE-485 restored the dispatch across the whole frontend editing flow: every action
 * that persists a change to the profile aggregate calls
 * `AbstractActionController::persistAndDispatchProfileUpdate()`, which is the one
 * place in `EXT:academic_persons_edit` that dispatches the event.
 *
 * The pin is a source scan rather than driving every one of the ~25 mutating actions
 * through the frontend plugin harness: the scan states the structural fact - each
 * concrete controller routes its persistence through the shared helper, and the
 * helper dispatches the event - and cannot rot into passing for an unrelated reason
 * when the form flow changes. The behaviour itself is covered where it is observable:
 * `AcademicPersonsEditProfileEditTranslationSyncTest` drives a real frontend edit and
 * asserts the translated row it produces, and the `SyncChangesToTranslations*` tests
 * cover the listening side.
 */
final class SyncChangesToTranslationsDispatchSurfaceTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * @return list<string> Absolute file paths of all concrete persons-edit controller classes.
     */
    private function concretePersonsEditControllerFiles(): array
    {
        $controllerDirectory = dirname((string)(new \ReflectionClass(ProfileController::class))->getFileName());
        $files = glob($controllerDirectory . '/*.php');
        $this->assertIsArray($files);
        $files = array_values(array_filter(
            $files,
            static fn(string $file): bool => basename($file) !== 'AbstractActionController.php',
        ));
        $this->assertNotSame([], $files, 'No controller sources found - the scan target moved.');
        return $files;
    }

    /**
     * ACE-485: every frontend editing controller persists its changes through the shared
     * `persistAndDispatchProfileUpdate()` helper, so each of them triggers the event
     * (and with it the translation synchronisation) on its persistence path.
     */
    #[Test]
    public function everyFrontendEditingControllerDispatchesThroughTheSharedHelper(): void
    {
        foreach ($this->concretePersonsEditControllerFiles() as $controllerFile) {
            $source = (string)file_get_contents($controllerFile);
            $this->assertStringContainsString(
                '$this->persistAndDispatchProfileUpdate(',
                $source,
                sprintf(
                    'Controller "%s" does not call persistAndDispatchProfileUpdate(). Every action'
                    . ' persisting a change to the profile aggregate must route through the helper,'
                    . ' so the change is announced via AfterProfileUpdateEvent (ACE-485).',
                    basename($controllerFile),
                ),
            );
        }
    }

    /**
     * The other half of the helper pin: the shared base controller is the one dispatch
     * site of the frontend editing flow, and it dispatches the real event class.
     */
    #[Test]
    public function abstractActionControllerIsTheEditingFlowDispatchSite(): void
    {
        $baseControllerSource = (string)file_get_contents(
            (string)(new \ReflectionClass(AbstractActionController::class))->getFileName(),
        );
        $this->assertStringContainsString(
            '->dispatch(new AfterProfileUpdateEvent(',
            $baseControllerSource,
            'AbstractActionController::persistAndDispatchProfileUpdate() no longer dispatches'
            . ' AfterProfileUpdateEvent - the frontend editing flow lost its synchronisation'
            . ' trigger again (see a1a471c44 for how that went unnoticed last time).',
        );
    }

    /**
     * Positive control for the source scans above, and a pin of its own: the profile
     * auto-creation dispatch of `EXT:academic_persons` is unchanged by ACE-485.
     */
    #[Test]
    public function profileFactoryStillDispatchesTheEventAfterProfileCreation(): void
    {
        $factorySource = (string)file_get_contents(
            (string)(new \ReflectionClass(AbstractProfileFactory::class))->getFileName(),
        );
        $this->assertStringContainsString('AfterProfileUpdateEvent', $factorySource);
        $this->assertStringContainsString('->dispatch($afterProfileUpdatedEvent)', $factorySource);
    }

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
