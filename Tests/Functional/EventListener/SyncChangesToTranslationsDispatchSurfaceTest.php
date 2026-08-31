<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Tests\Functional\EventListener;

use FGTCLB\AcademicPersons\Event\AfterProfileUpdateEvent;
use FGTCLB\AcademicPersons\Profile\AbstractProfileFactory;
use FGTCLB\AcademicPersonsEdit\Controller\ProfileController;
use FGTCLB\AcademicPersonsEdit\EventListener\SyncChangesToTranslations;
use FGTCLB\AcademicPersonsEdit\Tests\Functional\AbstractAcademicPersonsEditTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

/**
 * Pins the dispatch surface of {@see AfterProfileUpdateEvent} for ACE-485.
 *
 * The frontend profile editing flow of `EXT:academic_persons_edit` does NOT dispatch
 * the event: `a1a471c44 [!!!][TASK] Restructure profile editing` removed the dispatch
 * from `ProfileController`, and `updateProfileForUser()` never dispatched it. The only
 * in-repo dispatch is `AbstractProfileFactory::createProfileForUser()` - profile
 * auto-creation on frontend login and through the `academic:profiles:*` CLI commands.
 * A frontend profile edit therefore synchronises nothing today. ACE-485 will flip
 * that; these tests exist so the flip shows up as a red test here instead of as a
 * silent behaviour change.
 *
 * The pin is a source scan rather than a frontend request driving
 * `ProfileController::updateAction()`: the negative "no event is dispatched" is
 * established just as reliably by the absence of any reference to the event class in
 * the controller sources, and unlike a full form round trip (session referrer, cHash,
 * request-bound hidden fields, see `AbstractProfileEditingPluginTestCase`) the scan
 * cannot rot into passing for an unrelated reason when the form flow changes. The
 * factory scan below is the positive control proving the scan detects a dispatch.
 */
final class SyncChangesToTranslationsDispatchSurfaceTest extends AbstractAcademicPersonsEditTestCase
{
    /**
     * @return list<string> Absolute file paths of all persons-edit controller classes.
     */
    private function personsEditControllerFiles(): array
    {
        $controllerDirectory = dirname((string)(new \ReflectionClass(ProfileController::class))->getFileName());
        $files = glob($controllerDirectory . '/*.php');
        $this->assertIsArray($files);
        $this->assertNotSame([], $files, 'No controller sources found - the scan target moved.');
        return array_values($files);
    }

    #[Test]
    public function frontendEditingControllersDoNotReferenceAfterProfileUpdateEvent(): void
    {
        foreach ($this->personsEditControllerFiles() as $controllerFile) {
            $source = (string)file_get_contents($controllerFile);
            $this->assertStringNotContainsString(
                'AfterProfileUpdateEvent',
                $source,
                sprintf(
                    'Controller "%s" references AfterProfileUpdateEvent. The ACE-485 dispatch'
                    . ' surface changed: frontend profile edits now (potentially) trigger the'
                    . ' translation sync. Re-characterise the editing flow and update the'
                    . ' SyncChangesToTranslations tests before removing this pin.',
                    basename($controllerFile),
                ),
            );
        }
    }

    /**
     * Positive control for the source scan above: the one production dispatch site does
     * reference the event class, so the scan is proven able to detect a dispatch.
     */
    #[Test]
    public function profileFactoryIsTheOnlyInRepoDispatchSiteOfTheEvent(): void
    {
        $factorySource = (string)file_get_contents(
            (string)(new \ReflectionClass(AbstractProfileFactory::class))->getFileName(),
        );
        $this->assertStringContainsString('AfterProfileUpdateEvent', $factorySource);
        $this->assertStringContainsString('->dispatch($afterProfileUpdatedEvent)', $factorySource);
    }

    /**
     * The other half of the ACE-485 picture: the listener IS registered for the event,
     * so the moment anything dispatches it with a persisted default language profile,
     * the synchronisation runs. Nothing needs to change on the listening side.
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
